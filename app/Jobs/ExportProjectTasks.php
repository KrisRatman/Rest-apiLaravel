<?php

namespace App\Jobs;

use App\Enums\ExportStatus;
use App\Models\Export;
use App\Models\Task;
use App\Notifications\ExportFinishedNotification;
use App\Queries\TaskListQuery;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\Attributes\DeleteWhenMissingModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Собирает CSV с задачами проекта. Фильтры и сортировка — те же, что у
 * GET /projects/{project}/tasks: запрос восстанавливается из сохранённых параметров.
 *
 * Повторный запуск безопасен: файл перезаписывается целиком.
 */
#[DeleteWhenMissingModels]
class ExportProjectTasks implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60];

    /**
     * Меньше retry_after очереди (90 c): иначе задание выдадут второму воркеру,
     * пока первый ещё пишет файл.
     */
    public int $timeout = 60;

    /**
     * Символы, с которых Excel начинает формулу. Такие ячейки экранируем,
     * иначе заголовок задачи «=HYPERLINK(...)» выполнится у того, кто откроет файл.
     */
    private const FORMULA_PREFIXES = ['=', '+', '-', '@', "\t", "\r"];

    public function __construct(public Export $export) {}

    public function handle(): void
    {
        $this->export->update(['status' => ExportStatus::Processing]);

        $request = Request::create('/', 'GET', $this->export->filters ?? []);
        $tasks = TaskListQuery::for($this->export->project->tasks(), $request);

        $stream = fopen('php://temp', 'r+');
        // BOM, чтобы Excel распознал UTF-8 и не превратил кириллицу в кракозябры.
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, [
            'ID', 'Title', 'Status', 'Priority', 'Assignee', 'Assignee email',
            'Due date', 'Labels', 'Comments', 'Created at', 'Completed at',
        ], escape: '');

        $rows = 0;
        foreach ($tasks->lazy(500) as $task) {
            fputcsv($stream, $this->row($task), escape: '');
            $rows++;
        }

        rewind($stream);
        $path = "exports/{$this->export->id}.csv";
        Storage::disk('local')->put($path, $stream);
        fclose($stream);

        $this->export->forceFill([
            'status' => ExportStatus::Completed,
            'file_path' => $path,
            'rows_count' => $rows,
            'error' => null,
            'finished_at' => now(),
        ])->save();

        $this->export->user->notify(new ExportFinishedNotification($this->export));
    }

    /**
     * Вызывается один раз, когда попытки кончились.
     */
    public function failed(?Throwable $exception): void
    {
        $this->export->forceFill([
            'status' => ExportStatus::Failed,
            'error' => 'The export could not be generated.',
            'finished_at' => now(),
        ])->save();

        $this->export->user->notify(new ExportFinishedNotification($this->export));
    }

    /**
     * @return list<string|int|null>
     */
    private function row(Task $task): array
    {
        return [
            $task->id,
            $this->safe($task->title),
            $task->status->value,
            $task->priority->value,
            $this->safe($task->assignee?->name),
            $task->assignee?->email,
            $task->due_date?->toDateString(),
            $this->safe($task->labels->pluck('name')->implode(', ')),
            $task->comments_count,
            $task->created_at->toDateTimeString(),
            $task->completed_at?->toDateTimeString(),
        ];
    }

    private function safe(?string $value): ?string
    {
        if ($value !== null && $value !== '' && in_array($value[0], self::FORMULA_PREFIXES, true)) {
            return "'".$value;
        }

        return $value;
    }
}
