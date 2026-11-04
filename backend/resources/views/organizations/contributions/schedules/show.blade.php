<x-chama-layout :organization="$organization" active="contributions">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $schedule->name }}</h2>
                <p class="text-navy-400 mt-1">
                    {{ ucfirst($schedule->frequency) }} &middot; {{ \App\Support\Money::format($schedule->amount_cents, $schedule->currency) }} per member
                    &middot; deadline {{ $schedule->deadline_time }} {{ $schedule->timezone }}
                </p>
            </div>
            <a href="{{ route('organizations.contributions.schedules.index', $organization) }}" class="btn-secondary">Back to schedules</a>
        </div>
    </x-slot>

    <div class="space-y-6">
        @if ($schedule->description)
            <div class="card">
                <p class="text-navy-700 text-sm">{{ $schedule->description }}</p>
            </div>
        @endif

        <div class="card">
            <h3 class="font-heading font-semibold text-navy-900 mb-4">Per-member summary</h3>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-navy-400 border-b border-navy-50">
                        <th class="py-2 pr-4">Member</th>
                        <th class="py-2 pr-4">Expected</th>
                        <th class="py-2 pr-4">Paid</th>
                        <th class="py-2 pr-4">Overdue periods</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($memberSummaries as $summary)
                        <tr class="border-b border-navy-50 last:border-0">
                            <td class="py-3 pr-4 font-medium text-navy-900">{{ $summary['user']->name }}</td>
                            <td class="py-3 pr-4">{{ \App\Support\Money::format($summary['expected'], $schedule->currency) }}</td>
                            <td class="py-3 pr-4 text-brand-teal">{{ \App\Support\Money::format($summary['paid'], $schedule->currency) }}</td>
                            <td class="py-3 pr-4">
                                @if ($summary['overdue_count'] > 0)
                                    <span class="badge-danger">{{ $summary['overdue_count'] }}</span>
                                @else
                                    <span class="text-navy-400">0</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="card">
            <h3 class="font-heading font-semibold text-navy-900 mb-4">Obligations</h3>
            <table class="w-full text-left text-sm">
                <thead>
                    <tr class="text-navy-400 border-b border-navy-50">
                        <th class="py-2 pr-4">Member</th>
                        <th class="py-2 pr-4">Period</th>
                        <th class="py-2 pr-4">Due</th>
                        <th class="py-2 pr-4">Amount</th>
                        <th class="py-2 pr-4">Paid</th>
                        <th class="py-2 pr-4">Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($obligations as $obligation)
                        <tr class="border-b border-navy-50 last:border-0">
                            <td class="py-3 pr-4 font-medium text-navy-900">{{ $obligation->user->name }}</td>
                            <td class="py-3 pr-4 text-navy-400">
                                {{ $obligation->period_start->format('d M Y') }}
                                @if (!$obligation->period_start->eq($obligation->period_end))
                                    &ndash; {{ $obligation->period_end->format('d M Y') }}
                                @endif
                            </td>
                            <td class="py-3 pr-4 text-navy-400">{{ $obligation->due_at->timezone($schedule->timezone)->format('d M Y H:i') }}</td>
                            <td class="py-3 pr-4">{{ \App\Support\Money::format($obligation->amount_due_cents, $schedule->currency) }}</td>
                            <td class="py-3 pr-4">{{ \App\Support\Money::format($obligation->amount_paid_cents, $schedule->currency) }}</td>
                            <td class="py-3 pr-4"><x-status-badge :status="$obligation->status" /></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="mt-4">{{ $obligations->links() }}</div>
        </div>
    </div>
</x-chama-layout>
