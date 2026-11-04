<x-guest-layout>
    <div class="text-center mb-6">
        <h2 class="font-heading font-bold text-xl text-navy-900">You've been invited</h2>
        <p class="text-navy-400 mt-2">
            Join <strong>{{ $invitation->organization->name }}</strong> as a <strong>{{ ucfirst($invitation->role) }}</strong>.
        </p>
    </div>

    @if (session('error'))
        <div class="text-sm text-red-600 mb-4">{{ session('error') }}</div>
    @endif

    @auth
        @if (mb_strtolower(auth()->user()->email) === mb_strtolower($invitation->email))
            <form method="POST" action="{{ route('invitations.accept', $token) }}">
                @csrf
                <button type="submit" class="btn-primary w-full">Accept invitation</button>
            </form>
        @else
            <p class="text-sm text-navy-400 text-center">
                You're signed in as {{ auth()->user()->email }}, but this invitation was sent to
                {{ $invitation->email }}. Log out and sign in with that address to accept it.
            </p>
        @endif
    @else
        <p class="text-sm text-navy-400 text-center mb-4">Log in or create an account with <strong>{{ $invitation->email }}</strong> to accept.</p>
        <div class="flex gap-3">
            <a href="{{ route('login') }}" class="btn-secondary flex-1 text-center">Log in</a>
            <a href="{{ route('register') }}" class="btn-primary flex-1 text-center">Register</a>
        </div>
    @endauth
</x-guest-layout>
