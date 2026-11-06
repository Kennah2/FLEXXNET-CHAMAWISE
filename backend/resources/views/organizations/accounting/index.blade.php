<x-chama-layout :organization="$organization" active="accounting">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
                <p class="text-navy-400 mt-1">Accounting</p>
            </div>
            <a href="{{ route('organizations.expenses.index', $organization) }}" class="btn-secondary">Expenses</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($totalDebits !== $totalCredits)
            <div class="card border-l-4 border-red-500">
                <p class="text-sm font-semibold text-red-700">The ledger does not balance (debits {{ \App\Support\Money::format($totalDebits, $organization->currency) }} vs credits {{ \App\Support\Money::format($totalCredits, $organization->currency) }}).</p>
                <p class="text-xs text-navy-400 mt-1">This should be structurally impossible — every posting goes through a balance-enforcing service. Please report this.</p>
            </div>
        @endif

        @foreach ($byType as $type => $accounts)
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">{{ ucfirst($type) }}{{ $type === 'asset' ? 's' : ($type === 'expense' ? 's' : '') }}</h3>
                <table class="w-full text-left text-sm">
                    <tbody>
                        @foreach ($accounts as $account)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-2 pr-4 text-navy-900">
                                    {{ $account->name }}
                                    @if ($account->is_system)
                                        <span class="badge-neutral ml-2">System</span>
                                    @endif
                                </td>
                                <td class="py-2 pr-4 text-right font-semibold">{{ \App\Support\Money::format($account->balanceCents(), $organization->currency) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach

        <div class="grid sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('organizations.accounts.index', $organization) }}" class="card text-center hover:ring-brand-pink transition">
                <p class="font-medium text-navy-900">Manage accounts</p>
            </a>
            <a href="{{ route('organizations.transactions.index', $organization) }}" class="card text-center hover:ring-brand-pink transition">
                <p class="font-medium text-navy-900">View transactions</p>
            </a>
            <a href="{{ route('organizations.expenses.index', $organization) }}" class="card text-center hover:ring-brand-pink transition">
                <p class="font-medium text-navy-900">Record an expense</p>
            </a>
            @can('view', [\App\Models\PaymentGatewayCredential::class, $organization])
                <a href="{{ route('organizations.payments.daraja.edit', $organization) }}" class="card text-center hover:ring-brand-pink transition">
                    <p class="font-medium text-navy-900">M-Pesa settings</p>
                </a>
            @endcan
        </div>
    </div>
</x-chama-layout>
