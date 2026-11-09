@php
    $theme = \App\Domain\Theme::current();
@endphp
<!doctype html>
<html lang="en" class="{{ $theme->isDark() ? 'dark' : '' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta http-equiv="X-UA-Compatible" content="ie=edge" />
    <meta name="description" content="nibl.co.uk provides direct access to large number of XDCC IRC Bots." />
    <title>@yield('title') | nibl.co.uk</title>
    @vite(['resources/css/app.css'])
</head>
<body class="text-gray-700 antialiased bg-gray-100 font-sans dark:text-gray-100 dark:bg-gray-700">
    <nav class="bg-gray-800">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                <div class="flex items-center">
                    <div class="shrink-0">
                        <a href="/site/view/legacy" title="Switch to the legacy site"><img width="24" height="24" class="h-12 w-12" src="/img/penguin.svg" alt="NIBL"></a>
                        <div class="hidden">Icons made by <a href="https://www.flaticon.com/authors/freepik" title="Freepik">Freepik</a> from <a href="https://www.flaticon.com/" title="Flaticon">www.flaticon.com</a></div>
                    </div>
                    <div class="hidden md:block">
                        <div class="ml-10 flex items-center space-x-4">
                            <a href="/" class="{{ request()->routeIs('index') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">Home</a>
                            <a href="/bots" class="{{ request()->routeIs('bots', 'bot') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">Bots</a>
                            <a href="/search" class="{{ request()->routeIs('search') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">Search</a>
                            <a href="/about" class="{{ request()->routeIs('about') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">About us</a>
                            <a href="/faq" class="{{ request()->routeIs('faq') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">FAQ</a>
                            <a href="https://discord.gg/bUESsAg" class="text-gray-300 hover:bg-gray-700 hover:text-white px-3 py-2 rounded-md text-base font-medium flex items-center">
                                <img width="12" height="12" class="w-8 h-8 block items-baseline" src="/img/discord.svg" alt="Discord">
                            </a>
                        </div>
                    </div>
                </div>
                <div class="hidden md:block">
                    <div class="ml-4 flex items-center md:ml-6">
                        @include('partials.theme-toggle', ['theme' => $theme])
                        <a class="bg-indigo-700 hover:bg-indigo-600 text-white font-bold p-2 rounded-sm" xmlns="http://www.w3.org/1999/xhtml" href="irc://irc.rizon.net/nibl">#nibl @ irc.rizon.net</a>
                    </div>
                </div>
            </div>
        </div>

        <!--
          Mobile menu; always visible on small screens when JS is off.
        -->
        <div id="mobile-menu" class="md:hidden">
            <div class="px-2 pt-2 pb-3 space-y-1 sm:px-3">
                <a href="/" class="{{ request()->routeIs('index') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">Home</a>
                <a href="/bots" class="{{ request()->routeIs('bots', 'bot') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">Bots</a>
                <a href="/search" class="{{ request()->routeIs('search') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">Search</a>
                <a href="/about" class="{{ request()->routeIs('about') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">About us</a>
                <a href="/faq" class="{{ request()->routeIs('faq') ? 'bg-gray-900 text-white block px-3 py-2 rounded-md text-base font-medium' : 'text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium' }}">FAQ</a>
                <a href="https://discord.gg/bUESsAg" class="text-gray-300 hover:bg-gray-700 hover:text-white block px-3 py-2 rounded-md text-base font-medium">Discord</a>
                <div class="pt-2">
                    @include('partials.theme-toggle', ['theme' => $theme])
                </div>
            </div>
        </div>
    </nav>

    <header class="bg-white shadow-sm dark:bg-gray-900">
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            <h1 class="text-3xl font-bold leading-tight text-gray-900 dark:text-gray-100">
                @yield('header')
            </h1>
        </div>
    </header>
    <main>
        <div class="max-w-7xl mx-auto py-6 px-4 sm:px-6 lg:px-8">
            @yield('content')
        </div>
    </main>
    <footer class="text-center mt-6">
        <p class="text-sm text-gray-400 dark:text-gray-200">nibl.co.uk © 2020</p>
        <div class="mt-2 text-sm text-gray-400 dark:text-gray-200">
            <a href="{{ route('site.js', 'on') }}">Enable JavaScript</a>
        </div>
    </footer>
</body>
</html>