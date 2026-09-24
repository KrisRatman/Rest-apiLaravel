<?php

use App\Enums\ExportStatus;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Jobs\ExportProjectTasks;
use App\Models\Export;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Notifications\ExportFinishedNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    Notification::fake();
});

/**
 * @return list<list<string>>
 */
function readExportCsv(Export $export): array
{
    $content = Storage::disk('local')->get($export->file_path);

    expect($content)->toStartWith("\xEF\xBB\xBF");

    $lines = preg_split('/\R/', trim(substr($content, 3)));

    return array_map(fn (string $line) => str_getcsv($line, escape: ''), $lines);
}

it('writes the filtered and sorted tasks to csv and notifies the author', function () {
    $project = Project::factory()->create();
    $assignee = memberOf($project->team);
    $label = Label::factory()->for($project->team)->create(['name' => 'bug']);
    $urgent = Task::factory()->for($project)->priority(TaskPriority::Urgent)->create([
        'title' => 'Fix crash',
        'assignee_id' => $assignee->id,
        'due_date' => '2026-10-01',
    ]);
    $urgent->labels()->attach($label);
    $low = Task::factory()->for($project)->priority(TaskPriority::Low)->create(['title' => 'Polish icons']);
    Task::factory()->for($project)->done()->create();
    Task::factory()->create();
    $export = Export::factory()->for($project)->create([
        'filters' => ['filter' => ['status' => 'todo'], 'sort' => '-priority'],
    ]);

    (new ExportProjectTasks($export))->handle();

    $export->refresh();
    expect($export->status)->toBe(ExportStatus::Completed)
        ->and($export->rows_count)->toBe(2)
        ->and($export->finished_at)->not->toBeNull();

    $rows = readExportCsv($export);
    expect($rows)->toHaveCount(3)
        ->and($rows[0][1])->toBe('Title')
        ->and(array_column($rows, 0))->toBe(['ID', (string) $urgent->id, (string) $low->id])
        ->and($rows[1])->toBe([
            (string) $urgent->id, 'Fix crash', 'todo', 'urgent', $assignee->name, $assignee->email,
            '2026-10-01', 'bug', '0', $urgent->created_at->toDateTimeString(), '',
        ]);

    Notification::assertSentTo($export->user, ExportFinishedNotification::class);
});

it('escapes cells that spreadsheet apps would run as formulas', function (string $title) {
    $project = Project::factory()->create();
    Task::factory()->for($project)->create(['title' => $title]);
    $export = Export::factory()->for($project)->create();

    (new ExportProjectTasks($export))->handle();

    expect(readExportCsv($export->refresh())[1][1])->toBe("'".$title);
})->with([
    'equals' => '=HYPERLINK("http://evil.test","click")',
    'plus' => '+1+2',
    'minus' => '-2+3',
    'at' => '@SUM(A1:A2)',
]);

it('exports all tasks of the project when no filters are given', function () {
    $project = Project::factory()->create();
    Task::factory()->for($project)->count(3)->create(['status' => TaskStatus::InProgress]);
    $export = Export::factory()->for($project)->create(['filters' => null]);

    (new ExportProjectTasks($export))->handle();

    expect($export->refresh()->rows_count)->toBe(3);
});

it('marks the export as failed and notifies the author when all attempts fail', function () {
    $export = Export::factory()->create(['status' => ExportStatus::Processing]);

    (new ExportProjectTasks($export))->failed(new RuntimeException('Disk is full'));

    $export->refresh();
    expect($export->status)->toBe(ExportStatus::Failed)
        ->and($export->error)->toBe('The export could not be generated.')
        ->and($export->file_path)->toBeNull();

    Notification::assertSentTo($export->user, ExportFinishedNotification::class);
});
