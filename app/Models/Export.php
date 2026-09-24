<?php

namespace App\Models;

use App\Enums\ExportStatus;
use Database\Factories\ExportFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Prunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * Выгрузка задач проекта в CSV. Файл собирает фоновый job ExportProjectTasks.
 */
#[Fillable(['filters'])]
class Export extends Model
{
    /** @use HasFactory<ExportFactory> */
    use HasFactory, Prunable;

    /**
     * Через сколько дней выгрузка и её файл удаляются.
     */
    public const KEEP_DAYS = 7;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'pending',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ExportStatus::class,
            'filters' => 'array',
            'finished_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === ExportStatus::Completed;
    }

    public function fileName(): string
    {
        return "project-{$this->project_id}-tasks-{$this->id}.csv";
    }

    /**
     * @return Builder<Export>
     */
    public function prunable(): Builder
    {
        return static::where('created_at', '<', now()->subDays(self::KEEP_DAYS));
    }

    /**
     * Вместе с записью удаляем и файл (обычный Prunable вызывает этот хук для каждой модели).
     */
    protected function pruning(): void
    {
        if ($this->file_path !== null) {
            Storage::disk('local')->delete($this->file_path);
        }
    }
}
