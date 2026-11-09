@extends('layouts.app')

@section('title', 'FAQ')

@section('header', 'FAQ')

@section('content')
    <article class="bg-white rounded-sm shadow-sm my-4 divide-y divide-gray-200 dark:divide-gray-700 dark:bg-gray-800">
        <x-faq-item question="How do I download?">
            <p>Packs are shared by XDCC bots in the <span class="font-semibold">#nibl</span> channel on the <span class="font-semibold">Rizon</span> IRC network.<br>
            Connect with an IRC client, find a bot on the <a class="underline" href="/bots">Bots</a> page, then message the bot for the pack you want.</p>

            <div class="divide-y divide-solid divide-gray-200 border-t border-solid border-gray-200 dark:divide-gray-700 dark:border-gray-700">
                <div class="py-3 first:pt-0 last:pb-0">
                    <h4 class="font-semibold">IRC clients</h4>
                    <div class="overflow-x-auto">
                        <table class="mt-1 w-full text-left text-sm">
                            <tbody class="divide-y divide-solid divide-gray-200 dark:divide-gray-700">
                                <tr>
                                    <td class="py-3 pr-5 align-top font-semibold">
                                        <span class="relative inline-flex pr-5" title="Top Pick">
                                            <a class="underline" href="https://zoitechat.org/download.php" target="_blank" rel="noopener">ZoiteChat</a>
                                            <span class="absolute top-0.5 right-0.5 grid min-h-[24px] min-w-[24px] translate-x-2/4 -translate-y-2/4 place-items-center rounded-full border border-white bg-green-600 px-1 py-1 text-xs text-white">
                                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 16 16" fill="currentColor" class="h-3.5 w-3.5" aria-hidden="true">
                                                    <path fill-rule="evenodd" d="M12.416 3.376a.75.75 0 0 1 .208 1.04l-5 7.5a.75.75 0 0 1-1.154.114l-3-3a.75.75 0 0 1 1.06-1.06l2.353 2.353 4.493-6.74a.75.75 0 0 1 1.04-.207Z" clip-rule="evenodd" />
                                                </svg>
                                                <span class="sr-only">Top Pick</span>
                                            </span>
                                        </span>
                                    </td>
                                    <td class="py-3 align-top text-gray-600 dark:text-gray-300">Windows, Linux, macOS — free, open-source successor to HexChat with solid DCC support.</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-5 align-top font-semibold"><a class="underline" href="https://www.adiirc.com/" target="_blank" rel="noopener">AdiIRC</a></td>
                                    <td class="py-3 align-top text-gray-600 dark:text-gray-300">Windows — feature-rich client with a familiar interface and solid DCC support.</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-5 align-top font-semibold"><a class="underline" href="https://halloy.chat/" target="_blank" rel="noopener">Halloy</a></td>
                                    <td class="py-3 align-top text-gray-600 dark:text-gray-300">Windows, Linux, macOS — modern, free, and open-source.</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-5 align-top font-semibold"><a class="underline" href="https://weechat.org/" target="_blank" rel="noopener">WeeChat</a></td>
                                    <td class="py-3 align-top text-gray-600 dark:text-gray-300">Terminal — lightweight and highly scriptable.</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-5 align-top font-semibold"><a class="underline" href="https://irssi.org/" target="_blank" rel="noopener">irssi</a></td>
                                    <td class="py-3 align-top text-gray-600 dark:text-gray-300">Terminal — fast and keyboard-driven for power users.</td>
                                </tr>
                                <tr>
                                    <td class="py-3 pr-5 align-top font-semibold"><a class="underline" href="https://kiwiirc.com/nextclient/" target="_blank" rel="noopener">KiwiIRC</a></td>
                                    <td class="py-3 align-top text-gray-600 dark:text-gray-300">Web — no installation required.</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="py-3 first:pt-0 last:pb-0">
                    <h4 class="font-semibold">Connection details</h4>
                    <ul class="mt-1 list-inside list-disc space-y-1">
                        <li><span class="font-semibold">Network:</span> Rizon</li>
                        <li><span class="font-semibold">Server:</span> <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm dark:bg-gray-900">irc.rizon.net</code></li>
                        <li><span class="font-semibold">Channel:</span> <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm dark:bg-gray-900">#nibl</code></li>
                        <li><span class="font-semibold">Port:</span> <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm dark:bg-gray-900">6697</code> (TLS, recommended) or <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm dark:bg-gray-900">6667</code> (plain, only if TLS is unavailable)</li>
                    </ul>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Rizon's TLS certificate is self-signed; accept the prompt if your client asks.</p>
                </div>

                <div class="py-3 first:pt-0 last:pb-0">
                    <h4 class="font-semibold">Joining #nibl</h4>
                    <pre class="mt-1 overflow-x-auto rounded-sm bg-gray-900 p-3 font-mono text-sm text-gray-100"><code>/server irc.rizon.net +6697<br>/join #nibl</code></pre>
                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Some clients use <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm dark:bg-gray-900">/server irc.rizon.net 6697</code> instead.</p>
                </div>

                <div class="py-3 first:pt-0 last:pb-0">
                    <h4 class="font-semibold">Requesting packs (XDCC)</h4>
                    <p class="mt-1">Send a pack number to the bot that offers it:</p>
                    <pre class="mt-1 overflow-x-auto rounded-sm bg-gray-900 p-3 font-mono text-sm text-gray-100"><code>/msg BotName xdcc send #N</code></pre>
                    <p class="mt-2">Request several packs at once:</p>
                    <pre class="mt-1 overflow-x-auto rounded-sm bg-gray-900 p-3 font-mono text-sm text-gray-100"><code>/msg BotName xdcc batch N1,N2,N3</code></pre>
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">Files are transferred over DCC. Most clients require <code class="rounded bg-gray-100 px-1 py-0.5 font-mono text-sm dark:bg-gray-900">/dcc get</code> to accept an incoming transfer, or you can enable auto-accept.</p>
                </div>
            </div>
        </x-faq-item>

        <x-faq-item question="How to get my bot on the website?">
            <p>Contact an admin on IRC (Sirus, Jenga) or ask on Discord.</p>
        </x-faq-item>

        <x-faq-item question="Why is a bot down?">
            <p>We don't know, we only provide the parser and channel for downloading, we may know the bot owners, but won't help you get the bot up as they're not ours.</p>
        </x-faq-item>

        <x-faq-item question="Why is the bot sending me the wrong files?">
            <p>Sometimes the bots sort their files upon adding them, causing the packlist on the NIBL website to be wrong.  This is normal behavior and takes around 15 minutes for the nibl listing to be updated. If you run into this issue, please wait 15 minutes and try again.</p>
            <p>You can also request a packlist from the bot itself, such as: <span class="font-semibold">msg bot xdcc -1</span>, or possibly <span class="font-semibold">msg bot xdcc list</span> If it supports this, it will be the most accurate and up to date listing possible.</p>
        </x-faq-item>

        <x-faq-item question="Why is a bot no longer on the parser?">
            <p>We tried to contact the owner, but it wasn't answering, so we removed the bot from the list. (We're not gonna chase the bots owners, so don't ask)</p>
        </x-faq-item>

        <x-faq-item question="Why is X bot this slow?">
            <p>Too large of a queue, too many packs or aging server hardware are some of the reasons.</p>
        </x-faq-item>
    </article>
@endsection
