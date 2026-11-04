<?php

namespace Tests\Feature;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * Multi-tenancy is the core guarantee of the platform (docs/TENANCY.md): a user who belongs to
 * one chama must never be able to read or act on another chama's data, even with a direct,
 * otherwise-valid-looking URL or record id.
 */
class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    protected function createChamaWithChairperson(string $name): array
    {
        $chairperson = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => $name,
            'group_type' => 'savings',
        ]);

        return [$chairperson, Organization::firstWhere('name', $name)];
    }

    public function test_a_member_of_chama_a_cannot_view_chama_b(): void
    {
        [, $chamaA] = $this->createChamaWithChairperson('Chama A');
        [, $chamaB] = $this->createChamaWithChairperson('Chama B');

        $memberOfA = User::factory()->create(['email_verified_at' => now()]);
        ChamaMembership::create([
            'organization_id' => $chamaA->id,
            'user_id' => $memberOfA->id,
            'membership_number' => ChamaMembership::nextMembershipNumber($chamaA->id),
            'status' => ChamaMembership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($memberOfA)->get(route('organizations.show', $chamaB));

        $response->assertForbidden();
    }

    public function test_a_member_of_chama_a_cannot_list_members_of_chama_b(): void
    {
        [, $chamaA] = $this->createChamaWithChairperson('Chama A');
        [, $chamaB] = $this->createChamaWithChairperson('Chama B');

        $memberOfA = User::factory()->create(['email_verified_at' => now()]);
        ChamaMembership::create([
            'organization_id' => $chamaA->id,
            'user_id' => $memberOfA->id,
            'membership_number' => ChamaMembership::nextMembershipNumber($chamaA->id),
            'status' => ChamaMembership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $response = $this->actingAs($memberOfA)->get(route('organizations.members.index', $chamaB));

        $response->assertForbidden();
    }

    public function test_a_member_of_chama_a_cannot_change_membership_status_in_chama_b(): void
    {
        [$chairB, $chamaB] = $this->createChamaWithChairperson('Chama B');
        [, $chamaA] = $this->createChamaWithChairperson('Chama A');

        $memberOfA = User::factory()->create(['email_verified_at' => now()]);
        ChamaMembership::create([
            'organization_id' => $chamaA->id,
            'user_id' => $memberOfA->id,
            'membership_number' => ChamaMembership::nextMembershipNumber($chamaA->id),
            'status' => ChamaMembership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);

        $chairBMembership = ChamaMembership::where('organization_id', $chamaB->id)
            ->where('user_id', $chairB->id)
            ->firstOrFail();

        $response = $this->actingAs($memberOfA)->patch(
            route('organizations.members.update-status', [$chamaB, $chairBMembership]),
            ['status' => ChamaMembership::STATUS_REMOVED]
        );

        $response->assertForbidden();
        $this->assertEquals(ChamaMembership::STATUS_ACTIVE, $chairBMembership->fresh()->status);
    }

    public function test_a_user_with_no_membership_at_all_cannot_view_a_chama(): void
    {
        [, $organization] = $this->createChamaWithChairperson('Private Chama');

        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($outsider)->get(route('organizations.show', $organization));

        $response->assertForbidden();
    }

    public function test_the_same_user_has_different_roles_in_different_chamas(): void
    {
        [$userA, $chamaA] = $this->createChamaWithChairperson('Chama Where User Is Chair');
        [, $chamaB] = $this->createChamaWithChairperson('Chama Where User Is Member');

        ChamaMembership::create([
            'organization_id' => $chamaB->id,
            'user_id' => $userA->id,
            'membership_number' => ChamaMembership::nextMembershipNumber($chamaB->id),
            'status' => ChamaMembership::STATUS_ACTIVE,
            'joined_at' => now(),
        ]);
        app(PermissionRegistrar::class)->setPermissionsTeamId($chamaB->id);
        $userA->assignRole('member');

        $this->assertTrue($userA->can('update', $chamaA));
        $this->assertFalse($userA->can('update', $chamaB));
    }
}
