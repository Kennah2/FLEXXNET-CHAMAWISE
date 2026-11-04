<x-chama-layout :organization="$organization" active="contributions">
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
                <p class="text-navy-400 mt-1">Contribution schedules</p>
            </div>
            <div class="flex gap-3">
                <a href="{{ route('organizations.contributions.index', $organization) }}" class="btn-secondary">Back to contributions</a>
                @can('create', [\App\Models\ContributionSchedule::class, $organization])
                    <a href="{{ route('organizations.contributions.schedules.create', $organization) }}" class="btn-primary">New schedule</a>
                @endcan
            </div>
        </div>
    </x-slot>

    @if ($schedules->isEmpty())
        <div class="card text-center py-12">
            <p class="text-navy-400">No contribution schedules yet.</p>
        </div>
    @else
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
            @foreach ($schedules as $schedule)
                <a href="{{ route('organizations.contributions.schedules.show', [$organization, $schedule]) }}" class="card hover:ring-brand-pink transition">
                    <div class="flex items-center justify-between">
                        <h3 class="font-heading font-semibold text-navy-900">{{ $schedule->name }}</h3>
                        <x-status-badge :status="$schedule->status" />
                    </div>
                    <p class="text-sm text-navy-400 mt-1">{{ ucfirst($schedule->frequency) }} &middot; {{ \App\Support\Money::format($schedule->amount_cents, $schedule->currency) }}</p>
                    <p class="text-xs text-navy-400 mt-3">Starts {{ $schedule->start_date->format('d M Y') }}@if($schedule->end_date) &middot; ends {{ $schedule->end_date->format('d M Y') }}@endif</p>
                </a>
            @endforeach
        </div>
    @endif
</x-chama-layout>
