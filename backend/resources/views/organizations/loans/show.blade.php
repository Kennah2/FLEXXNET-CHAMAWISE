<x-chama-layout :organization="$organization" active="loans">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $loan->user->name }}'s loan</h2>
                <p class="text-navy-400 mt-1">{{ $organization->name }}</p>
            </div>
            <a href="{{ route('organizations.loans.index', $organization) }}" class="btn-secondary">Back to loans</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
            <div class="card">
                <p class="text-sm text-navy-400">Status</p>
                <x-status-badge :status="$loan->status" />
            </div>
            <div class="card">
                <p class="text-sm text-navy-400">Principal</p>
                <p class="text-xl font-heading font-bold text-navy-900 mt-1">{{ \App\Support\Money::format($loan->approved_amount_cents ?? $loan->requested_amount_cents, $organization->currency) }}</p>
            </div>
            @if ($loan->status !== 'pending' && $loan->status !== 'rejected' && $loan->status !== 'cancelled')
                <div class="card">
                    <p class="text-sm text-navy-400">Outstanding principal</p>
                    <p class="text-xl font-heading font-bold text-navy-900 mt-1">{{ \App\Support\Money::format($loan->outstandingPrincipalCents(), $organization->currency) }}</p>
                </div>
                <div class="card">
                    <p class="text-sm text-navy-400">Outstanding interest</p>
                    <p class="text-xl font-heading font-bold text-navy-900 mt-1">{{ \App\Support\Money::format($loan->outstandingInterestCents(), $organization->currency) }}</p>
                </div>
            @endif
        </div>

        @if ($loan->status === 'disbursed')
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Record a repayment</h3>
                <form method="POST" action="{{ route('organizations.loans.repayments.store', [$organization, $loan]) }}" class="grid sm:grid-cols-3 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Amount ({{ $organization->currency }})</label>
                        <input type="text" inputmode="decimal" name="amount" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Paid into</label>
                        <select name="financial_account_id" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn-primary">Record repayment</button>
                </form>
                @error('amount')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
            </div>
        @endif

        @if ($loan->installments->isNotEmpty())
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Repayment schedule</h3>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Due</th>
                            <th class="py-2 pr-4">Principal</th>
                            <th class="py-2 pr-4">Interest</th>
                            <th class="py-2 pr-4">Outstanding</th>
                            <th class="py-2 pr-4">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($loan->installments as $installment)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-2 pr-4 text-navy-400">{{ $installment->installment_number }}</td>
                                <td class="py-2 pr-4 text-navy-400">{{ $installment->due_date->format('d M Y') }}</td>
                                <td class="py-2 pr-4">{{ \App\Support\Money::format($installment->principal_due_cents, $organization->currency) }}</td>
                                <td class="py-2 pr-4">{{ \App\Support\Money::format($installment->interest_due_cents, $organization->currency) }}</td>
                                <td class="py-2 pr-4 font-medium">{{ \App\Support\Money::format($installment->outstandingCents(), $organization->currency) }}</td>
                                <td class="py-2 pr-4"><x-status-badge :status="$installment->status" /></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($loan->repayments->isNotEmpty())
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Repayment history</h3>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Principal</th>
                            <th class="py-2 pr-4">Interest</th>
                            <th class="py-2 pr-4">Recorded by</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($loan->repayments as $repayment)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-2 pr-4 text-navy-400">{{ $repayment->paid_at->format('d M Y H:i') }}</td>
                                <td class="py-2 pr-4 font-medium text-navy-900">{{ \App\Support\Money::format($repayment->amount_cents, $organization->currency) }}</td>
                                <td class="py-2 pr-4">{{ \App\Support\Money::format($repayment->principal_cents, $organization->currency) }}</td>
                                <td class="py-2 pr-4">{{ \App\Support\Money::format($repayment->interest_cents, $organization->currency) }}</td>
                                <td class="py-2 pr-4 text-navy-400">{{ $repayment->recorder->name }}</td>
                                <td class="py-2 pr-4"><x-status-badge :status="$repayment->status" /></td>
                                <td class="py-2 pr-4">
                                    @can('reverseRepayment', $loan)
                                        @if ($repayment->status === 'posted')
                                            <form method="POST" action="{{ route('organizations.loans.repayments.reverse', [$organization, $loan, $repayment]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-red-600 hover:underline">Reverse</button>
                                            </form>
                                        @endif
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-chama-layout>
