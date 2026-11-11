<?php

namespace Tests\Feature\Api\V1;

use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ChamaTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_member_sees_their_own_chamas_with_their_role_in_each(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Api Chama A');
        [, $organizationB] = $this->createChamaWithChairperson('Api Chama B');

        Sanctum::actingAs($ken);

        $response = $this->getJson('/api/v1/chamas');

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('name');
        $this->assertTrue($names->contains('Api Chama A'));
        $this->assertFalse($names->contains('Api Chama B')); // Ken was never added to B himself.

        $chamaA = collect($response->json('data'))->firstWhere('name', 'Api Chama A');
        $this->assertContains('chairperson', $chamaA['my_roles']);
    }

    public function test_show_returns_the_chama_with_roles_for_an_active_member(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Chama Show');

        Sanctum::actingAs($ken);

        $response = $this->getJson("/api/v1/chamas/{$organization->id}");

        $response->assertOk();
        $response->assertJsonPath('data.id', $organization->id);
        $this->assertContains('chairperson', $response->json('data.my_roles'));
    }

    public function test_a_non_member_cannot_view_a_chama_via_the_api(): void
    {
        [, $organization] = $this->createChamaWithChairperson('Api Private Chama');
        [$outsider] = $this->createChamaWithChairperson('Api Outsider Chama');

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/chamas/{$organization->id}")->assertForbidden();
    }

    public function test_members_endpoint_lists_the_chamas_members(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Api Members Chama');
        $frank = $this->addMemberWithRole($organization, 'member');

        Sanctum::actingAs($ken);

        $response = $this->getJson("/api/v1/chamas/{$organization->id}/members");

        $response->assertOk();
        $names = collect($response->json('data'))->pluck('user.name');
        $this->assertTrue($names->contains($ken->name));
        $this->assertTrue($names->contains($frank->name));
    }

    public function test_a_member_of_another_chama_cannot_list_this_chamas_members(): void
    {
        [, $organizationA] = $this->createChamaWithChairperson('Api Members Chama A');
        [$outsider] = $this->createChamaWithChairperson('Api Members Chama B');

        Sanctum::actingAs($outsider);

        $this->getJson("/api/v1/chamas/{$organizationA->id}/members")->assertForbidden();
    }
}
