@props(['question'])

<details class="group">
    <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-4 text-lg font-bold hover:bg-gray-50 focus-visible:outline-2 focus-visible:outline-indigo-600 dark:hover:bg-gray-900/40 [&::-webkit-details-marker]:hidden">
        <span>{{ $question }}</span>
        <svg class="h-5 w-5 shrink-0 transition-transform duration-200 group-open:rotate-180" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2" aria-hidden="true">
            <path stroke-linecap="round" stroke-linejoin="round" d="m6 9 6 6 6-6" />
        </svg>
    </summary>
    <div class="space-y-3 px-4 pb-4">
        {{ $slot }}
    </div>
</details>
