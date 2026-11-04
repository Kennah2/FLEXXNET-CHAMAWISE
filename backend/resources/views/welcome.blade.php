<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'CHAMA WISE') }} — Save Together. Grow Together.</title>
        <meta name="description" content="CHAMA WISE helps Kenyan chamas, savings groups and investment groups manage members, contributions, finances and reporting — on the web and on Android.">
        <link rel="icon" type="image/png" href="{{ asset('images/brand/chama-wise-icon.png') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased text-navy-900 bg-white">
        <!-- Nav -->
        <header class="border-b border-navy-50">
            <div class="max-w-7xl mx-auto px-6 py-4 flex items-center justify-between">
                <a href="/" class="flex items-center gap-2">
                    <x-application-logo class="h-9 w-9" />
                    <span class="font-heading font-bold text-lg">CHAMA <span class="text-brand-pink">WISE</span></span>
                </a>
                <nav class="hidden md:flex items-center gap-8 text-sm font-medium text-navy-700">
                    <a href="#features" class="hover:text-brand-pink">Features</a>
                    <a href="#how-it-works" class="hover:text-brand-pink">How it works</a>
                    <a href="#groups" class="hover:text-brand-pink">Who it's for</a>
                </nav>
                <div class="flex items-center gap-3">
                    @auth
                        <a href="{{ route('dashboard') }}" class="btn-primary">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="text-navy-700 hover:text-brand-pink text-sm font-medium">Log in</a>
                        <a href="{{ route('register') }}" class="btn-primary">Get started</a>
                    @endauth
                </div>
            </div>
        </header>

        <!-- Hero -->
        <section class="bg-navy-900 text-white">
            <div class="max-w-7xl mx-auto px-6 py-20 grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <span class="inline-flex items-center rounded-full bg-white/10 px-3 py-1 text-xs font-medium text-navy-100">A product by FlexxNet Technologies</span>
                    <h1 class="font-heading font-extrabold text-4xl sm:text-5xl leading-tight mt-5">
                        Save Together.<br><span class="text-brand-pink">Grow Together.</span>
                    </h1>
                    <p class="mt-6 text-lg text-navy-100 max-w-lg">
                        CHAMA WISE helps Kenyan chamas, savings groups, merry-go-round groups,
                        table banking groups and investment groups manage members, contributions,
                        finances and reporting — transparently, on the web and on Android.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ route('register') }}" class="btn-primary">Start your chama</a>
                        <a href="#features" class="btn-secondary !bg-transparent !text-white !border-navy-400">See how it works</a>
                    </div>
                </div>
                <div class="relative">
                    <div class="bg-white rounded-2xl p-6 shadow-xl">
                        <img src="{{ asset('images/brand/chama-wise-logo-full.webp') }}" alt="CHAMA WISE — Save Together. Grow Together." class="w-full">
                    </div>
                </div>
            </div>
        </section>

        <!-- Trust strip -->
        <section class="border-b border-navy-50">
            <div class="max-w-7xl mx-auto px-6 py-6 flex flex-wrap items-center justify-center gap-x-10 gap-y-3 text-sm font-medium text-navy-400">
                <span>Built for Kenyan chamas</span>
                <span class="h-1 w-1 rounded-full bg-navy-100 hidden sm:block"></span>
                <span>M-Pesa aware</span>
                <span class="h-1 w-1 rounded-full bg-navy-100 hidden sm:block"></span>
                <span>Multi-chama accounts</span>
                <span class="h-1 w-1 rounded-full bg-navy-100 hidden sm:block"></span>
                <span>Role-based access</span>
            </div>
        </section>

        <!-- Features -->
        <section id="features" class="max-w-7xl mx-auto px-6 py-20">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="font-heading font-bold text-3xl text-navy-900">Everything your chama needs in one place</h2>
                <p class="mt-4 text-navy-400">From member onboarding to financial reporting, built around how Kenyan savings groups actually operate.</p>
            </div>
            <div class="mt-14 grid sm:grid-cols-2 lg:grid-cols-3 gap-6">
                @php
                    $features = [
                        ['icon' => 'users', 'title' => 'Membership management', 'desc' => 'Invite members and assign roles per chama.'],
                        ['icon' => 'currency', 'title' => 'Contributions & savings', 'desc' => 'Daily, weekly or monthly schedules with deadlines and penalties.'],
                        ['icon' => 'card', 'title' => 'M-Pesa aware payments', 'desc' => 'Manual M-Pesa recording today, Daraja STK Push on the roadmap.'],
                        ['icon' => 'calculator', 'title' => 'Loans & table banking', 'desc' => 'Applications, guarantors, interest and repayment schedules.'],
                        ['icon' => 'book', 'title' => 'Transparent accounting', 'desc' => 'A proper double-entry ledger behind every number.'],
                        ['icon' => 'chart', 'title' => 'Reports & statements', 'desc' => 'Statements and reports your treasurer and auditor can trust.'],
                    ];
                @endphp
                @foreach ($features as $feature)
                    <div class="rounded-xl border border-navy-50 p-6 hover:shadow-md transition">
                        <span class="flex h-11 w-11 items-center justify-center rounded-lg bg-navy-900 text-white">
                            <x-icon :name="$feature['icon']" class="h-5 w-5" />
                        </span>
                        <h3 class="font-heading font-semibold text-navy-900 mt-4">{{ $feature['title'] }}</h3>
                        <p class="text-sm text-navy-400 mt-2">{{ $feature['desc'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        <!-- How it works -->
        <section id="how-it-works" class="bg-surface-light">
            <div class="max-w-7xl mx-auto px-6 py-20">
                <div class="text-center max-w-2xl mx-auto">
                    <h2 class="font-heading font-bold text-3xl text-navy-900">Get your chama running in minutes</h2>
                </div>
                <div class="mt-14 grid sm:grid-cols-3 gap-8">
                    @php
                        $steps = [
                            ['n' => '1', 'title' => 'Create your chama', 'desc' => 'You become the founding chairperson automatically.'],
                            ['n' => '2', 'title' => 'Invite your members', 'desc' => 'Send a secure invite link and assign each member a role.'],
                            ['n' => '3', 'title' => 'Track everything together', 'desc' => 'No spreadsheets, no guesswork.'],
                        ];
                    @endphp
                    @foreach ($steps as $step)
                        <div class="text-center">
                            <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-brand-pink text-white font-heading font-bold text-lg">{{ $step['n'] }}</div>
                            <h3 class="font-heading font-semibold text-navy-900 mt-4">{{ $step['title'] }}</h3>
                            <p class="text-sm text-navy-400 mt-2">{{ $step['desc'] }}</p>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <!-- Who it's for -->
        <section id="groups" class="max-w-7xl mx-auto px-6 py-20">
            <div class="text-center max-w-2xl mx-auto">
                <h2 class="font-heading font-bold text-3xl text-navy-900">Built for every kind of chama</h2>
            </div>
            <div class="mt-10 flex flex-wrap justify-center gap-3">
                @foreach (['Daily savings groups', 'Merry-go-round groups', 'Table banking groups', 'Investment chamas', 'Welfare groups', 'Professional investment groups'] as $group)
                    <span class="rounded-full border border-navy-100 px-4 py-2 text-sm font-medium text-navy-700">{{ $group }}</span>
                @endforeach
            </div>
        </section>

        <!-- CTA -->
        <section class="bg-navy-900">
            <div class="max-w-5xl mx-auto px-6 py-16 text-center">
                <h2 class="font-heading font-bold text-3xl text-white">Ready to save together?</h2>
                <p class="mt-3 text-navy-100">Create your chama's account in minutes — no spreadsheets, no guesswork.</p>
                <a href="{{ route('register') }}" class="btn-primary mt-8 inline-flex">Start your chama</a>
            </div>
        </section>

        <!-- Footer -->
        <footer class="border-t border-navy-50">
            <div class="max-w-7xl mx-auto px-6 py-10 flex flex-col sm:flex-row items-center justify-between gap-4">
                <div class="flex items-center gap-2">
                    <x-application-logo class="h-7 w-7" />
                    <span class="font-heading font-semibold text-sm">CHAMA WISE</span>
                </div>
                <p class="text-xs text-navy-400">&copy; {{ date('Y') }} FlexxNet Technologies. CHAMA WISE is under active development.</p>
            </div>
        </footer>
    </body>
</html>
