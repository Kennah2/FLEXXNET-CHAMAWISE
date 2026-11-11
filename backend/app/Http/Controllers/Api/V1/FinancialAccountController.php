<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreFinancialAccountRequest;
use App\Http\Requests\StoreReconciliationRequest;
use App\Http\Resources\AccountReconciliationResource;
use App\Http\Resources\FinancialAccountResource;
use App\Models\FinancialAccount;
use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Services\Accounts\FinancialAccountService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FinancialAccountController extends Controller
{
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [FinancialAccount::class, $organization]);

        $accounts = $organization->financialAccounts()->orderByDesc('is_default')->orderBy('name')->get();

        return FinancialAccountResource::collection($accounts);
    }

    public function show(Organization $organization, FinancialAccount $account): JsonResponse
    {
        abort_unless($account->organization_id === $organization->id, 404);
        $this->authorize('view', $account);

        $postings = LedgerPosting::query()
            ->where('ledger_account_id', $account->ledgerAccount?->id)
            ->with(['journalEntry'])
            ->orderByDesc('created_at')
            ->paginate(30);

        $reconciliations = $account->reconciliations()->orderByDesc('statement_date')->limit(10)->get();

        return response()->json([
            'data' => (new FinancialAccountResource($account))->toArray(request()) + [
                'postings' => $postings->through(fn (LedgerPosting $posting) => [
                    'id' => $posting->id,
                    'description' => $posting->journalEntry->description,
                    'posted_at' => $posting->journalEntry->posted_at->toIso8601String(),
                    'debit_cents' => $posting->debit_cents,
                    'credit_cents' => $posting->credit_cents,
                ]),
                'reconciliations' => AccountReconciliationResource::collection($reconciliations),
            ],
        ]);
    }

    public function store(StoreFinancialAccountRequest $request, Organization $organization, FinancialAccountService $service): JsonResponse
    {
        $account = $service->create(
            $organization,
            $request->validated('name'),
            $request->validated('type'),
            $request->validated('account_number'),
            Money::parseToCents($request->validated('opening_balance') ?? '0'),
            $request->boolean('is_default'),
            $request->user(),
        );

        return response()->json(['data' => new FinancialAccountResource($account)], 201);
    }

    public function reconcile(StoreReconciliationRequest $request, Organization $organization, FinancialAccount $account, FinancialAccountService $service): JsonResponse
    {
        $reconciliation = $service->reconcile(
            $organization,
            $account,
            $request->validated('statement_date'),
            Money::parseToCents($request->validated('statement_balance')),
            $request->validated('notes'),
            $request->user(),
        );

        return response()->json(['data' => new AccountReconciliationResource($reconciliation)], 201);
    }
}
