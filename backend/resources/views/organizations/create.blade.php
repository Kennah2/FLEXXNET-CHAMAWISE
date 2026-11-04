<x-app-layout>
    <x-slot name="header">
        <h2 class="font-heading font-bold text-xl text-navy-900 leading-tight">{{ __('Start a Chama') }}</h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-2xl mx-auto sm:px-6 lg:px-8">
            <div class="card">
                <form method="POST" action="{{ route('organizations.store') }}" class="space-y-6">
                    @csrf

                    <div>
                        <x-input-label for="name" value="Chama name" />
                        <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" required autofocus />
                        <x-input-error :messages="$errors->get('name')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="group_type" value="Group type" />
                        <select id="group_type" name="group_type" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                            @foreach (\App\Models\Organization::GROUP_TYPES as $type)
                                <option value="{{ $type }}" @selected(old('group_type') === $type)>{{ \Illuminate\Support\Str::headline($type) }}</option>
                            @endforeach
                        </select>
                        <x-input-error :messages="$errors->get('group_type')" class="mt-2" />
                    </div>

                    <div>
                        <x-input-label for="description" value="Description (optional)" />
                        <textarea id="description" name="description" rows="3" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">{{ old('description') }}</textarea>
                        <x-input-error :messages="$errors->get('description')" class="mt-2" />
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="currency" value="Currency" />
                            <x-text-input id="currency" name="currency" type="text" class="mt-1 block w-full" :value="old('currency', 'KES')" maxlength="3" />
                            <x-input-error :messages="$errors->get('currency')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="formation_date" value="Formation date" />
                            <x-text-input id="formation_date" name="formation_date" type="date" class="mt-1 block w-full" :value="old('formation_date')" />
                            <x-input-error :messages="$errors->get('formation_date')" class="mt-2" />
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <x-input-label for="contact_email" value="Contact email (optional)" />
                            <x-text-input id="contact_email" name="contact_email" type="email" class="mt-1 block w-full" :value="old('contact_email')" />
                            <x-input-error :messages="$errors->get('contact_email')" class="mt-2" />
                        </div>
                        <div>
                            <x-input-label for="contact_phone" value="Contact phone (optional)" />
                            <x-text-input id="contact_phone" name="contact_phone" type="text" class="mt-1 block w-full" :value="old('contact_phone')" />
                            <x-input-error :messages="$errors->get('contact_phone')" class="mt-2" />
                        </div>
                    </div>

                    <div>
                        <x-input-label for="location" value="Location (optional)" />
                        <x-text-input id="location" name="location" type="text" class="mt-1 block w-full" :value="old('location')" />
                        <x-input-error :messages="$errors->get('location')" class="mt-2" />
                    </div>

                    <p class="text-sm text-navy-400">You'll be set as the founding chairperson.</p>

                    <div class="flex justify-end gap-3">
                        <a href="{{ route('organizations.index') }}" class="btn-secondary">Cancel</a>
                        <button type="submit" class="btn-primary">Create chama</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>
