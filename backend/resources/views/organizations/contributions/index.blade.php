<x-chama-layout :organization="$organization" active="contributions">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
                <p class="text-navy-400 mt-1">Contributions</p>
            </div>
            @can('create', [\App\Models\ContributionSchedule::class, $organization])
                <a href="{{ route('organizations.contributions.schedules.index', $organization) }}" class="btn-secondary">Manage schedules</a>
            @endcan
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($activeSchedules->isEmpty())
            <div class="card text-center py-12">
                <p class="text-navy-400">No contribution schedule has been set up yet.</p>
                @can('create', [\App\Models\ContributionSchedule::class, $organization])
                    <a href="{{ route('organizations.contributions.schedules.create', $organization) }}" class="btn-primary mt-4 inline-flex">Create a contribution schedule</a>
                @endcan
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-2 {{ $myOutstandingPenaltyCents > 0 ? 'lg:grid-cols-3' : '' }} gap-4">
                <div class="card">
                    <p class="text-sm text-navy-400">Your outstanding balance</p>
                    <p class="text-2xl font-heading font-bold text-navy-900 mt-2">{{ \App\Support\Money::format($myOutstandingCents, $organization->currency) }}</p>
                </div>
                @if ($myOutstandingPenaltyCents > 0)
                    <div class="card border-l-4 border-red-500">
                        <p class="text-sm text-navy-400">Outstanding penalties</p>
                        <p class="text-2xl font-heading font-bold text-red-600 mt-2">{{ \App\Support\Money::format($myOutstandingPenaltyCents, $organization->currency) }}</p>
                        <p class="text-xs text-navy-400 mt-1">Tracked separately from your contributions.</p>
                    </div>
                @endif
                @if ($canManageFinances)
                    <div class="card">
                        <p class="text-sm text-navy-400">Chama total verified contributions</p>
                        <p class="text-2xl font-heading font-bold text-brand-teal mt-2">{{ \App\Support\Money::format($organizationTotalVerifiedCents, $organization->currency) }}</p>
                    </div>
                @endif
            </div>

            <!-- Record a contribution -->
            <div class="card">
                <h3 class="font-heading font-semibold text-navy-900 mb-4">Record a contribution</h3>
                <form method="POST" action="{{ route('organizations.contributions.store', $organization) }}" class="grid sm:grid-cols-2 lg:grid-cols-6 gap-3 items-end">
                    @csrf
                    @if ($canManageFinances)
                        <div class="lg:col-span-2">
                            <label class="block text-xs font-medium text-navy-400 mb-1">Member</label>
                            <select name="user_id" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                                @foreach ($members as $member)
                                    <option value="{{ $member->id }}" @selected($member->id === auth()->id())>{{ $member->name }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Schedule</label>
                        <select name="contribution_schedule_id" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            <option value="">General / unscheduled</option>
                            @foreach ($activeSchedules as $schedule)
                                <option value="{{ $schedule->id }}">{{ $schedule->name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Amount ({{ $organization->currency }})</label>
                        <input type="text" inputmode="decimal" name="amount" placeholder="100.00" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Method</label>
                        <select name="payment_method" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            @foreach (\App\Models\Contribution::METHODS as $method)
                                <option value="{{ $method }}">{{ ucfirst(str_replace('_', ' ', $method)) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Reference (optional)</label>
                        <input type="text" name="transaction_reference" placeholder="e.g. M-Pesa code" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                    </div>
                    <div>
                        <label class="block text-xs font-medium text-navy-400 mb-1">Paid at</label>
                        <input type="datetime-local" name="paid_at" value="{{ now()->format('Y-m-d\TH:i') }}" class="w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                    </div>
                    <div class="lg:col-span-6">
                        <button type="submit" class="btn-primary">Record contribution</button>
                        <span class="text-xs text-navy-400 ml-2">Awaits verification.</span>
                    </div>
                </form>
                @error('amount')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
            </div>

            @if ($canManageFinances && $pendingVerifications->isNotEmpty())
                <div class="card">
                    <h3 class="font-heading font-semibold text-navy-900 mb-4">Pending verifications</h3>
                    <table class="w-full text-left text-sm">
                        <thead>
                            <tr class="text-navy-400 border-b border-navy-50">
                                <th class="py-2 pr-4">Member</th>
                                <th class="py-2 pr-4">Amount</th>
                                <th class="py-2 pr-4">Method</th>
                                <th class="py-2 pr-4">Reference</th>
                                <th class="py-2 pr-4">Paid at</th>
                                <th class="py-2 pr-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($pendingVerifications as $payment)
                                <tr class="border-b border-navy-50 last:border-0">
                                    <td class="py-3 pr-4 font-medium text-navy-900">{{ $payment->user->name }}</td>
                                    <td class="py-3 pr-4">{{ \App\Support\Money::format($payment->amount_cents, $payment->currency) }}</td>
                                    <td class="py-3 pr-4 text-navy-400">{{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</td>
                                    <td class="py-3 pr-4 text-navy-400">{{ $payment->transaction_reference ?? '—' }}</td>
                                    <td class="py-3 pr-4 text-navy-400">{{ $payment->paid_at->format('d M Y H:i') }}</td>
                                    <td class="py-3 pr-4">
                                        <div class="flex gap-3">
                                            <form method="POST" action="{{ route('organizations.contributions.verify', [$organization, $payment]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-brand-teal hover:underline font-medium">Verify</button>
                                            </form>
                                            <form method="POST" action="{{ route('organizations.contributions.reject', [$organization, $payment]) }}">
                                                @csrf
                                                <button type="submit" class="text-xs text-red-600 hover:underline font-medium">Reject</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="card">
                    <h3 class="font-heading font-semibold text-navy-900 mb-4">Your obligations</h3>
                    @if ($myObligations->isEmpty())
                        <p class="text-sm text-navy-400">Nothing due yet.</p>
                    @else
                        <ul class="divide-y divide-navy-50">
                            @foreach ($myObligations as $obligation)
                                <li class="py-3 flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-navy-900 text-sm">{{ $obligation->schedule->name }}</p>
                                        <p class="text-xs text-navy-400">
                                            {{ $obligation->period_start->format('d M Y') }}
                                            @if (!$obligation->period_start->eq($obligation->period_end))
                                                &ndash; {{ $obligation->period_end->format('d M Y') }}
                                            @endif
                                            &middot; due {{ $obligation->due_at->timezone($obligation->schedule->timezone)->format('d M Y H:i') }}
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <p class="text-sm font-semibold text-navy-900">{{ \App\Support\Money::format($obligation->amount_due_cents, $organization->currency) }}</p>
                                        <x-status-badge :status="$obligation->status" />
                                        @if ($obligation->penalty_status === \App\Models\ContributionObligation::PENALTY_ASSESSED)
                                            <p class="text-xs text-red-600 mt-1">+ {{ \App\Support\Money::format($obligation->penalty_amount_cents, $organization->currency) }} penalty</p>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="card">
                    <h3 class="font-heading font-semibold text-navy-900 mb-4">Your payment history</h3>
                    @if ($myPayments->isEmpty())
                        <p class="text-sm text-navy-400">No payments recorded yet.</p>
                    @else
                        <ul class="divide-y divide-navy-50">
                            @foreach ($myPayments as $payment)
                                <li class="py-3 flex items-center justify-between">
                                    <div>
                                        <p class="font-medium text-navy-900 text-sm">{{ \App\Support\Money::format($payment->amount_cents, $payment->currency) }}</p>
                                        <p class="text-xs text-navy-400">{{ $payment->paid_at->format('d M Y H:i') }} &middot; {{ ucfirst(str_replace('_', ' ', $payment->payment_method)) }}</p>
                                    </div>
                                    <x-status-badge :status="$payment->status" />
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-chama-layout>
