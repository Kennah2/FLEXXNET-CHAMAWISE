<?php

namespace App\Services\Ledger;

use App\Models\JournalEntry;
use App\Models\LedgerAccount;
use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * The only code path allowed to create a JournalEntry. Every posting is all-or-nothing inside a
 * database transaction, and a journal entry is refused unless its debits and credits balance
 * exactly — this is the one place that invariant is enforced, so nothing elsewhere in the
 * application can silently create unbalanced books (Part 12).
 *
 * A posted entry is never edited or deleted. Mistakes are corrected with reverse(), which posts
 * a second, mirror-image entry rather than touching the first.
 */
class LedgerPoster
{
    /**
     * @param  list<array{ledger_account_id: int, debit_cents?: int, credit_cents?: int, user_id?: int|null}>  $lines
     */
    public function post(
        Organization $organization,
        string $description,
        array $lines,
        User $actor,
        ?Model $source = null,
        ?CarbonImmutable $postedAt = null,
    ): JournalEntry {
        $this->assertBalanced($organization, $lines);

        return DB::transaction(function () use ($organization, $description, $lines, $actor, $source, $postedAt) {
            $entry = JournalEntry::create([
                'organization_id' => $organization->id,
                'description' => $description,
                'source_type' => $source?->getMorphClass(),
                'source_id' => $source?->getKey(),
                'posted_at' => $postedAt ?? CarbonImmutable::now(),
                'created_by' => $actor->id,
                'status' => JournalEntry::STATUS_POSTED,
            ]);

            foreach ($lines as $line) {
                LedgerPosting::create([
                    'journal_entry_id' => $entry->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'organization_id' => $organization->id,
                    'user_id' => $line['user_id'] ?? null,
                    'debit_cents' => $line['debit_cents'] ?? 0,
                    'credit_cents' => $line['credit_cents'] ?? 0,
                ]);
            }

            return $entry;
        });
    }

    /**
     * Posts a new entry that exactly mirrors (debit <-> credit swapped, same accounts, same
     * users) every line of $entry, and marks $entry itself as reversed for display purposes.
     * $entry's own postings are untouched — the ledger stays append-only.
     */
    public function reverse(JournalEntry $entry, User $actor, ?string $description = null): JournalEntry
    {
        if ($entry->isReversed()) {
            throw new InvalidArgumentException("Journal entry #{$entry->id} has already been reversed.");
        }

        $lines = $entry->postings->map(fn (LedgerPosting $posting) => [
            'ledger_account_id' => $posting->ledger_account_id,
            'debit_cents' => $posting->credit_cents,
            'credit_cents' => $posting->debit_cents,
            'user_id' => $posting->user_id,
        ])->all();

        return DB::transaction(function () use ($entry, $actor, $description, $lines) {
            $reversal = JournalEntry::create([
                'organization_id' => $entry->organization_id,
                'description' => $description ?? "Reversal of: {$entry->description}",
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'posted_at' => CarbonImmutable::now(),
                'created_by' => $actor->id,
                'status' => JournalEntry::STATUS_POSTED,
                'reverses_journal_entry_id' => $entry->id,
            ]);

            foreach ($lines as $line) {
                LedgerPosting::create([
                    'journal_entry_id' => $reversal->id,
                    'ledger_account_id' => $line['ledger_account_id'],
                    'organization_id' => $entry->organization_id,
                    'user_id' => $line['user_id'],
                    'debit_cents' => $line['debit_cents'],
                    'credit_cents' => $line['credit_cents'],
                ]);
            }

            $entry->update(['status' => JournalEntry::STATUS_REVERSED]);

            return $reversal;
        });
    }

    /**
     * @param  list<array{ledger_account_id: int, debit_cents?: int, credit_cents?: int, user_id?: int|null}>  $lines
     */
    private function assertBalanced(Organization $organization, array $lines): void
    {
        if (count($lines) < 2) {
            throw new InvalidArgumentException('A journal entry needs at least two lines.');
        }

        $totalDebits = 0;
        $totalCredits = 0;
        $accountIds = [];

        foreach ($lines as $line) {
            $debit = $line['debit_cents'] ?? 0;
            $credit = $line['credit_cents'] ?? 0;

            if ($debit < 0 || $credit < 0) {
                throw new InvalidArgumentException('Ledger posting amounts cannot be negative.');
            }

            if (($debit > 0) === ($credit > 0)) {
                throw new InvalidArgumentException('Each posting line must be either a debit or a credit, not both or neither.');
            }

            $totalDebits += $debit;
            $totalCredits += $credit;
            $accountIds[] = $line['ledger_account_id'];
        }

        if ($totalDebits !== $totalCredits) {
            throw new InvalidArgumentException("Journal entry does not balance: debits {$totalDebits} != credits {$totalCredits}.");
        }

        if ($totalDebits === 0) {
            throw new InvalidArgumentException('A journal entry cannot post zero value.');
        }

        $validAccountCount = LedgerAccount::where('organization_id', $organization->id)
            ->whereIn('id', array_unique($accountIds))
            ->count();

        if ($validAccountCount !== count(array_unique($accountIds))) {
            throw new InvalidArgumentException('One or more ledger accounts do not belong to this organization.');
        }
    }
}
