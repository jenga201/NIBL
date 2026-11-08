@extends('layouts.app')

@php($noJs = \App\Domain\JsMode::current()->isDisabled())

@section('title', 'Home')

@section('header', 'Home')

@section('content')
    @if (null !== $news)
        @if ($news->isLongContext())
            <div class="bg-white rounded-sm shadow-sm w-full dark:bg-gray-800">
                <div class="flex rounded-t justify-between items-center py-2 px-3 bg-indigo-600">
                    <span title="{{ $news->getCreatedBy() }}" class="text-white font-bold dark:text-indigo-100">News</span>
                    <span class="text-white ml-auto dark:text-indigo-100">{{ $news->getPublishedAt()->format('d/m/Y') }}</span>
                </div>
                <div class="text-gray-900 px-3 py-2 dark:text-gray-100 mb-2">
                    {!! $news->getContext() !!}
                    <p class="mt-2"><em>~{{ $news->getCreatedBy() }}</em></p>
                </div>
            </div>
        @else
            <div class="flex items-center bg-white leading-none rounded-lg p-2 shadow-sm text-sm w-full dark:bg-gray-800">
                <span title="{{ $news->getCreatedBy() }}" class="inline-flex bg-indigo-600 text-white rounded-full h-6 px-3 justify-center items-center mr-2 dark:text-indigo-100">News</span>
                <span class="inline-flex text-gray-900 px-2 dark:text-gray-100">{{ $news->getContext() }}</span>
                <span class="inline-flex text-indigo-600 ml-auto dark:text-indigo-300">{{ $news->getPublishedAt()->format('d/m/Y') }}</span>
            </div>
        @endif
    @endif

    <div class="my-4 bg-white rounded-sm shadow-sm p-4 dark:bg-gray-800">
        <h2 class="font-bold text-2xl">Welcome to nibl.</h2>
        <p class="mt-2 mb-4">
            <strong>nibl.co.uk</strong> provides direct access to large number of XDCC IRC Bots. If you know what are you looking for, type your query inside this quick search form.
            If you want to browse our indexes go to <a class="text-indigo-900 underline dark:text-indigo-300" href="/bots">bots</a> tab.
        </p>
        <h3 class="font-bold text-xl">Quick search</h3>
        <form action="/search" class="w-full mt-2 h-10 pl-3 pr-2 bg-white border rounded-sm flex justify-between items-center relative dark:bg-gray-900 dark:border-gray-900" method="GET">
            <label for="search" class="sr-only">Search input</label>
            <input type="search"
                   name="query"
                   id="search"
                   placeholder="Type your text here"
                   minlength="3"
                   maxlength="250"
                   class="appearance-none w-full outline-hidden focus:outline active:outline-hidden dark:bg-gray-900"
            />
            <button type="submit" class="ml-1 outline-hidden focus:outline-hidden active:outline-hidden">
                <span class="sr-only">Search button</span>
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                     viewBox="0 0 24 24" class="w-6 h-6">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                </svg>
            </button>
        </form>
    </div>

    <livewire:latest-releases />
@endsection
