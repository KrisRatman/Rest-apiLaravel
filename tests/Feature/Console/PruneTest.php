<?php

use App\Models\Export;
use App\Models\TeamInvitation;
use Illuminate\Support\Facades\Storage;

it('prunes exports older than a week together with their files', function () {
    Storage::fake('local');
    Storage::disk('local')->put('exports/old.csv', 'old');
    Storage::disk('local')->put('exports/new.csv', 'new');
    $old = Export::factory()->completed('exports/old.csv')->create(['created_at' => now()->subDays(Export::KEEP_DAYS + 1)]);
    $fresh = Export::factory()->completed('exports/new.csv')->create();

    $this->artisan('model:prune', ['--model' => [Export::class]])->assertSuccessful();

    $this->assertModelMissing($old);
    $this->assertModelExists($fresh);
    Storage::disk('local')->assertMissing('exports/old.csv');
    Storage::disk('local')->assertExists('exports/new.csv');
});

it('prunes expired invitations', function () {
    $expired = TeamInvitation::factory()->expired()->create();
    $pending = TeamInvitation::factory()->create();

    $this->artisan('model:prune', ['--model' => [TeamInvitation::class]])->assertSuccessful();

    $this->assertModelMissing($expired);
    $this->assertModelExists($pending);
});
