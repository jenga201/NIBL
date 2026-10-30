@extends('legacy.layout')

@section('title', 'nibl - bots')

@section('content')
<form method="get" action="/bots">
    <div class="searchbox"><br/>Search:
        <input type="text" name="search" class="query">
        <input type="submit" value="Search" class="submit">
    </div>
</form>
<div class="bots">
    <div class="botsheader">
        @if (count($bots) > 0)
            <div class="title">All bots:</div>
            {{ count($bots) }} bot{{ count($bots) !== 1 ? 's' : '' }} indexed
        @else
            <div class="title">No bots found!</div>
        @endif
    </div>
    <div class="bot first">
        <div class="name"><a href="/bots?latest=true">Latest Packs</a></div>
    </div>
    @foreach ($bots as $bot)
        <div class="bot {{ $loop->last ? 'last' : '' }}">
            <div class="name">
                <a href="/bots?bot={{ urlencode($bot->getName()) }}">
                    {{ $bot->getName() }}
                    <div class="right">({{ $bot->getPackSize() }} packs)</div>
                </a>
            </div>
        </div>
    @endforeach
</div>
@endsection
