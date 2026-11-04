<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" x-data="{ collapsed: false }">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ isset($title) ? $title.' - ' : '' }}{{ $organization->name }} - {{ config('app.name', 'CHAMA WISE') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/brand/chama-wise-icon.png') }}">

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-surface-light">
        <div class="min-h-screen flex">
            <!-- Sidebar -->
            <aside
                class="bg-white border-r border-navy-50 flex flex-col shrink-0 transition-all duration-150"
                :class="collapsed ? 'w-20' : 'w-64'"
            >
                <div class="h-16 flex items-center gap-2 px-4 border-b border-navy-50 overflow-hidden">
                    <a href="{{ route('dashboard') }}" class="flex items-center gap-2 shrink-0">
                        <x-application-logo class="h-8 w-8 shrink-0" />
                        <span class="font-heading font-bold text-navy-900 whitespace-nowrap" x-show="!collapsed">CHAMA <span class="text-brand-pink">WISE</span></span>
                    </a>
                </div>

                <div class="px-4 pt-4 pb-2" x-show="!collapsed">
                    <p class="text-xs font-semibold tracking-wide text-navy-400 uppercase">Current chama</p>
                    <p class="text-sm font-semibold text-navy-900 truncate mt-0.5">{{ $organization->name }}</p>
                </div>

                <nav class="flex-1 overflow-y-auto px-3 py-2 space-y-1">
                    @php
                        $navItems = [
                            ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home', 'route' => route('organizations.show', $organization)],
                            ['key' => 'members', 'label' => 'Members', 'icon' => 'users', 'route' => route('organizations.members.index', $organization)],
                            ['key' => 'contributions', 'label' => 'Contributions', 'icon' => 'currency', 'route' => route('organizations.module.show', [$organization, 'contributions'])],
                            ['key' => 'loans', 'label' => 'Loans', 'icon' => 'calculator', 'route' => route('organizations.module.show', [$organization, 'loans'])],
                            ['key' => 'accounts', 'label' => 'Accounts', 'icon' => 'card', 'route' => route('organizations.module.show', [$organization, 'accounts'])],
                            ['key' => 'transactions', 'label' => 'Transactions', 'icon' => 'list', 'route' => route('organizations.module.show', [$organization, 'transactions'])],
                            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'route' => route('organizations.module.show', [$organization, 'reports'])],
                            ['key' => 'accounting', 'label' => 'Accounting', 'icon' => 'book', 'route' => route('organizations.module.show', [$organization, 'accounting'])],
                            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'cog', 'route' => route('organizations.module.show', [$organization, 'settings'])],
                        ];
                    @endphp

                    @foreach ($navItems as $item)
                        <a
                            href="{{ $item['route'] }}"
                            title="{{ $item['label'] }}"
                            class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition {{ $active === $item['key'] ? 'bg-navy-900 text-white' : 'text-navy-700 hover:bg-surface-light' }}"
                        >
                            <x-icon :name="$item['icon']" class="h-5 w-5 shrink-0" />
                            <span x-show="!collapsed" class="whitespace-nowrap">{{ $item['label'] }}</span>
                        </a>
                    @endforeach
                </nav>

                <div class="px-3 py-3 border-t border-navy-50 space-y-1">
                    <a href="{{ route('profile.edit') }}" class="flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-navy-700 hover:bg-surface-light transition" title="Profile">
                        <span class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full bg-navy-100 text-[10px] font-bold text-navy-900">{{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}</span>
                        <span x-show="!collapsed" class="whitespace-nowrap truncate">{{ auth()->user()->name }}</span>
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="w-full flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-red-600 hover:bg-red-50 transition" title="Logout">
                            <x-icon name="logout" class="h-5 w-5 shrink-0" />
                            <span x-show="!collapsed">Logout</span>
                        </button>
                    </form>
                    <button @click="collapsed = !collapsed" class="w-full flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium text-navy-400 hover:bg-surface-light transition" title="Toggle sidebar">
                        <x-icon name="collapse" class="h-5 w-5 shrink-0 transition-transform duration-150" x-bind:class="{ 'rotate-180': collapsed }" />
                        <span x-show="!collapsed">Collapse</span>
                    </button>
                </div>
            </aside>

            <!-- Main content -->
            <div class="flex-1 min-w-0">
                @isset($header)
                    <div class="px-6 sm:px-10 pt-8 pb-2">
                        {{ $header }}
                    </div>
                @endisset

                <main class="px-6 sm:px-10 pb-12">
                    @if (session('status'))
                        <div class="card border-l-4 border-brand-teal text-sm text-navy-900 mb-6">{{ session('status') }}</div>
                    @endif
                    @if (session('error'))
                        <div class="card border-l-4 border-red-500 text-sm text-navy-900 mb-6">{{ session('error') }}</div>
                    @endif
                    @if (session('invite_url'))
                        <div class="card border-l-4 border-brand-pink text-sm text-navy-900 mb-6">
                            <p class="font-semibold mb-1">Invitation created.</p>
                            <p class="text-navy-400 mb-2">Automated email delivery isn't wired up yet — share this link with the invitee directly:</p>
                            <code class="block bg-surface-light rounded p-2 text-xs break-all">{{ session('invite_url') }}</code>
                        </div>
                    @endif

                    {{ $slot }}
                </main>
            </div>
        </div>
    </body>
</html>
