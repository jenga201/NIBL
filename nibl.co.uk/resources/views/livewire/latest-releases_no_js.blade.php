<div>
    <div class="flex flex-wrap justify-between sticky top-0 bg-gray-100 dark:bg-gray-700 pt-2 pb-1 z-10">
        <h2 class="font-bold text-2xl">Latest releases</h2>
    </div>

    <div class="my-4 bg-white p-0 dark:bg-gray-800 overflow-x-auto">
        <table class="border-collapse table-fixed w-full bg-white table-striped relative shadow-sm dark:bg-gray-800">
            <colgroup>
                <col style="width: 200px" />
                <col style="width: 80px" />
                <col style="width: 100px" />
                <col />
                <col style="width: 180px" />
            </colgroup>
            <thead>
            <tr class="text-left">
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 rounded-tl dark:bg-gray-900">Bot</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Pack</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Size</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Filename</th>
                <th class="py-2 px-3 border-b border-gray-800 bg-gray-800 text-gray-200 rounded-tr dark:bg-gray-900">Actions</th>
            </tr>
            </thead>
            <tbody>
            @foreach ($latest as $pack)
            <tr class="text-left hover:bg-gray-100 dark:hover:bg-gray-700">
                <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700">
                    <a href="/bots/{{ $pack->getBot()->getName() }}" class="block truncate hover:underline" title="{{ $pack->getBot() }}">{{ $pack->getBot() }}</a>
                </td>
                <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700">{{ $pack->getNumber() }}</td>
                <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700">{{ $pack->getSizeReadable() }}</td>
                <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700 overflow-hidden">
                    <span class="block truncate" title="{{ $pack->getName() }}">{{ $pack->getName() }}</span>
                </td>
                <td class="border-dashed border-t border-gray-200 px-3 py-2 dark:border-gray-700">
                    <input type="text" readonly
                           value="/msg {{ $pack->getBot()->getName() }} xdcc send #{{ $pack->getNumber() }}"
                           aria-label="XDCC command"
                           class="w-full rounded-sm border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-mono text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200" />
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>