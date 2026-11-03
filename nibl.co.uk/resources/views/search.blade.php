@extends('layouts.app')

@php($noJs = \App\Domain\JsMode::current()->isDisabled())

@section('title', 'Search')

@section('header', 'Search')

@section('content')
    @if ($noJs)
        @include('partials.pack-list', [
            'packs' => $packs ?? collect(),
            'total' => $total ?? 0,
            'next' => $next ?? null,
            'prev' => $prev ?? null,
            'searchQuery' => $searchQuery ?? '',
            'fuzzy' => $fuzzy ?? true,
            'showBot' => true,
            'botName' => null,
        ])
    @else
        <div data-pack-table></div>
    @endif
@endsection
