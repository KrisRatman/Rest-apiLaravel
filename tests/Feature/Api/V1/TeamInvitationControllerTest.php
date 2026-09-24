<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;

describe('index', function () {
    it('lists invitations of the team for an admin', function () {
        $team = Team::factory()->create();
        $pending = TeamInvitation::factory()->for($team)->create();
        $expired = TeamInvitation::factory()->for($team)->expired()->create();
        TeamInvitation::factory()->create();
        signIn(memberOf($team, TeamRole::Admin));

        $this->getJson("/api/v1/teams/{$team->id}/invitations")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonFragment(['id' => $pending->id, 'is_expired' => false])
            ->assertJsonFragment(['id' => $expired->id, 'is_expired' => true])
            ->assertJsonMissingPath('data.0.token_hash');
    });

    it('returns 403 to a member', function () {
        $team = Team::factory()->create();
        signIn(memberOf($team));

        $this->getJson("/api/v1/teams/{$team->id}/invitations")->assertForbidden();
    });
});

describe('store', function () {
    it('creates an invitation and queues an email with the token', function () {
        Notification::fake();
        $team = Team::factory()->create();
        $admin = signIn(memberOf($team, TeamRole::Admin));

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => 'New.Person@Example.com', 'role' => 'admin'])
            ->assertCreated()
            ->assertJsonPath('data.email', 'new.person@example.com')
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonPath('data.invited_by', $admin->id);

        $invitation = $team->invitations()->sole();
        Notification::assertSentOnDemand(
            TeamInvitationNotification::class,
            function (TeamInvitationNotification $notification, array $channels, AnonymousNotifiable $notifiable) use ($invitation) {
                return $notifiable->routes['mail'] === 'new.person@example.com'
                    && $notification->invitation->is($invitation)
                    && TeamInvitation::hashToken($notification->token) === $invitation->token_hash;
            },
        );
    });

    it('replaces an expired invitation for the same email', function () {
        Notification::fake();
        $team = Team::factory()->create();
        $expired = TeamInvitation::factory()->for($team)->expired()->create(['email' => 'late@example.com']);
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => 'late@example.com', 'role' => 'member'])
            ->assertCreated();

        $this->assertModelMissing($expired);
        expect($team->invitations()->sole()->isExpired())->toBeFalse();
    });

    it('returns 422 when a pending invitation exists', function () {
        Notification::fake();
        $team = Team::factory()->create();
        TeamInvitation::factory()->for($team)->create(['email' => 'twice@example.com']);
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => 'twice@example.com', 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This email has already been invited.']);

        Notification::assertNothingSent();
    });

    it('returns 422 when the email belongs to a member', function () {
        Notification::fake();
        $team = Team::factory()->create();
        $member = memberOf($team);
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => $member->email, 'role' => 'member'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'This user is already a member of the team.']);
    });

    it('returns 422 for the owner role', function () {
        $team = Team::factory()->create();
        signIn($team->owner);

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => 'boss@example.com', 'role' => 'owner'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['role' => 'The selected role is invalid.']);
    });

    it('returns 403 to a member', function () {
        Notification::fake();
        $team = Team::factory()->create();
        signIn(memberOf($team));

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => 'friend@example.com', 'role' => 'member'])
            ->assertForbidden();

        Notification::assertNothingSent();
    });

    it('returns 429 after 20 invitations per minute', function () {
        Notification::fake();
        $team = Team::factory()->create();
        signIn($team->owner);

        foreach (range(1, 20) as $i) {
            $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => "person{$i}@example.com", 'role' => 'member'])
                ->assertCreated();
        }

        $this->postJson("/api/v1/teams/{$team->id}/invitations", ['email' => 'one.more@example.com', 'role' => 'member'])
            ->assertTooManyRequests();
    });
});

describe('destroy', function () {
    it('revokes the invitation', function () {
        $invitation = TeamInvitation::factory()->create();
        signIn($invitation->team->owner);

        $this->deleteJson("/api/v1/invitations/{$invitation->id}")->assertNoContent();

        $this->assertModelMissing($invitation);
    });

    it('returns 404 to a user from another team', function () {
        $invitation = TeamInvitation::factory()->create();
        signIn(Team::factory()->create()->owner);

        $this->deleteJson("/api/v1/invitations/{$invitation->id}")->assertNotFound();

        $this->assertModelExists($invitation);
    });
});
