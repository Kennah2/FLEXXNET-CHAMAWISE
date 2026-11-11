<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWithdrawalRequestRequest;
use App\Http\Resources\WithdrawalRequestResource;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Withdrawals\WithdrawalService;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class WithdrawalController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', [WithdrawalRequest::class, $organization]);

        $user = request()->user();

        $myRequests = WithdrawalRequest::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->with(['financialAccount', 'approvals'])
            ->orderByDesc('created_at')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $canManageFinances = User::find($user->id)->can(ChamaPermissions::MANAGE_FINANCES);

        $pendingApprovals = collect();
        $readyToPay = collect();
        if ($canManageFinances) {
            $pendingApprovals = WithdrawalRequest::where('organization_id', $organization->id)
                ->where('status', WithdrawalRequest::STATUS_PENDING)
                ->with(['user', 'financialAccount', 'approvals'])
                ->orderBy('created_at')
                ->get();

            $readyToPay = WithdrawalRequest::where('organization_id', $organization->id)
                ->where('status', WithdrawalRequest::STATUS_APPROVED)
                ->with(['user', 'financialAccount'])
                ->orderBy('created_at')
                ->get();
        }

        $savingsAccount = $organization->memberSavingsAccount();

        return response()->json([
            'data' => [
                'my_balance_cents' => $savingsAccount?->balanceCents($user->id) ?? 0,
                'my_requests' => WithdrawalRequestResource::collection($myRequests),
                'pending_approvals' => WithdrawalRequestResource::collection($pendingApprovals),
                'ready_to_pay' => WithdrawalRequestResource::collection($readyToPay),
            ],
        ]);
    }

    public function store(StoreWithdrawalRequestRequest $request, Organization $organization, WithdrawalService $service): JsonResponse
    {
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $withdrawal = $service->request(
            $organization,
            $account,
            Money::parseToCents($request->validated('amount')),
            $request->validated('reason'),
            $request->user(),
        );

        return response()->json(['data' => new WithdrawalRequestResource($withdrawal)], 201);
    }

    public function approve(Request $request, Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): JsonResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('approve', $withdrawal);

        $withdrawal = $service->approve($organization, $withdrawal, $request->user());

        return response()->json(['data' => new WithdrawalRequestResource($withdrawal)]);
    }

    public function reject(Request $request, Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): JsonResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('approve', $withdrawal);

        $withdrawal = $service->reject($organization, $withdrawal, $request->input('reason'), $request->user());

        return response()->json(['data' => new WithdrawalRequestResource($withdrawal)]);
    }

    public function pay(Request $request, Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): JsonResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('pay', $withdrawal);

        $withdrawal = $service->pay($organization, $withdrawal, $request->user());

        return response()->json(['data' => new WithdrawalRequestResource($withdrawal)]);
    }

    public function cancel(Request $request, Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): JsonResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('cancel', $withdrawal);

        $withdrawal = $service->cancel($organization, $withdrawal, $request->user());

        return response()->json(['data' => new WithdrawalRequestResource($withdrawal)]);
    }

    public function reverse(Request $request, Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): JsonResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('reverse', $withdrawal);

        $withdrawal = $service->reverse($organization, $withdrawal, $request->user());

        return response()->json(['data' => new WithdrawalRequestResource($withdrawal)]);
    }
}
