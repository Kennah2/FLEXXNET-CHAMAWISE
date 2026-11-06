<x-chama-layout :organization="$organization" active="overview">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
        @if ($organization->description)
            <p class="text-navy-400 mt-1">{{ $organization->description }}</p>
        @endif
    </x-slot>

    <div class="space-y-6">
        <!-- Welcome banner -->
        <div class="rounded-2xl bg-gradient-to-br from-navy-900 to-navy-700 text-white p-8">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3">
                        <h2 class="font-heading font-bold text-2xl">Welcome back, {{ Str::of(auth()->user()->name)->before(' ') }}!</h2>
                        @if ($currentUserRole)
                            <span class="inline-flex items-center rounded-full bg-white/15 px-3 py-1 text-xs font-medium">{{ ucfirst($currentUserRole) }}</span>
                        @endif
                    </div>
                    <p class="mt-2 text-navy-100 text-sm">You're viewing {{ $organization->name }}.</p>
                </div>
                <div class="text-left sm:text-right">
                    <p class="text-xs text-navy-100 uppercase tracking-wide">Active members</p>
                    <p class="text-3xl font-heading font-bold">{{ $organization->members->count() }}</p>
                </div>
            </div>
        </div>

        <!-- Pending actions -->
        <div>
            <h3 class="font-heading font-semibold text-navy-900 mb-3">Pending Actions</h3>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <a href="{{ route('organizations.members.index', $organization) }}" class="card flex items-center justify-between hover:ring-brand-pink transition">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="bell" class="h-5 w-5" /></span>
                        <div>
                            <p class="font-medium text-navy-900 text-sm">Pending Invitations</p>
                            <p class="text-xs text-navy-400">Awaiting acceptance</p>
                        </div>
                    </div>
                    <span class="text-lg font-heading font-bold text-navy-900">{{ $pendingInvitationsCount }}</span>
                </a>
                <a href="{{ route('organizations.module.show', [$organization, 'loans']) }}" class="card flex items-center justify-between opacity-60 hover:opacity-100 transition">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><x-icon name="calculator" class="h-5 w-5" /></span>
                        <div>
                            <p class="font-medium text-navy-900 text-sm">Loan Applications</p>
                            <p class="text-xs text-navy-400">Coming in Phase 6</p>
                        </div>
                    </div>
                </a>
                <a href="{{ route('organizations.withdrawals.index', $organization) }}" class="card flex items-center justify-between hover:ring-brand-pink transition">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-emerald-50 text-brand-teal"><x-icon name="currency" class="h-5 w-5" /></span>
                        <div>
                            <p class="font-medium text-navy-900 text-sm">Withdrawal Approvals</p>
                            <p class="text-xs text-navy-400">{{ $canManageFinances ? 'Awaiting your approval' : 'Officials only' }}</p>
                        </div>
                    </div>
                    @if ($canManageFinances)
                        <span class="text-lg font-heading font-bold text-navy-900">{{ $pendingWithdrawalApprovalsCount }}</span>
                    @endif
                </a>
            </div>
        </div>

        <!-- Stat cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
            <a href="{{ route('organizations.members.index', $organization) }}" class="card hover:ring-brand-pink transition">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-navy-400">Total Members</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-navy-50 text-navy-900"><x-icon name="users" class="h-5 w-5" /></span>
                </div>
                <p class="text-2xl font-heading font-bold text-navy-900 mt-3">{{ $organization->members->count() }}</p>
                <p class="text-xs text-brand-pink mt-1">View all &rarr;</p>
            </a>
            <a href="{{ route('organizations.module.show', [$organization, 'loans']) }}" class="card opacity-70 hover:opacity-100 transition">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-navy-400">Active Loans</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-amber-50 text-amber-600"><x-icon name="calculator" class="h-5 w-5" /></span>
                </div>
                <p class="text-sm font-medium text-navy-400 mt-3">Coming in Phase 6</p>
            </a>
            <a href="{{ route('organizations.contributions.index', $organization) }}" class="card hover:ring-brand-pink transition">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-navy-400">Total Contributions</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-emerald-50 text-brand-teal"><x-icon name="currency" class="h-5 w-5" /></span>
                </div>
                @if ($hasContributionSchedules)
                    <p class="text-2xl font-heading font-bold text-navy-900 mt-3">{{ \App\Support\Money::format($totalVerifiedContributionsCents, $organization->currency) }}</p>
                    <p class="text-xs text-brand-pink mt-1">View all &rarr;</p>
                @else
                    <p class="text-sm font-medium text-navy-400 mt-3">No schedule set up yet</p>
                @endif
            </a>
            <a href="{{ route('organizations.accounts.index', $organization) }}" class="card hover:ring-brand-pink transition">
                <div class="flex items-center justify-between">
                    <p class="text-sm text-navy-400">Accounts</p>
                    <span class="flex h-9 w-9 items-center justify-center rounded-lg bg-blue-50 text-blue-600"><x-icon name="card" class="h-5 w-5" /></span>
                </div>
                @if ($hasFinancialAccounts)
                    <p class="text-2xl font-heading font-bold text-navy-900 mt-3">{{ \App\Support\Money::format($totalCashCents, $organization->currency) }}</p>
                    <p class="text-xs text-brand-pink mt-1">View all &rarr;</p>
                @else
                    <p class="text-sm font-medium text-navy-400 mt-3">No accounts set up yet</p>
                @endif
            </a>
        </div>

        <!-- Quick actions -->
        <div>
            <h3 class="font-heading font-semibold text-navy-900 mb-3">Quick Actions</h3>
            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-4">
                @php
                    $quickActions = [
                        ['label' => 'Record Contribution', 'icon' => 'currency', 'bg' => 'bg-emerald-50', 'fg' => 'text-brand-teal', 'route' => route('organizations.contributions.index', $organization)],
                        ['label' => 'Apply for Loan', 'icon' => 'calculator', 'bg' => 'bg-purple-50', 'fg' => 'text-purple-600', 'route' => route('organizations.module.show', [$organization, 'loans'])],
                        ['label' => 'Add Member', 'icon' => 'plus', 'bg' => 'bg-blue-50', 'fg' => 'text-blue-600', 'route' => route('organizations.members.index', $organization)],
                        ['label' => 'Record Expense', 'icon' => 'list', 'bg' => 'bg-indigo-50', 'fg' => 'text-indigo-600', 'route' => route('organizations.expenses.index', $organization)],
                        ['label' => 'Add Account', 'icon' => 'card', 'bg' => 'bg-amber-50', 'fg' => 'text-amber-600', 'route' => route('organizations.accounts.create', $organization)],
                        ['label' => 'View Reports', 'icon' => 'chart', 'bg' => 'bg-rose-50', 'fg' => 'text-brand-pink', 'route' => route('organizations.module.show', [$organization, 'reports'])],
                    ];
                @endphp
                @foreach ($quickActions as $action)
                    <a href="{{ $action['route'] }}" class="card flex flex-col items-center justify-center gap-2 text-center py-6 hover:ring-brand-pink transition">
                        <span class="flex h-10 w-10 items-center justify-center rounded-lg {{ $action['bg'] }} {{ $action['fg'] }}"><x-icon :name="$action['icon']" class="h-5 w-5" /></span>
                        <span class="text-xs font-medium text-navy-900">{{ $action['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>

        <!-- Recent activity -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-heading font-semibold text-navy-900">Members</h3>
                    <a href="{{ route('organizations.members.index', $organization) }}" class="text-sm text-brand-pink hover:underline">View all &rarr;</a>
                </div>
                <ul class="divide-y divide-navy-50">
                    @foreach ($organization->members->take(5) as $member)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-navy-900 text-sm">{{ $member->name }}</p>
                                <p class="text-xs text-navy-400">Member #{{ $member->pivot->membership_number }}</p>
                            </div>
                            <span class="badge-success">{{ ucfirst($member->pivot->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Financial summary</h3>
                @if ($hasFinancialAccounts)
                    <ul class="space-y-3">
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-navy-400">Cash &amp; bank balance</span>
                            <span class="font-semibold text-navy-900">{{ \App\Support\Money::format($totalCashCents, $organization->currency) }}</span>
                        </li>
                        <li class="flex items-center justify-between text-sm">
                            <span class="text-navy-400">Owed to members (savings)</span>
                            <span class="font-semibold text-navy-900">{{ \App\Support\Money::format($memberSavingsCents, $organization->currency) }}</span>
                        </li>
                    </ul>
                @else
                    <p class="text-sm text-navy-400">Add a financial account to start tracking the ledger.</p>
                @endif
                <a href="{{ route('organizations.accounting.index', $organization) }}" class="text-sm text-brand-pink hover:underline inline-flex items-center gap-1 mt-4">
                    View accounting <x-icon name="arrow-right" class="h-4 w-4" />
                </a>
            </div>
        </div>
    </div>
</x-chama-layout>
