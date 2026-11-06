<x-chama-layout :organization="$organization" active="accounting">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">M-Pesa (Daraja) settings</h2>
        <p class="text-navy-400 mt-1">{{ $organization->name }}</p>
    </x-slot>

    <div class="max-w-xl space-y-6">
        <div class="card">
            <p class="text-sm text-navy-700">
                This chama collects M-Pesa STK Push payments through <strong>its own</strong> Safaricom
                Daraja app — never a shared CHAMA WISE till. Money goes straight to the shortcode
                below; CHAMA WISE only asks Safaricom to send the prompt and records what comes back.
            </p>
        </div>

        @if ($accounts->isEmpty())
            <div class="card border-l-4 border-amber-500">
                <p class="text-sm text-navy-700">Create a financial account first (e.g. your M-Pesa Till) before M-Pesa collection can be enabled.</p>
                <a href="{{ route('organizations.accounts.create', $organization) }}" class="btn-secondary mt-3 inline-flex">Add a financial account</a>
            </div>
        @else
            <div class="card">
                <form method="POST" action="{{ route('organizations.payments.daraja.update', $organization) }}" class="space-y-6">
                    @csrf
                    @method('PUT')

                    <div>
                        <x-input-label for="environment" value="Environment" />
                        <select id="environment" name="environment" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            <option value="sandbox" @selected(old('environment', $credential?->environment ?? 'sandbox') === 'sandbox')>Sandbox (testing)</option>
                            <option value="production" @selected(old('environment', $credential?->environment) === 'production')>Production (live)</option>
                        </select>
                        <x-input-error :messages="$errors->get('environment')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="shortcode" value="Business shortcode (Paybill / Till number)" />
                        <x-text-input id="shortcode" name="shortcode" type="text" class="mt-1 block w-full" :value="old('shortcode', $credential?->shortcode)" required />
                        <x-input-error :messages="$errors->get('shortcode')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="consumer_key" :value="$credential?->consumer_key ? 'Consumer key (saved — leave blank to keep it)' : 'Consumer key'" />
                        <x-text-input id="consumer_key" name="consumer_key" type="password" autocomplete="off" class="mt-1 block w-full" placeholder="{{ $credential?->consumer_key ? '••••••••' : '' }}" />
                        <x-input-error :messages="$errors->get('consumer_key')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="consumer_secret" :value="$credential?->consumer_secret ? 'Consumer secret (saved — leave blank to keep it)' : 'Consumer secret'" />
                        <x-text-input id="consumer_secret" name="consumer_secret" type="password" autocomplete="off" class="mt-1 block w-full" placeholder="{{ $credential?->consumer_secret ? '••••••••' : '' }}" />
                        <x-input-error :messages="$errors->get('consumer_secret')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="passkey" :value="$credential?->passkey ? 'Passkey (saved — leave blank to keep it)' : 'Passkey'" />
                        <x-text-input id="passkey" name="passkey" type="password" autocomplete="off" class="mt-1 block w-full" placeholder="{{ $credential?->passkey ? '••••••••' : '' }}" />
                        <x-input-error :messages="$errors->get('passkey')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="financial_account_id" value="Post confirmed payments to" />
                        <select id="financial_account_id" name="financial_account_id" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            @foreach ($accounts as $account)
                                <option value="{{ $account->id }}" @selected(old('financial_account_id', $credential?->financial_account_id) == $account->id)>{{ $account->name }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('financial_account_id')" class="mt-2" />
                    </div>

                    <label class="flex items-center gap-2 text-sm text-navy-700">
                        <input type="checkbox" name="is_enabled" value="1" @checked(old('is_enabled', $credential?->is_enabled))>
                        Enable M-Pesa collection for members
                    </label>
                    <p class="text-xs text-navy-400 -mt-4">Requires consumer key, consumer secret, shortcode, passkey and an account all to be set.</p>

                    <div class="flex justify-end">
                        <button type="submit" class="btn-primary">Save M-Pesa settings</button>
                    </div>
                </form>
            </div>

            @if ($callbackUrl)
                <div class="card">
                    <h3 class="font-heading font-semibold text-navy-900 mb-2">Callback URL</h3>
                    <p class="text-sm text-navy-400 mb-3">Paste this into your Daraja app's STK Push callback configuration on the Safaricom developer portal.</p>
                    <code class="block bg-navy-50 text-navy-900 text-xs rounded-md p-3 break-all">{{ $callbackUrl }}</code>
                </div>
            @endif
        @endif
    </div>
</x-chama-layout>
