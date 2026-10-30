@extends('layouts.app')

@section('title', 'Error 404')

@section('header', 'Error 404')

@section('content')
    <h2 class="text-2xl font-bold">Oops! Page not Found</h2>
    <p>Sorry but the page you are looking for does not exist.</p>
    <a href="/"><button class="mt-4 py-2 px-4 text-xl bg-gray-300 dark:bg-gray-800">Back to homepage</button></a>
@endsection
