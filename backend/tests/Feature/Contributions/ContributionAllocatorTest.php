<?php

namespace Tests\Feature\Contributions;

use App\Models\Contribution;
use App\Models\ContributionObligation;
use App\Models\ContributionSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Services\Contributions\ContributionAllocator;
use App\Services\Contributions\ObligationGenerator;
use Carbon\CarbonImmutable;
use Database\Seeders\PermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesChamas;
use Tests\TestCase;

class ContributionAllocatorTest extends TestCase
{
    use CreatesChamas, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PermissionSeeder::class);
        Carbon::setTestNow(CarbonImmutable::parse('2025-12-20 00:00:00', 'Africa/Nairobi'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @return array{0: User, 1: Organization, 2: ContributionSchedule}
     */
    private function chamaWithThreeDaysOfObligations(): array
    {
        [$ken, $organization] = $this->createChamaWithChairperson('Allocator Test Chama');

        $schedule = ContributionSchedule::create([
            'organization_id' => $organization->id,
            'name' => 'Daily savings',
            'frequency' => ContributionSchedule::FREQUENCY_DAILY,
            'currency' => 'KES',
            'amount_cents' => 10000,
            'start_date' => '2026-01-01',
            'deadline_time' => '23:59',
            'timezone' => 'Africa/Nairobi',
            'grace_period_days' => 0,
            'penalty_amount_cents' => 0,
            'applies_to' => ContributionSchedule::APPLIES_TO_ALL,
            'status' => ContributionSchedule::STATUS_ACTIVE,
            'created_by' => $ken->id,
        ]);

        app(ObligationGenerator::class)->generate($schedule, CarbonImmutable::parse('2026-01-03 10:00:00', 'Africa/Nairobi'));

        return [$ken, $organization, $schedule];
    }

    private function verifiedContribution(int $organizationId, int $userId, int $amountCents, ?int $scheduleId = null): Contribution
    {
        return Contribution::create([
            'organization_id' => $organizationId,
            'user_id' => $userId,
            'contribution_schedule_id' => $scheduleId,
            'amount_cents' => $amountCents,
            'currency' => 'KES',
            'payment_method' => Contribution::METHOD_CASH,
            'paid_at' => now(),
            'recorded_by' => $userId,
            'status' => Contribution::STATUS_VERIFIED,
            'verified_by' => $userId,
            'verified_at' => now(),
        ]);
    }

    public function test_allocates_a_payment_across_obligations_oldest_first(): void
    {
        [$ken, $organization, $schedule] = $this->chamaWithThreeDaysOfObligations();

        // 2.5 days' worth: should fully pay Jan 1 and Jan 2, half-pay Jan 3.
        $contribution = $this->verifiedContribution($organization->id, $ken->id, 25000, $schedule->id);
        app(ContributionAllocator::class)->allocate($contribution);

        $obligations = ContributionObligation::where('contribution_schedule_id', $schedule->id)
            ->where('user_id', $ken->id)
            ->orderBy('period_start')
            ->get();

        [$jan1, $jan2, $jan3] = $obligations;

        $this->assertSame(10000, $jan1->amount_paid_cents);
        $this->assertSame(ContributionObligation::STATUS_PAID, $jan1->status);

        $this->assertSame(10000, $jan2->amount_paid_cents);
        $this->assertSame(ContributionObligation::STATUS_PAID, $jan2->status);

        $this->assertSame(5000, $jan3->amount_paid_cents);
        $this->assertSame(ContributionObligation::STATUS_PARTIAL, $jan3->status);

        $this->assertSame(25000, $contribution->allocations()->sum('amount_cents'));
    }

    public function test_overpayment_leaves_a_surplus_unallocated_rather_than_invented_future_credit(): void
    {
        [$ken, $organization, $schedule] = $this->chamaWithThreeDaysOfObligations();

        // Total outstanding across all 3 days is 30000; pay 40000.
        $contribution = $this->verifiedContribution($organization->id, $ken->id, 40000, $schedule->id);
        app(ContributionAllocator::class)->allocate($contribution);

        $totalAllocated = $contribution->allocations()->sum('amount_cents');
        $this->assertSame(30000, $totalAllocated);
        $this->assertSame(40000, $contribution->amount_cents);

        $obligations = ContributionObligation::where('contribution_schedule_id', $schedule->id)->where('user_id', $ken->id)->get();
        foreach ($obligations as $obligation) {
            $this->assertTrue($obligation->isFullyPaid());
            $this->assertSame(0, $obligation->outstandingCents());
        }
    }

    public function test_reversing_a_contribution_restores_the_obligations_it_paid_and_keeps_the_allocation_rows(): void
    {
        [$ken, $organization, $schedule] = $this->chamaWithThreeDaysOfObligations();

        $contribution = $this->verifiedContribution($organization->id, $ken->id, 10000, $schedule->id);
        $allocator = app(ContributionAllocator::class);
        $allocator->allocate($contribution);

        $jan1 = ContributionObligation::where('contribution_schedule_id', $schedule->id)
            ->where('user_id', $ken->id)
            ->orderBy('period_start')
            ->first();
        $this->assertSame(10000, $jan1->amount_paid_cents);
        $this->assertSame(ContributionObligation::STATUS_PAID, $jan1->status);

        // Simulate the reversal happening on the same day generation did (Jan 3) — our frozen
        // "now" otherwise sits in December, before the schedule even starts, which would make
        // every obligation legitimately "not due yet" rather than overdue.
        Carbon::setTestNow(CarbonImmutable::parse('2026-01-03 10:00:00', 'Africa/Nairobi'));
        $allocator->reverse($contribution);

        $jan1->refresh();
        $this->assertSame(0, $jan1->amount_paid_cents);
        $this->assertSame(ContributionObligation::STATUS_OVERDUE, $jan1->status);

        $allocation = $contribution->allocations()->first();
        $this->assertNotNull($allocation->reversed_at);
        $this->assertDatabaseHas('contribution_allocations', ['id' => $allocation->id]);
    }

    public function test_an_unscheduled_contribution_allocates_across_any_of_the_members_outstanding_obligations(): void
    {
        [$ken, $organization, $schedule] = $this->chamaWithThreeDaysOfObligations();

        // No contribution_schedule_id — a general payment, should still find the member's
        // outstanding obligations in this organization.
        $contribution = $this->verifiedContribution($organization->id, $ken->id, 10000, null);
        app(ContributionAllocator::class)->allocate($contribution);

        $jan1 = ContributionObligation::where('contribution_schedule_id', $schedule->id)
            ->where('user_id', $ken->id)
            ->orderBy('period_start')
            ->first();

        $this->assertSame(10000, $jan1->amount_paid_cents);
    }
}
