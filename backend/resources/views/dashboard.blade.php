<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-bold text-xl text-navy-900 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="card border-l-4 border-brand-teal text-sm text-navy-900">
                    {{ session('status') }}
                </div>
            @endif

            <div class="flex items-center justify-between">
                <h3 class="font-heading font-semibold text-lg text-navy-900">Your chamas</h3>
                <a href="{{ route('organizations.create') }}" class="btn-primary">+ Start a chama</a>
            </div>

            @if ($organizations->isEmpty())
                <div class="card text-center py-12">
                    <p class="text-navy-400">You're not a member of any chama yet.</p>
                    <a href="{{ route('organizations.create') }}" class="btn-primary mt-4">Start your first chama</a>
                </div>
            @else
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    @foreach ($organizations as $organization)
                        <a href="{{ route('organizations.show', $organization) }}" class="card hover:ring-brand-pink transition block">
                            <div class="flex items-center justify-between">
                                <h4 class="font-heading font-semibold text-navy-900">{{ $organization->name }}</h4>
                                <span class="badge-success">{{ ucfirst($organization->status) }}</span>
                            </div>
                            <p class="text-sm text-navy-400 mt-1">{{ Str::headline($organization->group_type) }} &middot; {{ $organization->currency }}</p>
                            <p class="text-sm text-navy-400 mt-3">Member #{{ $organization->pivot->membership_number }}</p>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
