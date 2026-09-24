<?php

namespace Database\Factories;

use App\Enums\ExportStatus;
use App\Models\Export;
use App\Models\Project;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Export>
 */
class ExportFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'user_id' => fn (array $attributes) => Project::find($attributes['project_id'])?->team->owner_id,
            'status' => ExportStatus::Pending,
            'filters' => [],
        ];
    }

    /**
     * Готовая выгрузка; сам файл тест кладёт в Storage::fake() по этому пути.
     */
    public function completed(string $path = 'exports/test.csv'): static
    {
        return $this->state(fn () => [
            'status' => ExportStatus::Completed,
            'file_path' => $path,
            'rows_count' => 1,
            'finished_at' => now(),
        ]);
    }
}
