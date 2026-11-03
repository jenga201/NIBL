@props(['total' => 0, 'next' => null, 'prev' => null, 'query' => '', 'showBot' => true, 'botName' => null])

@php
    $pageQuery = $query !== '' ? ['query' => $query] : [];
    $pageAction = $showBot
        ? route('search', $pageQuery)
        : route('bot', array_merge(['name' => $botName], $pageQuery));

    $navButton = 'h-10 px-4 shrink-0 flex items-center justify-center rounded-full bg-gray-800 hover:bg-gray-700 text-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800';
    $navButtonDisabled = $navButton.' disabled:opacity-40 disabled:cursor-not-allowed';
@endphp

@if ($next !== null || $prev !== null)
    <form method="post" action="{{ $pageAction }}" class="my-3 mx-auto w-full sm:w-1/2 flex items-center justify-between gap-4" aria-label="Pack list pages">
        @csrf

        <button type="submit" @disabled($prev === null) class="{{ $prev !== null ? $navButton : $navButtonDisabled }}">« First</button>
        <button type="submit" name="cursor" value="{{ $prev }}" @disabled($prev === null) class="{{ $prev !== null ? $navButton : $navButtonDisabled }}">← Previous</button>

        <span class="text-xs tabular-nums text-gray-500 dark:text-gray-400">{{ number_format($total) }} packs</span>

        <button type="submit" name="cursor" value="{{ $next }}" @disabled($next === null) class="{{ $next !== null ? $navButton : $navButtonDisabled }}">Next →</button>
        <button type="submit" name="cursor" value="last" @disabled($next === null) class="{{ $next !== null ? $navButton : $navButtonDisabled }}">Last »</button>
    </form>
@endif
