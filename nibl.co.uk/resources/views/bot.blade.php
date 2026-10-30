@extends('layouts.app')

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

    <div
        data-pack-table
        data-bot-id="{{ $bot->getId() }}"
        data-bot-name="{{ $bot->getName() }}"
        data-batch-enabled="{{ $bot->isBatchEnabled() ? 'true' : 'false' }}"
    ></div>
@endsection
