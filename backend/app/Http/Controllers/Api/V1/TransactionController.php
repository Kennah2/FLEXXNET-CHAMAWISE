<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\JournalEntryResource;
use App\Models\JournalEntry;
use App\Models\Organization;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TransactionController extends Controller
{
    /**
     * The chama's full, auditable transaction history — the API equivalent of
     * JournalEntryController's web page (Part 29).
     */
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('view', $organization);

        $entries = JournalEntry::where('organization_id', $organization->id)
            ->with(['organization', 'postings.ledgerAccount', 'postings.user', 'creator'])
            ->orderByDesc('posted_at')
            ->paginate(25);

        return JournalEntryResource::collection($entries);
    }
}
