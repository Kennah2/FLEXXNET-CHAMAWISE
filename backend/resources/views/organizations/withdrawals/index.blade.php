<x-chama-layout :organization="$organization" active="withdrawals">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
        <p class="text-navy-400 mt-1">Withdrawals</p>
    </x-slot>

    <div class="space-y-6">
        <div class="card">
            <p class="text-sm text-navy-400">Your savings balance</p>
            <p class="text-2xl font-heading font-bold text-navy-900 mt-2">{{ \App\Support\Money::format($myBalanceCents, $organization->currency) }}</p>
        </div>

        @if ($accounts->isEmpty())
            <div class="card text-center py-8">
                <p class="text-navy-400">No financial accounts are set up yet, so withdrawals can't be paid out.</p>
            </div>
        @else
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Request a withdrawal</h3>
                <form method="POST" action="{{ route('organizations.withdrawals.store', $organization) }}" class="grid sm:grid-cols-4 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Amount ({{ $organization->currency }})</label>
                        <input type="text" inputmode="decimal" name="amount" placeholder="100.00" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Pay out from</label>
                        <select name="financial_account_id" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}">{{ $account->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="block text-xs font-medium text-navy-400 mb-1">Reason (optional)</label>
                        <input type="text" name="reason" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                    </div>
                    <div class="sm:col-span-4">
                        <button type="submit" class="btn-primary">Request withdrawal</button>
                    </div>
                </form>
                @error('amount')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
            </div>
        @endif

        @if ($canManageFinances && $pendingApprovals->isNotEmpty())
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Pending approvals</h3>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">Member</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Account</th>
                            <th class="py-2 pr-4">Approvals</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($pendingApprovals as $withdrawal)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-3 pr-4 font-medium text-navy-900">{{ $withdrawal->user->name }}</td>
                                <td class="py-3 pr-4">{{ \App\Support\Money::format($withdrawal->amount_cents, $organization->currency) }}</td>
                                <td class="py-3 pr-4 text-navy-400">{{ $withdrawal->financialAccount->name }}</td>
                                <td class="py-3 pr-4 text-navy-400">{{ $withdrawal->approvalsCount() }} / {{ $withdrawal->required_approvals }}</td>
                                <td class="py-3 pr-4">
                                    @if ($withdrawal->user_id === auth()->id())
                                        <span class="text-xs text-navy-400">Can't approve your own request</span>
                                    @else
                                        <div class="flex gap-3">
                                            <form method="POST" action="{{ route('organizations.withdrawals.approve', [$organization, $withdrawal]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-brand-teal hover:underline font-medium">Approve</button>
                                            </form>
                                            <form method="POST" action="{{ route('organizations.withdrawals.reject', [$organization, $withdrawal]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-red-600 hover:underline font-medium">Reject</button>
                                            </form>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        @if ($canManageFinances && $readyToPay->isNotEmpty())
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Approved — ready to pay</h3>
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">Member</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Account</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($readyToPay as $withdrawal)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-3 pr-4 font-medium text-navy-900">{{ $withdrawal->user->name }}</td>
                                <td class="py-3 pr-4">{{ \App\Support\Money::format($withdrawal->amount_cents, $organization->currency) }}</td>
                                <td class="py-3 pr-4 text-navy-400">{{ $withdrawal->financialAccount->name }}</td>
                                <td class="py-3 pr-4">
                                    <form method="POST" action="{{ route('organizations.withdrawals.pay', [$organization, $withdrawal]) }}">
                                        @csrf
                                        <button type="submit" class="text-xs text-brand-teal hover:underline font-medium">Mark paid</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <div class="card">
            <h3 class="font-heading font-semibold text-navy-900 mb-4">Your requests</h3>
            @if ($myRequests->isEmpty())
                <p class="text-sm text-navy-400">No withdrawal requests yet.</p>
            @else
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">Date</th>
                            <th class="py-2 pr-4">Amount</th>
                            <th class="py-2 pr-4">Account</th>
                            <th class="py-2 pr-4">Approvals</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($myRequests as $withdrawal)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-3 pr-4 text-navy-400">{{ $withdrawal->created_at->format('d M Y') }}</td>
                                <td class="py-3 pr-4 font-medium text-navy-900">{{ \App\Support\Money::format($withdrawal->amount_cents, $organization->currency) }}</td>
                                <td class="py-3 pr-4 text-navy-400">{{ $withdrawal->financialAccount->name }}</td>
                                <td class="py-3 pr-4 text-navy-400">{{ $withdrawal->approvalsCount() }} / {{ $withdrawal->required_approvals }}</td>
                                <td class="py-3 pr-4"><x-status-badge :status="$withdrawal->status" /></td>
                                <td class="py-3 pr-4">
                                    <div class="flex gap-3">
                                        @can('cancel', $withdrawal)
                                            <form method="POST" action="{{ route('organizations.withdrawals.cancel', [$organization, $withdrawal]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-navy-400 hover:underline">Cancel</button>
                                            </form>
                                        @endcan
                                        @can('reverse', $withdrawal)
                                            @if ($withdrawal->status === 'paid')
                                                <form method="POST" action="{{ route('organizations.withdrawals.reverse', [$organization, $withdrawal]) }}">
                                                    @csrf
                                                    <button type="submit" class="text-xs text-red-600 hover:underline">Reverse</button>
                                                </form>
                                            @endif
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>
    </div>
</x-chama-layout>
