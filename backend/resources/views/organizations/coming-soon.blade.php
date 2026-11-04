<x-chama-layout :organization="$organization" :active="$module">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
        <p class="text-navy-400 mt-1">{{ $title }}</p>
    </x-slot>

    <div class="max-w-2xl">
        <div class="card text-center py-14">
            <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-navy-50 text-navy-900 mb-4">
                <x-icon :name="$icon" class="h-7 w-7" />
            </div>
            <h3 class="font-heading font-semibold text-xl text-navy-900">{{ $title }} isn't built yet</h3>
            <span class="badge-pending mt-3 inline-block">{{ $phase }}</span>
            <p class="text-navy-400 mt-4 max-w-md mx-auto">{{ $description }}</p>
            <a href="{{ route('organizations.show', $organization) }}" class="btn-secondary mt-6 inline-flex">Back to overview</a>
        </div>
    </div>
</x-chama-layout>
