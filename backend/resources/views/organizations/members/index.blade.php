<x-app-layout>
    <x-slot name="header">
        <div class="flex items-center justify-between">
            <h2 class="font-heading font-bold text-xl text-navy-900 leading-tight">{{ $organization->name }} &middot; Members</h2>
            <a href="{{ route('organizations.show', $organization) }}" class="btn-secondary">Back to chama</a>
        </div>
    </x-slot>

    <div class="py-12">
        <div class="max-w-5xl mx-auto sm:px-6 lg:px-8 space-y-6">
            @if (session('status'))
                <div class="card border-l-4 border-brand-teal text-sm text-navy-900">{{ session('status') }}</div>
            @endif

            @if (session('invite_url'))
                <div class="card border-l-4 border-brand-pink text-sm text-navy-900">
                    <p class="font-semibold mb-1">Invitation created.</p>
                    <p class="text-navy-400 mb-2">Automated email delivery isn't wired up yet — share this link with the invitee directly:</p>
                    <code class="block bg-surface-light rounded p-2 text-xs break-all">{{ session('invite_url') }}</code>
                </div>
            @endif

            @can('create', [\App\Models\Invitation::class, $organization])
                <div class="card">
                    <h3 class="font-heading font-semibold text-navy-900 mb-4">Invite a member</h3>
                    <form method="POST" action="{{ route('organizations.invitations.store', $organization) }}" class="flex flex-col sm:flex-row gap-3">
                        @csrf
                        <input type="email" name="email" placeholder="email@example.com" class="flex-1 rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink" required>
                        <select name="role" class="rounded-md border-navy-100 shadow-sm focus:border-brand-pink focus:ring-brand-pink">
                            @foreach (array_keys(\App\Support\ChamaPermissions::DEFAULT_ROLE_PERMISSIONS) as $role)
                                <option value="{{ $role }}" @selected($role === 'member')>{{ ucfirst($role) }}</option>
                            @endforeach
                        </select>
                        <button type="submit" class="btn-primary">Send invite</button>
                    </form>
                    @error('email')<p class="text-sm text-red-600 mt-2">{{ $message }}</p>@enderror
                </div>
            @endcan

            <div class="card overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead>
                        <tr class="text-navy-400 border-b border-navy-50">
                            <th class="py-2 pr-4">#</th>
                            <th class="py-2 pr-4">Name</th>
                            <th class="py-2 pr-4">Status</th>
                            <th class="py-2 pr-4">Joined</th>
                            @can('manageMembers', $organization)
                                <th class="py-2 pr-4">Actions</th>
                            @endcan
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($memberships as $membership)
                            <tr class="border-b border-navy-50 last:border-0">
                                <td class="py-3 pr-4 text-navy-400">{{ $membership->membership_number }}</td>
                                <td class="py-3 pr-4 font-medium text-navy-900">{{ $membership->user->name }}</td>
                                <td class="py-3 pr-4">
                                    <span class="{{ $membership->status === 'active' ? 'badge-success' : 'badge-pending' }}">
                                        {{ ucfirst($membership->status) }}
                                    </span>
                                </td>
                                <td class="py-3 pr-4 text-navy-400">{{ $membership->joined_at?->format('d M Y') ?? '—' }}</td>
                                @can('manageMembers', $organization)
                                    <td class="py-3 pr-4">
                                        @if ($membership->user_id !== auth()->id())
                                            <form method="POST" action="{{ route('organizations.members.update-status', [$organization, $membership]) }}" class="flex gap-2">
                                                @csrf
                                                @method('PATCH')
                                                <select name="status" class="rounded-md border-navy-100 text-xs shadow-sm">
                                                    <option value="active" @selected($membership->status === 'active')>Active</option>
                                                    <option value="suspended" @selected($membership->status === 'suspended')>Suspended</option>
                                                    <option value="removed" @selected($membership->status === 'removed')>Removed</option>
                                                </select>
                                                <button type="submit" class="text-xs text-brand-pink hover:underline">Update</button>
                                            </form>
                                        @endif
                                    </td>
                                @endcan
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</x-app-layout>
