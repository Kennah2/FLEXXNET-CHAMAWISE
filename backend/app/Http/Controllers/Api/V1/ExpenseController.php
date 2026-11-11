<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreExpenseRequest;
use App\Http\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\FinancialAccount;
use App\Models\Organization;
use App\Services\Expenses\ExpenseService;
use App\Support\Money;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ExpenseController extends Controller
{
    public function index(Organization $organization): AnonymousResourceCollection
    {
        $this->authorize('viewAny', [Expense::class, $organization]);

        $expenses = $organization->expenses()
            ->with(['financialAccount', 'recorder'])
            ->orderByDesc('expense_date')
            ->paginate(30);

        return ExpenseResource::collection($expenses);
    }

    public function store(StoreExpenseRequest $request, Organization $organization, ExpenseService $service): JsonResponse
    {
        $account = FinancialAccount::where('organization_id', $organization->id)->findOrFail($request->validated('financial_account_id'));

        $expense = $service->record(
            $organization,
            $account,
            Money::parseToCents($request->validated('amount')),
            $request->validated('description'),
            $request->validated('expense_date'),
            $request->user(),
        );

        return response()->json(['data' => new ExpenseResource($expense)], 201);
    }

    public function reverse(Organization $organization, Expense $expense, ExpenseService $service): JsonResponse
    {
        abort_unless($expense->organization_id === $organization->id, 404);
        $this->authorize('reverse', $expense);

        $expense = $service->reverse($organization, $expense, request()->user());

        return response()->json(['data' => new ExpenseResource($expense)]);
    }
}
