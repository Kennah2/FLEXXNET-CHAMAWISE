<?php

namespace Tests\Concerns;

use App\Models\ChamaMembership;
use App\Models\Organization;
use App\Models\User;
use Spatie\Permission\PermissionRegistrar;

/**
 * Shared helpers for feature tests that need a chama with members in specific roles. Goes
 * through the real HTTP registration flow (organizations.store) for the founding chairperson,
 * exactly as OrganizationAccessTest / OrganizationManagementTest do, so these tests exercise the
 * same code path the app actually uses rather than a parallel shortcut.
 */
trait CreatesChamas
{
    protected function createChamaWithChairperson(string $name, ?string $email = null): array
    {
        $chairperson = User::factory()->create([
            'email_verified_at' => now(),
            ...($email ? ['email' => $email] : []),
        ]);

        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => $name,
            'group_type' => 'savings',
        ]);

        return [$chairperson, Organization::firstWhere('name', $name)];
    }

    protected function addMemberWithRole(Organization $organization, string $role = 'member', ?string $email = null, ?\DateTimeInterface $joinedAt = null): User
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            ...($email ? ['email' => $email] : []),
        ]);

        ChamaMembership::create([
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'membership_number' => ChamaMembership::nextMembershipNumber($organization->id),
            'status' => ChamaMembership::STATUS_ACTIVE,
            'joined_at' => $joinedAt ?? now(),
        ]);

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $user->assignRole($role);

        return $user;
    }
}
