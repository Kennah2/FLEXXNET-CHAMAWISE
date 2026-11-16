<?php

namespace App\Http\Controllers;

use App\Http\Requests\ApproveLoanRequest;
use App\Http\Requests\DisburseLoanRequest;
use App\Http\Requests\StoreLoanRepaymentRequest;
use App\Http\Requests\StoreLoanRequest;
use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Models\LoanRepayment;
use App\Models\Organization;
use App\Models\User;
use App\Services\Loans\LoanService;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Spatie\Permission\PermissionRegistrar;

class LoanController extends Controller
{
    public function index(Organization $organization): View
    {
        $this->authorize('viewAny', [Loan::class, $organization]);

        $user = request()->user();

        $myLoans = Loan::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->orderByDesc('created_at')
            ->get();

        app(PermissionRegistrar::class)->setPermissionsTeamId($organization->id);
        $canManageFinances = User::find($user->id)->can(ChamaPermissions::MANAGE_FINANCES);

        $pendingApplications = collect();
        $readyToDisburse = collect();
        if ($canManageFinances) {
            $pendingApplications = Loan::where('organization_id', $organization->id)
                ->where('status', Loan::STATUS_PENDING)
                ->with('user')
                ->orderBy('created_at')
                ->get();

            // Every org-wide approved-but-undisbursed loan, not just the viewer's own — same
            // reasoning as withdrawals' "ready to pay" queue (see Phase 3's bug writeup).
            $readyToDisburse = Loan::where('organization_id', $organization->id)
                ->where('status', Loan::STATUS_APPROVED)
                ->with('user')
                ->orderBy('created_at')
                ->get();
        }

        $accounts = $organization->financialAccounts()->where('is_active', true)->orderBy('name')->get();

        return view('organizations.loans.index', compact(
            'organization',
            'myLoans',
            'canManageFinances',
            'pendingApplications',
            'readyToDisburse',
            'accounts',
        ));
    }

    public function store(StoreLoanRequest $request, Organization $organization, LoanService $service): RedirectResponse
    {
        $forUserId = (int) ($request->validated('user_id') ?: $request->user()->id);

        $service->apply(
            $organization,
            $forUserId,
            Money::parseToCents($request->validated('amount')),
            (int) $request->validated('term_months'),
            $request->validated('reason'),
            $request->user(),
        );

        return back()->with('status', 'Loan application submitted — awaiting a decision.');
    }

    public function show(Organization $organization, Loan $loan): View
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $this->authorize('view', $loan);

        $loan->load(['installments', 'repayments.recorder', 'user']);
        $accounts = $organization->financialAccounts()->where('is_active', true)->orderBy('name')->get();

        return view('organizations.loans.show', compact('organization', 'loan', 'accounts'));
    }

    public function approve(ApproveLoanRequest $request, Organization $organization, Loan $loan, LoanService $service): RedirectResponse
    {
        $service->approve(
            $organization,
            $loan,
            Money::parseToCents($request->validated('approved_amount')),
            (int) $request->validated('term_months'),
            Money::parseToCents($request->validated('interest_rate')),
            $request->user(),
        );

        return back()->with('status', 'Loan approved.');
    }

    public function reject(Organization $organization, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $this->authorize('approve', $loan);

        $service->reject($organization, $loan, request()->user());

        return back()->with('status', 'Loan application rejected.');
    }

    public function cancel(Organization $organization, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $this->authorize('cancel', $loan);

        $service->cancel($organization, $loan, request()->user());

        return back()->with('status', 'Loan application cancelled.');
    }

    public function disburse(DisburseLoanRequest $request, Organization $organization, Loan $loan, LoanService $service): RedirectResponse
    {
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $service->disburse($organization, $loan, $account, $request->user());

        return back()->with('status', 'Loan disbursed and posted to the ledger.');
    }

    public function storeRepayment(StoreLoanRepaymentRequest $request, Organization $organization, Loan $loan, LoanService $service): RedirectResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $service->recordRepayment($organization, $loan, $account, Money::parseToCents($request->validated('amount')), $request->user());

        return back()->with('status', 'Repayment recorded and posted to the ledger.');
    }

    public function reverseRepayment(Organization $organization, Loan $loan, LoanRepayment $repayment, LoanService $service): RedirectResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        abort_unless($repayment->loan_id === $loan->id, 404);
        $this->authorize('reverseRepayment', $loan);

        $service->reverseRepayment($organization, $repayment, request()->user());

        return back()->with('status', 'Repayment reversed.');
    }
}
