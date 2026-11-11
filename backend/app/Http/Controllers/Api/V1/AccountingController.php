<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\LedgerAccountResource;
use App\Models\LedgerPosting;
use App\Models\Organization;
use App\Services\Ledger\LedgerProvisioner;
use Illuminate\Http\JsonResponse;

class AccountingController extends Controller
{
    public function index(Organization $organization, LedgerProvisioner $provisioner): JsonResponse
    {
        $this->authorize('view', $organization);

        $provisioner->provisionSystemAccounts($organization);

        $accounts = $organization->ledgerAccounts()->with('organization')->orderBy('type')->orderBy('name')->get();
        $byType = $accounts->groupBy('type')->map(fn ($group) => LedgerAccountResource::collection($group));

        $totals = LedgerPosting::where('organization_id', $organization->id)
            ->selectRaw('COALESCE(SUM(debit_cents), 0) as debits, COALESCE(SUM(credit_cents), 0) as credits')
            ->first();

        return response()->json([
            'data' => [
                'by_type' => $byType,
                'total_debits_cents' => (int) $totals->debits,
                'total_credits_cents' => (int) $totals->credits,
                'is_balanced' => (int) $totals->debits === (int) $totals->credits,
            ],
        ]);
    }
}
