<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ApproveLoanRequest;
use App\Http\Requests\DisburseLoanRequest;
use App\Http\Requests\StoreLoanRepaymentRequest;
use App\Http\Resources\LoanRepaymentResource;
use App\Http\Resources\LoanResource;
use App\Models\FinancialAccount;
use App\Models\Loan;
use App\Models\Organization;
use App\Models\User;
use App\Services\Loans\LoanService;
use App\Support\ChamaPermissions;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;

class LoanController extends Controller
{
    public function index(Organization $organization): JsonResponse
    {
        $this->authorize('viewAny', [Loan::class, $organization]);

        $user = request()->user();

        $myLoans = Loan::where('organization_id', $organization->id)
            ->where('user_id', $user->id)
            ->with(['installments', 'repayments'])
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

            $readyToDisburse = Loan::where('organization_id', $organization->id)
                ->where('status', Loan::STATUS_APPROVED)
                ->with('user')
                ->orderBy('created_at')
                ->get();
        }

        return response()->json([
            'data' => [
                'can_manage_finances' => $canManageFinances,
                'my_loans' => LoanResource::collection($myLoans),
                'pending_applications' => LoanResource::collection($pendingApplications),
                'ready_to_disburse' => LoanResource::collection($readyToDisburse),
            ],
        ]);
    }

    public function store(Request $request, Organization $organization, LoanService $service): JsonResponse
    {
        $validated = $request->validate([
            'user_id' => ['nullable', 'integer'],
            'amount' => ['required', 'regex:/^\d+(\.\d{1,2})?$/'],
            'term_months' => ['required', 'integer', 'min:1', 'max:120'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $forUserId = (int) ($validated['user_id'] ?? $request->user()->id);
        $this->authorize('create', [Loan::class, $organization, $forUserId]);

        $loan = $service->apply(
            $organization,
            $forUserId,
            Money::parseToCents($validated['amount']),
            (int) $validated['term_months'],
            $validated['reason'] ?? null,
            $request->user(),
        );

        return response()->json(['data' => new LoanResource($loan)], 201);
    }

    public function show(Organization $organization, Loan $loan): JsonResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $this->authorize('view', $loan);

        $loan->load(['installments', 'repayments.recorder', 'user']);

        return response()->json(['data' => new LoanResource($loan)]);
    }

    public function approve(ApproveLoanRequest $request, Organization $organization, Loan $loan, LoanService $service): JsonResponse
    {
        $loan = $service->approve(
            $organization,
            $loan,
            Money::parseToCents($request->validated('approved_amount')),
            (int) $request->validated('term_months'),
            Money::parseToCents($request->validated('interest_rate')),
            $request->user(),
        );

        return response()->json(['data' => new LoanResource($loan)]);
    }

    public function reject(Organization $organization, Loan $loan, LoanService $service): JsonResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $this->authorize('approve', $loan);

        $loan = $service->reject($organization, $loan, request()->user());

        return response()->json(['data' => new LoanResource($loan)]);
    }

    public function cancel(Organization $organization, Loan $loan, LoanService $service): JsonResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $this->authorize('cancel', $loan);

        $loan = $service->cancel($organization, $loan, request()->user());

        return response()->json(['data' => new LoanResource($loan)]);
    }

    public function disburse(DisburseLoanRequest $request, Organization $organization, Loan $loan, LoanService $service): JsonResponse
    {
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $loan = $service->disburse($organization, $loan, $account, $request->user());

        return response()->json(['data' => new LoanResource($loan)]);
    }

    public function storeRepayment(StoreLoanRepaymentRequest $request, Organization $organization, Loan $loan, LoanService $service): JsonResponse
    {
        abort_unless($loan->organization_id === $organization->id, 404);
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $repayment = $service->recordRepayment($organization, $loan, $account, Money::parseToCents($request->validated('amount')), $request->user());

        return response()->json(['data' => new LoanRepaymentResource($repayment)], 201);
    }
}
