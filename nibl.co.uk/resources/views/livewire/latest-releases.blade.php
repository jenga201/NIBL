<div>
    <div class="flex flex-wrap justify-between sticky top-0 bg-gray-100 dark:bg-gray-700 pt-2 pb-1 z-10">
        <h2 class="font-bold text-2xl">Latest releases</h2>
        @if (! $noJs)
        <div>
            <button id="enable-copy-as-batch" class="py-1 px-2 bg-gray-800 hover:bg-gray-700 text-gray-100 dark:bg-gray-900 dark:hover:bg-gray-800">Enable batch mode</button>
            <button id="disable-copy-as-batch" class="hidden py-1 px-2 border border-gray-800 hover:bg-gray-700 text-gray-700 hover:text-gray-100 dark:text-gray-300 dark:hover:text-gray-200">Disable batch mode</button>
            <button id="clear-selected-batch" class="hidden py-1 px-2 border border-gray-800 hover:bg-gray-700 text-gray-700 hover:text-gray-100 dark:text-gray-300 dark:hover:text-gray-200 mr-2">Clear selected</button>
            <button id="copy-as-batch" class="hidden py-1 px-2 bg-gray-800 hover:bg-gray-700 text-gray-700 hover:text-gray-100 dark:text-gray-300 dark:hover:text-gray-200">Copy selected</button>
        </div>
        @endif
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
                <th class="py-2 px-3 sticky top-11 border-b border-gray-800 bg-gray-800 text-gray-200 rounded-tl dark:bg-gray-900">Bot</th>
                <th class="py-2 px-3 sticky top-11 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Pack</th>
                <th class="py-2 px-3 sticky top-11 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Size</th>
                <th class="py-2 px-3 sticky top-11 border-b border-gray-800 bg-gray-800 text-gray-200 dark:bg-gray-900">Filename</th>
                <th class="py-2 px-3 sticky top-11 border-b border-gray-800 bg-gray-800 text-gray-200 rounded-tr dark:bg-gray-900 {{ $noJs ? '' : 'text-center' }}">Actions</th>
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
                    @if ($noJs)
                        <input type="text" readonly
                               value="/msg {{ $pack->getBot()->getName() }} xdcc send #{{ $pack->getNumber() }}"
                               aria-label="XDCC command"
                               class="w-full rounded-sm border border-gray-300 bg-gray-50 px-2 py-1 text-xs font-mono text-gray-700 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-200" />
                    @else
                        <div class="flex items-center justify-center gap-2">
                            <button class="copy-data py-1 px-2 bg-gray-300 hover:bg-gray-200 dark:bg-gray-900 dark:hover:bg-gray-800" data-botpack="{{ $pack->getNumber() }}" data-botname="{{ $pack->getBot() }}">Copy</button>
                            @if ($pack->getBot()->isBatchEnabled())
                            <label class="batch-copy-checkbox hidden items-center space-x-2">
                                <input type="checkbox"
                                       name="batch"
                                       value="1"
                                       data-botpack="{{ $pack->getNumber() }}"
                                       data-botname="{{ $pack->getBot() }}"
                                       class="form-tick appearance-none h-6 w-6 border border-gray-300 rounded-md checked:bg-blue-600 checked:border-transparent focus:outline-hidden dark:border-gray-600 dark:bg-gray-600">
                                <span class="text-gray-900 font-medium dark:text-gray-100">Batch</span>
                            </label>
                            @endif
                        </div>
                    @endif
                </td>
            </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
