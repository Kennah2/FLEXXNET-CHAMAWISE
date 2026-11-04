<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-heading font-bold text-xl text-navy-900 leading-tight">{{ __('My Chamas') }}</h2>
            <a href="{{ route('organizations.create') }}" class="btn-primary">+ Start a chama</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
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
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
