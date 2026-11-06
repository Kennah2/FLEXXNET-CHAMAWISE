<x-chama-layout :organization="$organization" active="accounts">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $account->name }}</h2>
                <p class="text-navy-400 mt-1">{{ ucfirst(str_replace('_', ' ', $account->type)) }}@if($account->account_number) &middot; {{ $account->account_number }}@endif</p>
            </div>
            <a href="{{ route('organizations.accounts.index', $organization) }}" class="btn-secondary">Back to accounts</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        <div class="card">
            <p class="text-sm text-navy-400">Current balance</p>
            <p class="text-3xl font-heading font-bold text-navy-900 mt-2">{{ \App\Support\Money::format($account->balanceCents(), $account->currency) }}</p>
        </div>

        @can('reconcile', $account)
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Reconcile against a statement</h3>
                <form method="POST" action="{{ route('organizations.accounts.reconcile', [$organization, $account]) }}" class="grid sm:grid-cols-3 gap-3 items-end">
                    @csrf
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Statement date</label>
                        <input type="date" name="statement_date" value="{{ now()->toDateString() }}" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Statement balance ({{ $account->currency }})</label>
                        <input type="text" inputmode="decimal" name="statement_balance" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                    </div>
                    <div>
                        <button type="submit" class="btn-primary w-full">Record reconciliation</button>
                    </div>
                    <div class="sm:col-span-3">
                        <input type="text" name="notes" placeholder="Notes (optional)" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                    </div>
                </form>
                @error('statement_balance')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror

                @if ($reconciliations->isNotEmpty())
                    <table class="w-full text-left text-sm mt-6">
                        <thead>
                            <tr class="text-navy-400 border-b border-navy-50">
                                <th class="py-2 pr-4">Date</th>
                                <th class="py-2 pr-4">Statement</th>
                                <th class="py-2 pr-4">Ledger</th>
                                <th class="py-2 pr-4">Variance</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($reconciliations as $r)
                                <tr class="border-b border-navy-50 last:border-0">
                                    <td class="py-2 pr-4 text-navy-400">{{ $r->statement_date->format('d M Y') }}</td>
                                    <td class="py-2 pr-4">{{ \App\Support\Money::format($r->statement_balance_cents, $account->currency) }}</td>
                                    <td class="py-2 pr-4">{{ \App\Support\Money::format($r->computed_balance_cents, $account->currency) }}</td>
                                    <td class="py-2 pr-4">
                                        @if ($r->isBalanced())
                                            <span class="badge-success">Balanced</span>
                                        @else
                                            <span class="badge-danger">{{ \App\Support\Money::format($r->variance_cents, $account->currency) }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        @endcan

        <div class="card">
            <h3 class="font-heading font-semibold text-navy-900 mb-4">Statement</h3>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-navy-400 border-b border-navy-50">
                        <th class="py-2 pr-4">Date</th>
                        <th class="py-2 pr-4">Description</th>
                        <th class="py-2 pr-4 text-right">Debit</th>
                        <th class="py-2 pr-4 text-right">Credit</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($postings as $posting)
                        <tr class="border-b border-navy-50 last:border-0">
                            <td class="py-2 pr-4 text-navy-400">{{ $posting->journalEntry->posted_at->format('d M Y H:i') }}</td>
                            <td class="py-2 pr-4">{{ $posting->journalEntry->description }}</td>
                            <td class="py-2 pr-4 text-right text-brand-teal">{{ $posting->debit_cents > 0 ? \App\Support\Money::format($posting->debit_cents, $account->currency) : '—' }}</td>
                            <td class="py-2 pr-4 text-right text-red-600">{{ $posting->credit_cents > 0 ? \App\Support\Money::format($posting->credit_cents, $account->currency) : '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="py-6 text-center text-navy-400">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="mt-4">{{ $postings->links() }}</div>
        </div>
    </div>
</x-chama-layout>
