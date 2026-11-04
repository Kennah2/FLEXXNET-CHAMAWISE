<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <div>
                <h2 class="font-heading font-bold text-xl text-navy-900 leading-tight">{{ $organization->name }}</h2>
                <p class="text-sm text-navy-400">{{ \Illuminate\Support\Str::headline($organization->group_type) }} &middot; {{ $organization->currency }}</p>
            </div>
            <a href="{{ route('organizations.members.index', $organization) }}" class="btn-secondary">Manage members</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="card border-l-4 border-brand-teal text-sm text-navy-900">{{ session('status') }}</div>
            @endif

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
                <div class="card">
                    <p class="text-sm text-navy-400">Members</p>
                    <p class="text-2xl font-heading font-bold text-navy-900">{{ $organization->members->count() }}</p>
                </div>
                <div class="card">
                    <p class="text-sm text-navy-400">Status</p>
                    <p class="text-2xl font-heading font-bold text-brand-teal">{{ ucfirst($organization->status) }}</p>
                </div>
                <div class="card">
                    <p class="text-sm text-navy-400">Formed</p>
                    <p class="text-2xl font-heading font-bold text-navy-900">{{ $organization->formation_date?->format('M Y') ?? '—' }}</p>
                </div>
            </div>

            @if ($organization->description)
                <div class="card">
                    <h3 class="font-heading font-semibold text-navy-900 mb-2">About</h3>
                    <p class="text-navy-700">{{ $organization->description }}</p>
                </div>
            @endif

            <div class="card">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-heading font-semibold text-navy-900">Members</h3>
                    <a href="{{ route('organizations.members.index', $organization) }}" class="text-sm text-brand-pink hover:underline">View all &rarr;</a>
                </div>
                <ul class="divide-y divide-navy-50">
                    @foreach ($organization->members->take(5) as $member)
                        <li class="py-3 flex items-center justify-between">
                            <div>
                                <p class="font-medium text-navy-900">{{ $member->name }}</p>
                                <p class="text-sm text-navy-400">Member #{{ $member->pivot->membership_number }}</p>
                            </div>
                            <span class="badge-success">{{ ucfirst($member->pivot->status) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card bg-navy-900 text-white">
                <p class="text-sm text-navy-100">
                    Contribution schedules, savings tracking, payments and financial reporting are
                    built out in the next development phase — this is the Phase 1 foundation:
                    membership, roles and chama administration.
                </p>
            </div>
        </div>
    </div>
</x-app-layout>
