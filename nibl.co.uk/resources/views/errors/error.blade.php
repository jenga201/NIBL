@extends('layouts.app')

@section('title', 'Error ' . $code)

@section('header', 'Error ' . $code)

@section('content')
    <h2 class="text-2xl font-bold">Oops! Something went wrong!</h2>
    <a href="/"><button class="mt-4 py-2 px-4 text-xl bg-gray-300 dark:bg-gray-800">Back to homepage</button></a>
@endsection
