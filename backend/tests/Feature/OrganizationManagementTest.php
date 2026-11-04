<?php

namespace Tests\Feature;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class OrganizationManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_verified_user_can_create_a_chama(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Upendo Savings Group',
            'group_type' => 'savings',
            'currency' => 'KES',
        ]);

        $organization = Organization::firstWhere('name', 'Upendo Savings Group');

        $response->assertRedirect(route('organizations.show', $organization));
        $this->assertNotNull($organization);
        $this->assertEquals($user->id, $organization->created_by);

        $this->assertDatabaseHas('chama_memberships', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'status' => ChamaMembership::STATUS_ACTIVE,
        ]);

        $this->assertTrue($user->fresh()->hasRole('chairperson'));
    }

    public function test_an_unverified_user_cannot_create_a_chama(): void
    {
        $user = User::factory()->unverified()->create();

        $response = $this->actingAs($user)->post(route('organizations.store'), [
            'name' => 'Should Not Exist',
            'group_type' => 'savings',
        ]);

        $response->assertStatus(302);
        $this->assertDatabaseMissing('organizations', ['name' => 'Should Not Exist']);
    }

    public function test_chairperson_can_update_chama_but_ordinary_member_cannot(): void
    {
        $chairperson = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => 'Test Chama',
            'group_type' => 'savings',
        ]);
        $organization = Organization::firstWhere('name', 'Test Chama');

        $this->assertTrue($chairperson->can('update', $organization));

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

        $this->assertFalse($member->fresh()->can('update', $organization));
    }
}
