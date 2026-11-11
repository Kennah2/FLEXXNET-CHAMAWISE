<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWithdrawalRequestRequest;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Models\User;
use App\Models\WithdrawalRequest;
use App\Services\Withdrawals\WithdrawalService;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class WithdrawalController extends Controller
{
    public function index(Organization $organization): View
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

            // Every org-wide approved-but-unpaid request, not just the current user's own — a
            // treasurer must be able to pay out withdrawals they didn't personally request.
            $readyToPay = WithdrawalRequest::where('organization_id', $organization->id)
                ->where('status', WithdrawalRequest::STATUS_APPROVED)
                ->with(['user', 'financialAccount'])
                ->orderBy('created_at')
                ->get();
        }

        $accounts = $organization->financialAccounts()->where('is_active', true)->orderBy('name')->get();
        $savingsAccount = $organization->memberSavingsAccount();
        $myBalanceCents = $savingsAccount?->balanceCents($user->id) ?? 0;

        return view('organizations.withdrawals.index', compact(
            'organization',
            'myRequests',
            'canManageFinances',
            'pendingApprovals',
            'readyToPay',
            'accounts',
            'myBalanceCents',
        ));
    }

    public function store(StoreWithdrawalRequestRequest $request, Organization $organization, WithdrawalService $service): RedirectResponse
    {
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $service->request(
            $organization,
            $account,
            Money::parseToCents($request->validated('amount')),
            $request->validated('reason'),
            $request->user(),
        );

        return redirect()->route('organizations.withdrawals.index', $organization)
            ->with('status', 'Withdrawal request submitted — awaiting approval.');
    }

    public function approve(Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('approve', $withdrawal);

        $service->approve($organization, $withdrawal, request()->user());

        return back()->with('status', 'Approval recorded.');
    }

    public function reject(Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('approve', $withdrawal);

        $service->reject($organization, $withdrawal, request('reason'), request()->user());

        return back()->with('status', 'Withdrawal request rejected.');
    }

    public function pay(Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('pay', $withdrawal);

        $service->pay($organization, $withdrawal, request()->user());

        return back()->with('status', 'Withdrawal paid and posted to the ledger.');
    }

    public function cancel(Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('cancel', $withdrawal);

        $service->cancel($organization, $withdrawal, request()->user());

        return back()->with('status', 'Withdrawal request cancelled.');
    }

    public function reverse(Organization $organization, WithdrawalRequest $withdrawal, WithdrawalService $service): RedirectResponse
    {
        abort_unless($withdrawal->organization_id === $organization->id, 404);
        $this->authorize('reverse', $withdrawal);

        $service->reverse($organization, $withdrawal, request()->user());

        return back()->with('status', 'Withdrawal reversed.');
    }
}
