<?php

namespace App\Actions\Teams;

use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use App\Services\ProjectStatistics;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RemoveTeamMember
{
    /**
     * Исключает участника (или участник выходит сам) и снимает с него задачи команды,
     * чтобы они не висели на человеке, который их больше не видит.
     *
     * @throws ValidationException
     */
    public function handle(Team $team, User $member): void
    {
        if ($member->id === $team->owner_id) {
            throw ValidationException::withMessages([
                'user' => 'The team owner cannot leave the team. Transfer ownership first.',
            ]);
        }

        DB::transaction(function () use ($team, $member): void {
            $team->members()->detach($member->id);

            $tasks = Task::query()
                ->whereBelongsTo($member, 'assignee')
                ->whereIn('project_id', $team->projects()->select('id'));

            // Массовый update идёт в обход TaskObserver — статистику проектов сбрасываем сами.
            $affectedProjects = (clone $tasks)->distinct()->pluck('project_id');

            $tasks->update(['assignee_id' => null]);

            $affectedProjects->each(fn ($projectId) => ProjectStatistics::forget((int) $projectId));
        });
    }
}
