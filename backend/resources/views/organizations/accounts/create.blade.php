<x-chama-layout :organization="$organization" active="accounts">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">New account</h2>
        <p class="text-navy-400 mt-1">{{ $organization->name }}</p>
    </x-slot>

    <div class="max-w-xl">
        <div class="card">
            <form method="POST" action="{{ route('organizations.accounts.store', $organization) }}" class="space-y-6">
                @csrf

                <div>
                    <x-input-label for="name" value="Account name" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. Equity Bank, M-Pesa Till" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="type" value="Type" />
                    <select id="type" name="type" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                        @foreach (\App\Models\FinancialAccount::TYPES as $type)
                            <option value="{{ $type }}" @selected(old('type') === $type)>{{ ucfirst(str_replace('_', ' ', $type)) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get('type')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="account_number" value="Account / till number (optional)" />
                    <x-text-input id="account_number" name="account_number" type="text" class="mt-1 block w-full" :value="old('account_number')" />
                </div>

                <div>
                    <x-input-label for="opening_balance" value="Opening balance ({{ $organization->currency }})" />
                    <x-text-input id="opening_balance" name="opening_balance" type="text" inputmode="decimal" class="mt-1 block w-full" :value="old('opening_balance', '0')" />
                    <p class="text-xs text-navy-400 mt-1">Whatever's already in this account before CHAMA WISE starts tracking it.</p>
                    <x-input-error :messages="$errors->get('opening_balance')" class="mt-2" />
                </div>

                <label class="flex items-center gap-2 text-sm text-navy-700">
                    <input type="checkbox" name="is_default" value="1">
                    Make this the default account
                </label>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('organizations.accounts.index', $organization) }}" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Create account</button>
                </div>
            </form>
        </div>
    </div>
</x-chama-layout>
