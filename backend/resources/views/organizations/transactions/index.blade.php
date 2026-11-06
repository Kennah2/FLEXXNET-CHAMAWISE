<x-chama-layout :organization="$organization" active="transactions">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">{{ $organization->name }}</h2>
        <p class="text-navy-400 mt-1">Transactions</p>
    </x-slot>

    <div class="card">
        @forelse ($entries as $entry)
            <div class="py-4 border-b border-navy-50 last:border-0">
                <div class="flex items-center justify-between">
                    <div>
                        <p class="font-medium text-navy-900 text-sm">
                            {{ $entry->description }}
                            @if ($entry->status === 'reversed')
                                <span class="badge-danger ml-2">Reversed</span>
                            @endif
                            @if ($entry->reverses_journal_entry_id)
                                <span class="badge-neutral ml-2">Reversal</span>
                            @endif
                        </p>
                        <p class="text-xs text-navy-400">{{ $entry->posted_at->format('d M Y H:i') }} &middot; recorded by {{ $entry->creator->name }}</p>
                    </div>
                </div>
                <table class="w-full text-left text-xs mt-3">
                    <tbody>
                        @foreach ($entry->postings as $posting)
                            <tr>
                                <td class="py-1 pr-4 text-navy-700">
                                    {{ $posting->ledgerAccount->name }}
                                    @if ($posting->user)
                                        <span class="text-navy-400">({{ $posting->user->name }})</span>
                                    @endif
                                </td>
                                <td class="py-1 pr-4 text-right text-brand-teal w-28">{{ $posting->debit_cents > 0 ? \App\Support\Money::format($posting->debit_cents, $organization->currency) : '' }}</td>
                                <td class="py-1 pr-4 text-right text-red-600 w-28">{{ $posting->credit_cents > 0 ? \App\Support\Money::format($posting->credit_cents, $organization->currency) : '' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @empty
            <p class="text-navy-400 text-center py-8">No transactions posted yet.</p>
        @endforelse
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
</x-chama-layout>
