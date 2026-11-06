<x-chama-layout :organization="$organization" active="accounting">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
                <p class="text-navy-400 mt-1">Expenses</p>
            </div>
            <a href="{{ route('organizations.accounting.index', $organization) }}" class="btn-secondary">Back to accounting</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @can('create', [\App\Models\Expense::class, $organization])
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Record an expense</h3>
                @if ($accounts->isEmpty())
                    <p class="text-sm text-navy-400">Add a financial account first before recording expenses.</p>
                @else
                    <form method="POST" action="{{ route('organizations.expenses.store', $organization) }}" class="grid sm:grid-cols-4 gap-3 items-end">
                        @csrf
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1">Amount ({{ $organization->currency }})</label>
                            <input type="text" inputmode="decimal" name="amount" placeholder="100.00" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1">Paid from</label>
                            <select name="financial_account_id" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                                @foreach ($accounts as $account)
                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-navy-400 mb-1">Date</label>
                            <input type="date" name="expense_date" value="{{ now()->toDateString() }}" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                        </div>
                        <div>
                            <button type="submit" class="btn-primary w-full">Record</button>
                        </div>
                        <div class="sm:col-span-4">
                            <input type="text" name="description" placeholder="Description (e.g. Stationery, venue hire)" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                        </div>
                    </form>
                    @error('amount')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
                    @error('description')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
                @endif
            </div>
        @endcan

        <div class="card">
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-navy-400 border-b border-navy-50">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Description</th>
                        <th class="py-2 pr-4">Account</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Status</th>
                        <th class="py-2 pr-4">Recorded by</th>
                        <th class="py-2 pr-4">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($expenses as $expense)
                        <tr class="border-b border-navy-50 last:border-0">
                            <td class="py-3 pr-4 text-navy-400">{{ $expense->expense_date->format('d M Y') }}</td>
                            <td class="py-3 pr-4 font-medium text-navy-900">{{ $expense->description }}</td>
                            <td class="py-3 pr-4 text-navy-400">{{ $expense->financialAccount->name }}</td>
                            <td class="py-3 pr-4">{{ \App\Support\Money::format($expense->amount_cents, $organization->currency) }}</td>
                            <td class="py-3 pr-4"><x-status-badge :status="$expense->status" /></td>
                            <td class="py-3 pr-4 text-navy-400">{{ $expense->recorder->name }}</td>
                            <td class="py-3 pr-4">
                                @can('reverse', $expense)
                                    @if ($expense->status === 'posted')
                                        <form method="POST" action="{{ route('organizations.expenses.reverse', [$organization, $expense]) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-red-600 hover:underline">Reverse</button>
                                        </form>
                                    @endif
                                @endcan
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="py-6 text-center text-navy-400">No expenses recorded yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $expenses->links() }}</div>
        </div>
    </div>
</x-chama-layout>
