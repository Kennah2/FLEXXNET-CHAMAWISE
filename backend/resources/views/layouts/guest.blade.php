<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>{{ config('app.name', 'CHAMA WISE') }}</title>

        <link rel="icon" type="image/png" href="{{ asset('images/brand/chama-wise-icon.png') }}">

        <!-- Scripts -->
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="font-sans text-navy-900 antialiased">
        <div class="min-h-screen flex flex-col sm:justify-center items-center pt-6 sm:pt-0 bg-navy-900">
            <div class="text-center">
                <a href="/">
                    <x-application-logo variant="full" class="h-24 w-auto mx-auto" />
                </a>
                <p class="mt-2 text-sm font-medium text-navy-100">Save Together. Grow Together.</p>
            </div>

            <div class="w-full sm:max-w-md mt-6 px-6 py-4 bg-white shadow-md overflow-hidden sm:rounded-lg">
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
