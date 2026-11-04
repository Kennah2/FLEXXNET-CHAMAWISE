<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name', 'CHAMA WISE') }} — Save Together. Grow Together.</title>
        <meta name="description" content="CHAMA WISE helps Kenyan chamas, savings groups and investment groups manage members, contributions, finances and reporting — on the web and on Android.">
        <link rel="icon" type="image/webp" href="{{ asset('images/brand/chama-wise-icon.webp') }}">
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans antialiased bg-navy-900 text-white min-h-screen flex flex-col">
        <header class="max-w-6xl w-full mx-auto px-6 py-6 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <x-application-logo class="h-10 w-auto" />
                <span class="font-heading font-bold text-lg">CHAMA <span class="text-brand-pink">WISE</span></span>
            </div>
            <nav class="flex items-center gap-3">
                @auth
                    <a href="{{ route('dashboard') }}" class="btn-primary">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="text-navy-100 hover:text-white text-sm font-medium">Log in</a>
                    <a href="{{ route('register') }}" class="btn-primary">Get started</a>
                @endauth
            </nav>
        </header>

        <main class="flex-1 flex items-center">
            <div class="max-w-6xl w-full mx-auto px-6 py-16 grid md:grid-cols-2 gap-12 items-center">
                <div>
                    <h1 class="font-heading font-extrabold text-4xl sm:text-5xl leading-tight">
                        Save Together.<br><span class="text-brand-pink">Grow Together.</span>
                    </h1>
                    <p class="mt-6 text-lg text-navy-100">
                        CHAMA WISE helps Kenyan chamas, savings groups, merry-go-round groups,
                        table banking groups and investment groups manage members, contributions,
                        finances and reporting — transparently, on the web and on Android.
                    </p>
                    <div class="mt-8 flex gap-4">
                        <a href="{{ route('register') }}" class="btn-primary">Start your chama</a>
                        <a href="{{ route('login') }}" class="btn-secondary !bg-transparent !text-white !border-navy-400">Log in</a>
                    </div>
                    <p class="mt-8 text-xs text-navy-400">A product by FlexxNet Technologies.</p>
                </div>
                <div class="hidden md:flex justify-center">
                    <x-application-logo variant="full" class="w-full max-w-md" />
                </div>
            </div>
        </main>

        <footer class="max-w-6xl w-full mx-auto px-6 py-6 text-center text-xs text-navy-400">
            &copy; {{ date('Y') }} FlexxNet Technologies. CHAMA WISE is under active development.
        </footer>
    </body>
</html>
