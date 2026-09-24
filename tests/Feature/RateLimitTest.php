<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

it('reports the limit and the remaining requests in headers', function () {
    signIn();

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 120)
        ->assertHeader('X-RateLimit-Remaining', 119);
});

it('returns 429 with Retry-After when a user exceeds the api limit', function () {
    config(['api.rate_limits.api' => 3]);
    signIn();

    foreach (range(1, 3) as $request) {
        $this->getJson('/api/v1/me')->assertOk();
    }

    $this->getJson('/api/v1/me')
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertExactJson(['message' => 'Too Many Attempts.']);
});

it('counts requests separately for each user', function () {
    config(['api.rate_limits.api' => 2]);
    $first = User::factory()->create();
    $second = User::factory()->create();

    signIn($first);
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertOk();
    $this->getJson('/api/v1/me')->assertTooManyRequests();

    signIn($second);
    $this->getJson('/api/v1/me')->assertOk();
});

it('applies the lower guest limit to public endpoints', function () {
    config(['api.rate_limits.guest' => 2]);
    $payload = ['email' => 'nobody@example.com', 'password' => 'wrong-password'];

    $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', $payload)->assertUnprocessable();
    $this->postJson('/api/v1/auth/login', $payload)
        ->assertTooManyRequests()
        ->assertHeader('X-RateLimit-Limit', 2);
});

it('limits export requests separately', function () {
    Queue::fake();
    config(['api.rate_limits.exports' => 2]);
    $project = Project::factory()->create();
    signIn($project->team->owner);

    $this->postJson("/api/v1/projects/{$project->id}/exports")->assertAccepted();
    $this->postJson("/api/v1/projects/{$project->id}/exports")->assertAccepted();
    $this->postJson("/api/v1/projects/{$project->id}/exports")->assertTooManyRequests();

    $this->getJson("/api/v1/projects/{$project->id}/tasks")->assertOk();
});
