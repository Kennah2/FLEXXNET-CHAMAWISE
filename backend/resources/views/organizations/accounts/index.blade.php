<x-chama-layout :organization="$organization" active="accounts">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
                <p class="text-navy-400 mt-1">Accounts</p>
            </div>
            @can('create', [\App\Models\FinancialAccount::class, $organization])
                <a href="{{ route('organizations.accounts.create', $organization) }}" class="btn-primary">+ New account</a>
            @endcan
        </div>
    </x-slot>

    @if ($accounts->isEmpty())
        <div class="card text-center py-12">
            <p class="text-navy-400">No financial accounts yet.</p>
            @can('create', [\App\Models\FinancialAccount::class, $organization])
                <a href="{{ route('organizations.accounts.create', $organization) }}" class="btn-primary mt-4 inline-flex">Add your first account</a>
            @endcan
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($accounts as $account)
                <a href="{{ route('organizations.accounts.show', [$organization, $account]) }}" class="card hover:ring-brand-pink transition">
                    <div class="flex items-center justify-between">
                        <h3 class="font-heading font-semibold text-navy-900">{{ $account->name }}</h3>
                        @if ($account->is_default)
                            <span class="badge-success">Default</span>
                        @endif
                    </div>
                    <p class="text-sm text-navy-400 mt-1">{{ ucfirst(str_replace('_', ' ', $account->type)) }}@if($account->account_number) &middot; {{ $account->account_number }}@endif</p>
                    <p class="text-2xl font-heading font-bold text-navy-900 mt-4">{{ \App\Support\Money::format($account->balanceCents(), $account->currency) }}</p>
                </a>
            @endforeach
        </div>
    @endif
</x-chama-layout>
