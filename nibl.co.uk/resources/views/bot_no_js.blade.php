@extends('layouts.app_no_js')

@section('title', 'Bot')

@section('header', 'Bot ' . $bot->getName())

@section('content')
    <div class="flex justify-between">
        <div>
            <span class="font-bold text-lg">{{ $bot->getPackSize() }}</span>&nbsp;<span class="text-sm">packs</span>
        </div>
        <div>
            <span class="text-sm">Last processed</span>&nbsp;<span class="text-md font-bold">{{ $bot->getLastProcessed()->format('Y-m-d H:i:s') }}</span>
        </div>
    </div>

    @include('partials.pack-list', [
        'packs' => $packs ?? collect(),
        'total' => $total ?? 0,
        'next' => $next ?? null,
        'prev' => $prev ?? null,
        'searchQuery' => $searchQuery ?? '',
        'fuzzy' => $fuzzy ?? true,
        'showBot' => false,
        'botName' => $bot->getName(),
    ])
@endsection