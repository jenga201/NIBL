@extends('legacy.layout')

@section('title', 'nibl - bots' . ($bot ? ' - ' . $bot->getName() : '') . ($searchQuery ? ' - ' . $searchQuery : ''))

@section('content')
<form method="get" action="/bots">
    <div class="searchbox"><br/>Search:
        <input type="text" name="search" value="{{ $searchQuery }}" class="query">
        @if ($bot)
            <input type="hidden" name="bot" value="{{ $bot->getName() }}">
            <input type="submit" name="searchthis" value="Search this bot" class="submit">
            <input type="submit" name="searchall" value="Search all bots" class="submit">
        @else
            <input type="submit" value="Search" class="submit">
        @endif
    </div>
</form>
<div class="bots">
    <div class="botsheader">
        @if ($searchQueryVague)
            <div class="title"><strong>Error:</strong> Search query too vague!</div>
        @elseif ($results === null || count($results) === 0)
            <div class="title">No packs found!</div>
        @else
            <div class="title">{{ count($results) }} pack{{ count($results) !== 1 ? 's' : '' }} found
                @if ($bot)on {{ $bot->getName() }}@endif
                @if ($searchQuery) matching "{{ $searchQuery }}"@endif:</div>
        @endif
    </div>

    <table class="botlist">
        <tr class="nowrap">
            <th>Batch</th>
            @if (! $bot)
                <th>Name</th>
            @endif
            <th>Pack</th>
            <th>Size</th>
            <th>Filename</th>
        </tr>
        @foreach ($results ?? [] as $pack)
            <tr class="botlistitem {{ $loop->first ? 'first' : '' }} {{ $loop->last ? 'last' : '' }}"
                data-botname="{{ $pack->getBot()->getName() }}" data-botpack="{{ $pack->getNumber() }}">
                <td class="batch"><input type="checkbox" name="batch"></td>
                @if (! $bot)
                    <td class="name"><a href="/bots?bot={{ urlencode($pack->getBot()->getName()) }}">{{ $pack->getBot()->getName() }}</a></td>
                @endif
                <td class="packnumber">{{ $pack->getNumber() }}</td>
                <td class="filesize">{{ $pack->getSizeReadable() }}</td>
                <td class="filename">{{ $pack->getName() }}
                    <a href="/bots?search={{ urlencode($pack->getName()) }}" class="link" title="Find out what other bots have this file">[s]</a>
                </td>
            </tr>
        @endforeach
    </table>
</div>
@endsection

@section('scripts')
<script src="/legacy/js/bots.js"></script>
@endsection
