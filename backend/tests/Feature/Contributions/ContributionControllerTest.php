<?php

namespace Tests\Feature\Contributions;

use App\Models\Contribution;
use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Services\Contributions\ObligationGenerator;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ContributionControllerTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
    }

    private function dailySchedule(int $organizationId, int $createdBy, int $penaltyCents = 0): ContributionSchedule
    {
        return ContributionSchedule::create([
            'organization_id' => $organizationId,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => now()->subDays(2)->toDateString(),
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => $penaltyCents,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $createdBy,
        ]);
    }

    public function test_a_member_can_record_their_own_contribution(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Record Own Chama');
        $this->dailySchedule($organization->id, $ken->id);

        $response = $this->actingAs($ken)->post(route('organizations.contributions.store', $organization), [
            'amount' => '100.00',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contributions', [
            'organization_id' => $organization->id,
            'user_id' => $ken->id,
            'amount_cents' => 10000,
            'status' => Contribution::STATUS_PENDING_VERIFICATION,
        ]);
    }

    public function test_a_member_cannot_record_a_contribution_for_another_member_without_manage_finances(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Cannot Record For Others Chama');
        $member = $this->addMemberWithRole($organization, 'member');

        $response = $this->actingAs($member)->post(route('organizations.contributions.store', $organization), [
            'user_id' => $ken->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertForbidden();
    }

    public function test_a_treasurer_can_record_a_contribution_on_behalf_of_another_member(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Treasurer Records For Others Chama');
        $treasurer = $this->addMemberWithRole($organization, 'treasurer');

        $response = $this->actingAs($treasurer)->post(route('organizations.contributions.store', $organization), [
            'user_id' => $ken->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);

        $response->assertRedirect();
        $this->assertDatabaseHas('contributions', ['user_id' => $ken->id, 'recorded_by' => $treasurer->id]);
    }

    public function test_verifying_a_contribution_allocates_it_and_an_ordinary_member_cannot_verify(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Verify Chama');
        $schedule = $this->dailySchedule($organization->id, $ken->id);
        $member = $this->addMemberWithRole($organization, 'member');

        $this->actingAs($member)->post(route('organizations.contributions.store', $organization), [
            'contribution_schedule_id' => $schedule->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $contribution = Contribution::firstWhere('user_id', $member->id);

        // The member who made the payment cannot verify their own payment.
        $forbidden = $this->actingAs($member)->post(route('organizations.contributions.verify', [$organization, $contribution]));
        $forbidden->assertForbidden();

        $response = $this->actingAs($ken)->post(route('organizations.contributions.verify', [$organization, $contribution]));
        $response->assertRedirect();

        $contribution->refresh();
        $this->assertSame(Contribution::STATUS_VERIFIED, $contribution->status);
        $this->assertSame($ken->id, $contribution->verified_by);
        $this->assertGreaterThan(0, $contribution->allocations()->count());
    }

    public function test_rejecting_a_contribution_does_not_allocate_it(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reject Chama');
        $schedule = $this->dailySchedule($organization->id, $ken->id);
        app(ObligationGenerator::class)->generate($schedule);

        $this->actingAs($ken)->post(route('organizations.contributions.store', $organization), [
            'contribution_schedule_id' => $schedule->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $contribution = Contribution::firstWhere('user_id', $ken->id);

        $response = $this->actingAs($ken)->post(route('organizations.contributions.reject', [$organization, $contribution]));
        $response->assertRedirect();

        $contribution->refresh();
        $this->assertSame(Contribution::STATUS_REJECTED, $contribution->status);
        $this->assertSame(0, $contribution->allocations()->count());

        $obligation = ContributionObligation::where('contribution_schedule_id', $schedule->id)->where('user_id', $ken->id)->first();
        $this->assertSame(0, $obligation->amount_paid_cents);
    }

    public function test_verifying_a_second_contribution_with_a_duplicate_transaction_reference_is_rejected(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Duplicate Reference Chama');
        $schedule = $this->dailySchedule($organization->id, $ken->id);

        $this->actingAs($ken)->post(route('organizations.contributions.store', $organization), [
            'contribution_schedule_id' => $schedule->id,
            'amount' => '100.00',
            'payment_method' => 'manual_mpesa',
            'transaction_reference' => 'QGH7XYZ123',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $first = Contribution::firstWhere('transaction_reference', 'QGH7XYZ123');
        $this->actingAs($ken)->post(route('organizations.contributions.verify', [$organization, $first]));

        $this->actingAs($ken)->post(route('organizations.contributions.store', $organization), [
            'contribution_schedule_id' => $schedule->id,
            'amount' => '100.00',
            'payment_method' => 'manual_mpesa',
            'transaction_reference' => 'QGH7XYZ123',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $second = Contribution::where('transaction_reference', 'QGH7XYZ123')->where('id', '!=', $first->id)->first();

        $response = $this->actingAs($ken)->post(route('organizations.contributions.verify', [$organization, $second]));
        $response->assertStatus(422);

        $second->refresh();
        $this->assertSame(Contribution::STATUS_PENDING_VERIFICATION, $second->status);
    }

    public function test_reversing_a_verified_contribution_restores_the_obligation(): void
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Reverse Chama');
        $schedule = $this->dailySchedule($organization->id, $ken->id);

        $this->actingAs($ken)->post(route('organizations.contributions.store', $organization), [
            'contribution_schedule_id' => $schedule->id,
            'amount' => '100.00',
            'payment_method' => 'cash',
            'paid_at' => now()->format('Y-m-d\TH:i'),
        ]);
        $contribution = Contribution::firstWhere('user_id', $ken->id);
        $this->actingAs($ken)->post(route('organizations.contributions.verify', [$organization, $contribution]));

        $response = $this->actingAs($ken)->post(route('organizations.contributions.reverse', [$organization, $contribution]));
        $response->assertRedirect();

        $contribution->refresh();
        $this->assertSame(Contribution::STATUS_REVERSED, $contribution->status);

        $allocation = $contribution->allocations()->first();
        $this->assertNotNull($allocation->reversed_at);
    }

    public function test_a_member_of_another_chama_cannot_view_this_chamas_contributions_page(): void
    {
        [$ken, $organizationA] = $this->createChamaWithChairperson('Contrib Chama A');
        [, $organizationB] = $this->createChamaWithChairperson('Contrib Chama B');
        $memberOfB = $this->addMemberWithRole($organizationB, 'member');

        $response = $this->actingAs($memberOfB)->get(route('organizations.contributions.index', $organizationA));

        $response->assertForbidden();
    }
}
