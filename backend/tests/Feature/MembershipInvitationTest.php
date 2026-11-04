<?php

namespace Tests\Feature;

use App\Models\ChamaMembership;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class MembershipInvitationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    protected function createChama(): array
    {
        $chairperson = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => 'Invite Test Chama',
            'group_type' => 'savings',
        ]);

        return [$chairperson, Organization::firstWhere('name', 'Invite Test Chama')];
    }

    public function test_chairperson_can_invite_a_member_and_a_pending_invitation_is_created(): void
    {
        [$chairperson, $organization] = $this->createChama();

        $response = $this->actingAs($chairperson)->post(
            route('organizations.invitations.store', $organization),
            ['email' => 'newmember@example.test', 'role' => 'member']
        );

        $response->assertRedirect();
        $this->assertDatabaseHas('invitations', [
            'organization_id' => $organization->id,
            'email' => 'newmember@example.test',
            'role' => 'member',
            'status' => Invitation::STATUS_PENDING,
        ]);
    }

    public function test_ordinary_member_cannot_send_invitations(): void
    {
        [, $organization] = $this->createChama();

        $member = User::factory()->create(['email_verified_at' => now()]);
        ChamaMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $member->id,
            'membership_number' => ChamaMembership::nextMembershipNumber($organization->id),
            'status' => ChamaMembership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $member->assignRole('member');

        $response = $this->actingAs($member)->post(
            route('organizations.invitations.store', $organization),
            ['email' => 'someone@example.test', 'role' => 'member']
        );

        $response->assertForbidden();
    }

    public function test_invited_user_can_accept_invitation_and_becomes_active_member(): void
    {
        [$chairperson, $organization] = $this->createChama();

        $plainToken = Invitation::generateToken();
        $invitation = Invitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $chairperson->id,
            'email' => 'invitee@example.test',
            'role' => 'treasurer',
            'token' => Invitation::hashToken($plainToken),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $invitee = User::factory()->create([
            'email' => 'invitee@example.test',
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($invitee)->post(route('invitations.accept', $plainToken));

        $response->assertRedirect(route('organizations.show', $organization));

        $this->assertDatabaseHas('chama_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $invitee->id,
            'status' => ChamaMembership::STATUS_ACTIVE,
        ]);

        $this->assertEquals(Invitation::STATUS_ACCEPTED, $invitation->fresh()->status);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $this->assertTrue($invitee->fresh()->hasRole('treasurer'));
    }

    public function test_invitation_cannot_be_accepted_by_a_different_email_address(): void
    {
        [$chairperson, $organization] = $this->createChama();

        $plainToken = Invitation::generateToken();
        Invitation::create([
            'organization_id' => $organization->id,
            'invited_by' => $chairperson->id,
            'email' => 'intended@example.test',
            'role' => 'member',
            'token' => Invitation::hashToken($plainToken),
            'status' => Invitation::STATUS_PENDING,
            'expires_at' => now()->addDays(7),
        ]);

        $otherUser = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($otherUser)->post(route('invitations.accept', $plainToken));

        $this->assertDatabaseMissing('chama_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $otherUser->id,
        ]);
    }
}
