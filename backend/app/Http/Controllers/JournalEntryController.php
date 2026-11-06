<?php

namespace App\Http\Controllers;

use App\Models\JournalEntry;
use App\Models\Organization;
use Illuminate\View\View;

class JournalEntryController extends Controller
{
    /**
     * The chama's full, auditable transaction history (Part 29) — every posted journal entry,
     * newest first, with its balanced debit/credit lines.
     */
    public function index(Organization $organization): View
    {
        $this->authorize('view', $organization);

        $entries = JournalEntry::where('organization_id', $organization->id)
            ->with(['postings.ledgerAccount', 'postings.user', 'creator'])
            ->orderByDesc('posted_at')
            ->paginate(25);

        return view('organizations.transactions.index', compact('organization', 'entries'));
    }
}
