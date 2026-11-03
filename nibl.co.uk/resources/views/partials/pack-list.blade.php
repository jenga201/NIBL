@php
    $formAction = $showBot
        ? route('search')
        : route('bot', ['name' => $botName]);
@endphp
<div class="my-4 bg-white rounded-sm shadow-sm p-4 dark:bg-gray-800">
    <h3 class="font-bold text-xl">{{ $showBot ? 'Search form' : 'Search in selected bot packs' }}</h3>
    <form method="post" action="{{ $formAction }}" class="mt-2">
        @csrf
        <div class="w-full h-10 pl-3 pr-2 bg-white border rounded-sm flex justify-between items-center relative dark:bg-gray-900 dark:border-gray-900">
            <label for="pack-search-input" class="sr-only">Search input</label>
            <input id="pack-search-input" type="search" name="query" value="{{ $searchQuery }}"
                   placeholder="Type your text here" minlength="3" maxlength="250"
                   class="appearance-none w-full outline-hidden focus:outline active:outline-hidden dark:bg-gray-900" />
            <button type="submit" class="ml-1 outline-hidden focus:outline-hidden active:outline-hidden" aria-label="Search">
                <svg fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" viewBox="0 0 24 24" class="w-6 h-6">
                    <path d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                </svg>
            </button>
        </div>
        <label class="mt-2 flex items-center gap-2 text-sm text-gray-700 dark:text-gray-300">
            <input type="checkbox" name="fuzzy" value="1" {{ $fuzzy ? 'checked' : '' }}
                   class="form-tick appearance-none h-5 w-5 border border-gray-300 rounded checked:bg-blue-600 checked:border-transparent focus:outline-none dark:border-gray-600 dark:bg-gray-600 {{ $fuzzy ? '' : 'opacity-50' }}" />
            Fuzzy search
        </label>
    </form>
</div>

@if ($showBot && strlen(trim((string) $searchQuery)) < 3)
    <div class="my-4 bg-white rounded-sm shadow-sm p-4 text-center dark:bg-gray-800">
        <h3 class="text-2xl">Search for packs</h3>
        <p class="text-sm mt-2">Enter at least 3 characters to search across all bots.</p>
    </div>
@elseif (count($packs) === 0)
    <p class="my-4 text-sm text-gray-500 dark:text-gray-400">No packs found.</p>
@else
    @include('partials.pagination', ['total' => $total, 'next' => $next, 'prev' => $prev, 'query' => $searchQuery, 'showBot' => $showBot, 'botName' => $botName ?? null])

    <div class="my-4 bg-white p-0 dark:bg-gray-800 overflow-x-auto">
        <table class="border-collapse w-full table-fixed bg-white relative shadow-sm dark:bg-gray-800">
            <colgroup>
                @if ($showBot)
                    <col style="width: 200px" />
                @endif
                <col style="width: 80px" />
                <col style="width: 100px" />
                <col />
                <col style="width: 180px" />
            </colgroup>
            <thead>
            <tr class="text-left">
                @if ($showBot)
                    <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900 rounded-tl">Bot</th>
                @endif
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900 {{ $showBot ? '' : 'rounded-tl' }}">Pack</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Size</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Filename</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900 rounded-tr">Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($packs as $pack)
                <tr class="text-left hover:bg-gray-100 dark:hover:bg-gray-700">
                    @if ($showBot)
                        <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">
                            <a href="/bots/{{ rawurlencode($pack->bot->name) }}" class="block truncate hover:underline" title="{{ $pack->bot->name }}">{{ $pack->bot->name }}</a>
                        </td>
                    @endif
                    <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">{{ $pack->number }}</td>
                    <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">{{ $pack->size }}</td>
                    <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">
                        <span class="block truncate" title="{{ $pack->name }}">{{ $pack->name }}</span>
                    </td>
                    <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">
                        <input type="text" readonly
                               value="/msg {{ $showBot ? ($pack->bot->name ?? '') : $botName }} xdcc send #{{ $pack->number }}"
                               aria-label="XDCC command"
                               class="w-full rounded-sm border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-mono text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200" />
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>

    @include('partials.pagination', ['total' => $total, 'next' => $next, 'prev' => $prev, 'query' => $searchQuery, 'showBot' => $showBot, 'botName' => $botName ?? null])
@endif
