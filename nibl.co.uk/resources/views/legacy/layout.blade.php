<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>:: @yield('title', 'nibl') ::</title>
    @inject('legacyTheme', 'App\Infrastructure\Service\LegacyThemeService')
    <link rel="stylesheet" href="/legacy/reset.css" type="text/css">
    <link rel="stylesheet" href="/legacy/jquery-ui.css" type="text/css">
    <link rel="stylesheet" href="/legacy/jquery-ui.structure.css" type="text/css">
    <link rel="stylesheet" href="/legacy/jquery-ui.theme.css" type="text/css">
    <link rel="stylesheet" href="/legacy/{{ $legacyTheme->stylesheet(request()->cookie('theme')) }}" type="text/css">
    <script src="/legacy/js/jquery-3.2.1.min.js"></script>
    <script src="/legacy/js/jquery-ui.js"></script>
    <script src="/legacy/js/jquery.cookie.js"></script>
</head>
<body>
<div id="wrapper">
    <div id="header">
        <h1><a href="{{ route('site.view', 'modern') }}" title="Switch to nibl.co.uk">nibl</a></h1>
        <h3><a href="irc://irc.rizon.net/nibl">#nibl @ irc.rizon.net</a></h3>
        <ul id="navigation">
            <li><a href="/" class="{{ request()->routeIs('index') ? 'selected' : '' }}">News</a></li>
            <li><a href="/bots" class="{{ request()->routeIs('bots') ? 'selected' : '' }}">Bots</a></li>
            <li><a href="/irc" class="{{ request()->routeIs('legacy.irc') ? 'selected' : '' }}">IRC</a></li>
            <li><a href="/guides" class="{{ request()->routeIs('legacy.guides') ? 'selected' : '' }}">Guides</a></li>
            <li><a href="/links" class="{{ request()->routeIs('legacy.links') ? 'selected' : '' }}">Links</a></li>
            <li><a href="/staff" class="{{ request()->routeIs('legacy.staff') ? 'selected' : '' }}">Staff</a></li>
        </ul>
    </div>
    <div id="content_edge">
        @yield('content')
    </div>
</div>
<div id="footer">
    <div class="right">
        Theme:
        <select class="themes" onchange="$.cookie('theme', this.value, { expires: 3652 });location.reload()">
            @foreach ($legacyTheme->themes() as $name => $theme)
                <option value="{{ $name }}" @selected($name === $legacyTheme->current(request()->cookie('theme')))>{{ $theme['label'] }}</option>
            @endforeach
        </select>
    </div>
    nibl.co.uk
</div>
<div id="popup" title="Paste this into your IRC client:" style="display:none;"><textarea id="popuptext" rows="4" cols="25"></textarea></div>
@yield('scripts')
</body>
</html>
