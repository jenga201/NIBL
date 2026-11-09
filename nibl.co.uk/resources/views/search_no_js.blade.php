@extends('layouts.app_no_js')

@section('title', 'Search')

@section('header', 'Search')

@section('content')
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
@endsection