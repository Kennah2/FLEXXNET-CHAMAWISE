<x-chama-layout :organization="$organization" active="loans">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
        <p class="text-navy-400 mt-1">Loans</p>
    </x-slot>

    <div class="space-y-6">
        <div class="card">
            <h3 class="font-heading font-semibold text-navy-900 mb-4">Apply for a loan</h3>
            <form method="POST" action="{{ route('organizations.loans.store', $organization) }}" class="grid sm:grid-cols-4 gap-3 items-end">
                @csrf
                <div>
                    <label class="block text-xs font-medium text-navy-400 mb-1">Amount ({{ $organization->currency }})</label>
                    <input type="text" inputmode="decimal" name="amount" placeholder="10000.00" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                </div>
                <div>
                    <label class="block text-xs font-medium text-navy-400 mb-1">Term (months)</label>
                    <input type="number" min="1" max="120" name="term_months" placeholder="12" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                </div>
                <div class="sm:col-span-2">
                    <label class="block text-xs font-medium text-navy-400 mb-1">Reason (optional)</label>
                    <input type="text" name="reason" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                </div>
                <div class="sm:col-span-4">
                    <button type="submit" class="btn-primary">Apply for loan</button>
                    <span class="text-xs text-navy-400 ml-2">The requested amount and term are a starting point — the committee sets the final terms on approval.</span>
                </div>
            </form>
            @error('amount')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
        </div>

        @if ($canManageFinances && $pendingApplications->isNotEmpty())
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Pending applications</h3>
                <div class="space-y-4">
                    @foreach ($pendingApplications as $loan)
                        <div class="border border-navy-50 rounded-lg p-4">
                            <div class="flex items-center justify-between mb-3">
                                <div>
                                    <p class="font-medium text-navy-900">{{ $loan->user->name }} requested {{ \App\Support\Money::format($loan->requested_amount_cents, $organization->currency) }}</p>
                                    <p class="text-xs text-navy-400">over {{ $loan->requested_term_months }} months @if ($loan->reason) &middot; {{ $loan->reason }} @endif</p>
                                </div>
                            </div>
                            @if ($loan->user_id === auth()->id())
                                <span class="text-xs text-navy-400">Can't approve your own application</span>
                            @else
                                <div class="flex flex-wrap gap-3 items-end">
                                    <form method="POST" action="{{ route('organizations.loans.approve', [$organization, $loan]) }}" class="flex flex-wrap gap-2 items-end">
                                        @csrf
                                        <div>
                                            <label class="block text-xs font-medium text-navy-400 mb-1">Approved amount</label>
                                            <input type="text" inputmode="decimal" name="approved_amount" value="{{ sprintf('%d.%02d', intdiv($loan->requested_amount_cents, 100), $loan->requested_amount_cents % 100) }}" class="w-32 rounded-md border-navy-100 shadow-sm text-sm" required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-navy-400 mb-1">Term (months)</label>
                                            <input type="number" min="1" max="120" name="term_months" value="{{ $loan->requested_term_months }}" class="w-24 rounded-md border-navy-100 shadow-sm text-sm" required>
                                        </div>
                                        <div>
                                            <label class="block text-xs font-medium text-navy-400 mb-1">Interest rate (% flat)</label>
                                            <input type="text" inputmode="decimal" name="interest_rate" placeholder="12.5" class="w-24 rounded-md border-navy-100 shadow-sm text-sm" required>
                                        </div>
                                        <button type="submit" class="text-xs text-brand-teal hover:underline font-medium pb-2">Approve</button>
                                    </form>
                                    <form method="POST" action="{{ route('organizations.loans.reject', [$organization, $loan]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-red-600 hover:underline font-medium">Reject</button>
                                    </form>
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($canManageFinances && $readyToDisburse->isNotEmpty())
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Approved — ready to disburse</h3>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">Member</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Term</th>
                            <th class="py-2 pr-4">Rate</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($readyToDisburse as $loan)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-3 pr-4 font-medium text-navy-900">{{ $loan->user->name }}</td>
                                <td class="py-3 pr-4">{{ \App\Support\Money::format($loan->approved_amount_cents, $organization->currency) }}</td>
                                <td class="py-3 pr-4 text-navy-400">{{ $loan->term_months }} months</td>
                                <td class="py-3 pr-4 text-navy-400">{{ number_format($loan->interest_rate_bps / 100, 2) }}%</td>
                                <td class="py-3 pr-4">
                                    @if ($loan->user_id === auth()->id())
                                        <span class="text-xs text-navy-400">Can't disburse your own loan</span>
                                    @elseif ($accounts->isEmpty())
                                        <span class="text-xs text-navy-400">No account to disburse from</span>
                                    @else
                                        <form method="POST" action="{{ route('organizations.loans.disburse', [$organization, $loan]) }}" class="flex gap-2 items-center">
                                            @csrf
                                            <select name="financial_account_id" class="rounded-md border-navy-100 shadow-sm text-xs">
                                                @foreach ($accounts as $account)
                                                    <option value="{{ $account->id }}">{{ $account->name }}</option>
                                                @endforeach
                                            </select>
                                            <button type="submit" class="text-xs text-brand-teal hover:underline font-medium">Disburse</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card">
            <h3 class="font-heading font-semibold text-navy-900 mb-4">Your loans</h3>
            @if ($myLoans->isEmpty())
                <p class="text-sm text-navy-400">No loan applications yet.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($myLoans as $loan)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-3 pr-4 text-navy-400">{{ $loan->created_at->format('d M Y') }}</td>
                                <td class="py-3 pr-4 font-medium text-navy-900">
                                    <a href="{{ route('organizations.loans.show', [$organization, $loan]) }}" class="hover:text-brand-pink">
                                        {{ \App\Support\Money::format($loan->approved_amount_cents ?? $loan->requested_amount_cents, $organization->currency) }}
                                    </a>
                                </td>
                                <td class="py-3 pr-4"><x-status-badge :status="$loan->status" /></td>
                                <td class="py-3 pr-4">
                                    @can('cancel', $loan)
                                        <form method="POST" action="{{ route('organizations.loans.cancel', [$organization, $loan]) }}">
                                            @csrf
                                            <button type="submit" class="text-xs text-navy-400 hover:underline">Cancel</button>
                                        </form>
                                    @endcan
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-chama-layout>
