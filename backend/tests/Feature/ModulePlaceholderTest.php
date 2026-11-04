<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModulePlaceholderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    public function test_a_member_can_view_a_not_yet_built_module_page(): void
    {
        $chairperson = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => 'Module Test Chama',
            'group_type' => 'savings',
        ]);
        $organization = Organization::firstWhere('name', 'Module Test Chama');

        $response = $this->actingAs($chairperson)->get(route('organizations.module.show', [$organization, 'loans']));

        $response->assertOk();
        $response->assertSee('Loans');
        $response->assertSee('Phase 6');
    }

    public function test_an_unknown_module_404s(): void
    {
        $chairperson = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => 'Module 404 Chama',
            'group_type' => 'savings',
        ]);
        $organization = Organization::firstWhere('name', 'Module 404 Chama');

        $response = $this->actingAs($chairperson)->get('/chamas/'.$organization->id.'/not-a-real-module');

        $response->assertNotFound();
    }

    public function test_a_non_member_cannot_view_another_chamas_module_page(): void
    {
        $chairperson = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($chairperson)->post(route('organizations.store'), [
            'name' => 'Private Module Chama',
            'group_type' => 'savings',
        ]);
        $organization = Organization::firstWhere('name', 'Private Module Chama');

        $outsider = User::factory()->create(['email_verified_at' => now()]);

        $response = $this->actingAs($outsider)->get(route('organizations.module.show', [$organization, 'reports']));

        $response->assertForbidden();
    }
}
