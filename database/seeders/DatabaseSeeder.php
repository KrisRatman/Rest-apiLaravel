<?php

namespace Database\Seeders;

use App\Actions\Teams\CreateTeam;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Enums\TeamRole;
use App\Models\Comment;
use App\Models\Label;
use App\Models\Project;
use App\Models\Task;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Демо-данные: вход demo@example.com / password.
 */
class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(CreateTeam $createTeam): void
    {
        // Контейнер запускает сидер при каждом старте: наполняем только пустую базу.
        if (User::where('email', 'demo@example.com')->exists()) {
            return;
        }

        $demo = User::factory()->create(['name' => 'Demo User', 'email' => 'demo@example.com']);
        $ivan = User::factory()->create(['name' => 'Ivan Petrov', 'email' => 'ivan@example.com']);
        $maria = User::factory()->create(['name' => 'Maria Sokolova', 'email' => 'maria@example.com']);
        $alex = User::factory()->create(['name' => 'Alex Kim', 'email' => 'alex@example.com']);

        $mobile = $createTeam->handle($demo, [
            'name' => 'Mobile Team',
            'description' => 'iOS and Android apps of the online store',
        ]);
        $mobile->members()->attach($ivan, ['role' => TeamRole::Admin]);
        $mobile->members()->attach($maria, ['role' => TeamRole::Member]);

        $marketing = $createTeam->handle($alex, [
            'name' => 'Marketing',
            'description' => 'Promo campaigns and landing pages',
        ]);
        $marketing->members()->attach($demo, ['role' => TeamRole::Member]);

        $this->seedMobileTeam($mobile, [$demo, $ivan, $maria]);
        $this->seedMarketingTeam($marketing, [$alex, $demo]);
    }

    /**
     * @param  list<User>  $people
     */
    private function seedMobileTeam(Team $team, array $people): void
    {
        [$demo, $ivan, $maria] = $people;

        $labels = collect([
            'bug' => '#E5484D',
            'feature' => '#30A46C',
            'design' => '#8E4EC6',
            'backend' => '#0090FF',
        ])->map(fn (string $color, string $name) => Label::factory()->for($team)->create(compact('name', 'color')));

        $release = Project::factory()->for($team)->create([
            'name' => 'Release 2.0',
            'description' => 'New cart, Apple Pay and push notifications',
            'created_by' => $demo->id,
        ]);

        $tasks = [
            ['Design the new cart screen', TaskStatus::Done, TaskPriority::High, $maria, -10, ['design']],
            ['Implement cart API client', TaskStatus::Review, TaskPriority::High, $ivan, 2, ['feature']],
            ['Apple Pay integration', TaskStatus::InProgress, TaskPriority::Urgent, $demo, 5, ['feature', 'backend']],
            ['Push notifications for order status', TaskStatus::Todo, TaskPriority::Medium, $ivan, 12, ['feature']],
            ['Crash on empty wishlist', TaskStatus::InProgress, TaskPriority::Urgent, $demo, -2, ['bug']],
            ['Wrong currency symbol in receipts', TaskStatus::Todo, TaskPriority::Low, $maria, -1, ['bug']],
            ['Update onboarding illustrations', TaskStatus::Todo, TaskPriority::Low, null, null, ['design']],
            ['Dark theme for checkout', TaskStatus::Todo, TaskPriority::Medium, $maria, 20, ['design', 'feature']],
        ];

        foreach ($tasks as [$title, $status, $priority, $assignee, $dueInDays, $taskLabels]) {
            $task = Task::factory()->for($release)->create([
                'title' => $title,
                'status' => $status,
                'priority' => $priority,
                'assignee_id' => $assignee?->id,
                'creator_id' => $demo->id,
                'due_date' => $dueInDays === null ? null : today()->addDays($dueInDays),
                'completed_at' => $status === TaskStatus::Done ? now()->subDays(8) : null,
            ]);
            $task->labels()->attach($labels->only($taskLabels)->pluck('id'));
        }

        $applePay = $release->tasks()->where('title', 'Apple Pay integration')->sole();
        Comment::factory()->for($applePay)->for($ivan, 'author')->create(['body' => 'Merchant ID is ready, keys are in the vault.']);
        Comment::factory()->for($applePay)->for($demo, 'author')->create(['body' => 'Thanks! Sandbox payments already work.']);

        $legacy = Project::factory()->for($team)->archived()->create([
            'name' => 'Release 1.5',
            'description' => 'Previous release, kept for history',
            'created_by' => $demo->id,
        ]);
        Task::factory()->for($legacy)->done()->count(3)->create([
            'creator_id' => $ivan->id,
            'completed_at' => now()->subMonth(),
        ]);
    }

    /**
     * @param  list<User>  $people
     */
    private function seedMarketingTeam(Team $team, array $people): void
    {
        [$alex, $demo] = $people;

        $campaign = Project::factory()->for($team)->create([
            'name' => 'Black Friday',
            'description' => 'Landing page and email campaign',
            'created_by' => $alex->id,
        ]);

        Task::factory()->for($campaign)->create([
            'title' => 'Prepare banners for the app',
            'status' => TaskStatus::InProgress,
            'priority' => TaskPriority::High,
            'assignee_id' => $demo->id,
            'creator_id' => $alex->id,
            'due_date' => today()->addDays(7),
        ]);
        Task::factory()->for($campaign)->create([
            'title' => 'Write the email copy',
            'priority' => TaskPriority::Medium,
            'assignee_id' => $alex->id,
            'creator_id' => $alex->id,
            'due_date' => today()->addDays(10),
        ]);
    }
}
