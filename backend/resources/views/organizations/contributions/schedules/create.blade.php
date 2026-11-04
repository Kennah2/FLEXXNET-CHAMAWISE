<x-chama-layout :organization="$organization" active="contributions">
    <x-slot name="header">
        <h2 class="font-heading font-bold text-2xl text-navy-900">New contribution schedule</h2>
        <p class="text-navy-400 mt-1">{{ $organization->name }}</p>
    </x-slot>

    <div class="max-w-3xl">
        <div class="card">
            <form method="POST" action="{{ route('organizations.contributions.schedules.store', $organization) }}" class="space-y-6" x-data="{ appliesTo: 'all' }">
                @csrf

                <div>
                    <x-input-label for="name" value="Schedule name" />
                    <x-text-input id="name" name="name" type="text" class="mt-1 block w-full" :value="old('name')" placeholder="e.g. Daily savings" required autofocus />
                    <x-input-error :messages="$errors->get('name')" class="mt-2" />
                </div>

                <div>
                    <x-input-label for="description" value="Description (optional)" />
                    <textarea id="description" name="description" rows="2" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">{{ old('description') }}</textarea>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="frequency" value="Frequency" />
                        <select id="frequency" name="frequency" class="mt-1 block w-full rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            @foreach (\App\Models\ContributionSchedule::FREQUENCIES as $frequency)
                                <option value="{{ $frequency }}" @selected(old('frequency') === $frequency)>{{ ucfirst($frequency) }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-navy-400 mt-1">"Custom" schedules don't auto-generate obligations.</p>
                    </div>
                    <div>
                        <x-input-label for="amount" value="Amount per member ({{ $organization->currency }})" />
                        <x-text-input id="amount" name="amount" type="text" inputmode="decimal" class="mt-1 block w-full" :value="old('amount')" placeholder="100.00" required />
                        <x-input-error :messages="$errors->get('amount')" class="mt-2" />
                    </div>
                </div>

                <div class="grid sm:grid-cols-2 gap-4">
                    <div>
                        <x-input-label for="start_date" value="Start date" />
                        <x-text-input id="start_date" name="start_date" type="date" class="mt-1 block w-full" :value="old('start_date')" required />
                        <x-input-error :messages="$errors->get('start_date')" class="mt-2" />
                    </div>
                    <div>
                        <x-input-label for="end_date" value="End date (optional)" />
                        <x-text-input id="end_date" name="end_date" type="date" class="mt-1 block w-full" :value="old('end_date')" />
                        <x-input-error :messages="$errors->get('end_date')" class="mt-2" />
                    </div>
                </div>

                <div class="grid sm:grid-cols-3 gap-4">
                    <div>
                        <x-input-label for="deadline_time" value="Deadline time" />
                        <x-text-input id="deadline_time" name="deadline_time" type="time" class="mt-1 block w-full" :value="old('deadline_time', '23:59')" required />
                    </div>
                    <div>
                        <x-input-label for="grace_period_days" value="Grace period (days)" />
                        <x-text-input id="grace_period_days" name="grace_period_days" type="number" min="0" max="30" class="mt-1 block w-full" :value="old('grace_period_days', 0)" required />
                    </div>
                    <div>
                        <x-input-label for="penalty_amount" value="Missed-payment penalty ({{ $organization->currency }})" />
                        <x-text-input id="penalty_amount" name="penalty_amount" type="text" inputmode="decimal" class="mt-1 block w-full" :value="old('penalty_amount', '0')" />
                    </div>
                </div>

                <div>
                    <x-input-label value="Applies to" />
                    <div class="mt-2 flex gap-6">
                        <label class="flex items-center gap-2 text-sm text-navy-700">
                            <input type="radio" name="applies_to" value="all" x-model="appliesTo" checked>
                            All current members
                        </label>
                        <label class="flex items-center gap-2 text-sm text-navy-700">
                            <input type="radio" name="applies_to" value="selected" x-model="appliesTo">
                            Selected members
                        </label>
                    </div>
                </div>

                <div x-show="appliesTo === 'selected'" x-cloak>
                    <x-input-label value="Members" />
                    <div class="mt-2 grid sm:grid-cols-2 gap-2 max-h-48 overflow-y-auto border border-navy-50 rounded-lg p-3">
                        @foreach ($members as $member)
                            <label class="flex items-center gap-2 text-sm text-navy-700">
                                <input type="checkbox" name="member_ids[]" value="{{ $member->id }}">
                                {{ $member->name }}
                            </label>
                        @endforeach
                    </div>
                </div>

                <div class="flex justify-end gap-3">
                    <a href="{{ route('organizations.contributions.schedules.index', $organization) }}" class="btn-secondary">Cancel</a>
                    <button type="submit" class="btn-primary">Create schedule</button>
                </div>
            </form>
        </div>
    </div>
</x-chama-layout>
