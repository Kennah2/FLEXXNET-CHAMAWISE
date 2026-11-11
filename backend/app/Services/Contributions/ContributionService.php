<?php

namespace App\Services\Contributions;

use App\Models\Contribution;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Ledger\LedgerPoster;
use App\Services\Ledger\LedgerProvisioner;
use Carbon\CarbonImmutable;
use DateTimeInterface;

/**
 * Every write action on a Contribution, shared between the web controller and the v1 API
 * controller (Part 15: the API must never duplicate business/financial logic rather than calling
 * the same code the web app uses) — recording, verifying (which posts the ledger and allocates
 * against obligations), rejecting, and reversing.
 */
class ContributionService
{
    public function __construct(
        private readonly ContributionAllocator $allocator,
        private readonly ObligationGenerator $generator,
        private readonly LedgerPoster $poster,
        private readonly LedgerProvisioner $ledgerProvisioner,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function record(
        Organization $organization,
        int $forUserId,
        ?int $contributionScheduleId,
        int $amountCents,
        string $paymentMethod,
        ?string $transactionReference,
        string|DateTimeInterface $paidAt,
        ?string $notes,
        User $actor,
    ): Contribution {
        $contribution = Contribution::create([
            'organization_id' => $organization->id,
            'user_id' => $forUserId,
            'contribution_schedule_id' => $contributionScheduleId,
            'amount_cents' => $amountCents,
            'currency' => $organization->currency,
            'payment_method' => $paymentMethod,
            'transaction_reference' => $transactionReference,
            'paid_at' => $paidAt,
            'recorded_by' => $actor->id,
            'status' => Contribution::STATUS_PENDING_VERIFICATION,
            'notes' => $notes,
        ]);

        $this->auditLogger->log(
            'contribution.recorded',
            $contribution,
            organizationId: $organization->id,
            userId: $actor->id,
            after: $contribution->only(['user_id', 'amount_cents', 'payment_method']),
        );

        return $contribution;
    }

    public function verify(Organization $organization, Contribution $contribution, int $financialAccountId, User $actor): Contribution
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        abort_unless($contribution->status === Contribution::STATUS_PENDING_VERIFICATION, 422, 'Only a pending contribution can be verified.');

        $this->ledgerProvisioner->provisionSystemAccounts($organization);

        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($financialAccountId);
        $this->ledgerProvisioner->provisionForFinancialAccount($account);

        // Obligations are otherwise only generated lazily when someone views the contributions
        // page/endpoint — verifying a payment must not depend on that having happened recently,
        // so make sure the schedule's obligations are current before allocating against them.
        if ($contribution->contribution_schedule_id) {
            $this->generator->generate($contribution->schedule, CarbonImmutable::now());
        }

        if ($contribution->transaction_reference) {
            $duplicate = Contribution::query()
                ->where('organization_id', $organization->id)
                ->where('transaction_reference', $contribution->transaction_reference)
                ->where('status', Contribution::STATUS_VERIFIED)
                ->where('id', '!=', $contribution->id)
                ->exists();

            abort_if($duplicate, 422, 'This transaction reference has already been verified on another contribution.');
        }

        $savingsAccount = $organization->memberSavingsAccount();

        $entry = $this->poster->post(
            $organization,
            "Contribution verified: {$contribution->user->name}",
            [
                ['ledger_account_id' => $account->ledgerAccount->id, 'debit_cents' => $contribution->amount_cents],
                ['ledger_account_id' => $savingsAccount->id, 'credit_cents' => $contribution->amount_cents, 'user_id' => $contribution->user_id],
            ],
            $actor,
            source: $contribution,
        );

        $contribution->status = Contribution::STATUS_VERIFIED;
        $contribution->verified_by = $actor->id;
        $contribution->verified_at = now();
        $contribution->financial_account_id = $account->id;
        $contribution->journal_entry_id = $entry->id;
        $contribution->save();

        $this->allocator->allocate($contribution);

        $this->auditLogger->log('contribution.verified', $contribution, organizationId: $organization->id, userId: $actor->id);

        return $contribution;
    }

    public function reject(Organization $organization, Contribution $contribution, User $actor): Contribution
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        abort_unless($contribution->status === Contribution::STATUS_PENDING_VERIFICATION, 422, 'Only a pending contribution can be rejected.');

        $contribution->status = Contribution::STATUS_REJECTED;
        $contribution->verified_by = $actor->id;
        $contribution->verified_at = now();
        $contribution->save();

        $this->auditLogger->log('contribution.rejected', $contribution, organizationId: $organization->id, userId: $actor->id);

        return $contribution;
    }

    public function reverse(Organization $organization, Contribution $contribution, User $actor): Contribution
    {
        abort_unless($contribution->organization_id === $organization->id, 404);
        abort_unless($contribution->status === Contribution::STATUS_VERIFIED, 422, 'Only a verified contribution can be reversed.');

        $this->allocator->reverse($contribution);

        if ($contribution->journal_entry_id) {
            $this->poster->reverse($contribution->journalEntry, $actor, "Reversal of contribution: {$contribution->user->name}");
        }

        $contribution->status = Contribution::STATUS_REVERSED;
        $contribution->save();

        $this->auditLogger->log('contribution.reversed', $contribution, organizationId: $organization->id, userId: $actor->id);

        return $contribution;
    }
}
