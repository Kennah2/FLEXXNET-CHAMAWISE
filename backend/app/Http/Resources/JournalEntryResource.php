<?php

namespace App\Http\Resources;

use App\Models\JournalEntry;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin JournalEntry
 */
class JournalEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'description' => $this->description,
            'posted_at' => $this->posted_at?->toIso8601String(),
            'status' => $this->status,
            'reverses_journal_entry_id' => $this->reverses_journal_entry_id,
            'created_by' => $this->whenLoaded('creator', fn () => $this->creator->name),
            'postings' => $this->whenLoaded('postings', fn () => $this->postings->map(fn ($posting) => [
                'ledger_account_id' => $posting->ledger_account_id,
                'ledger_account_name' => $posting->ledgerAccount?->name,
                'user' => $posting->user_id ? new UserResource($posting->user) : null,
                'debit_cents' => $posting->debit_cents,
                'credit_cents' => $posting->credit_cents,
                'amount_formatted' => Money::format(max($posting->debit_cents, $posting->credit_cents), $this->organization->currency),
            ])),
        ];
    }
}
