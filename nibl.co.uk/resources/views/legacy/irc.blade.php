@extends('legacy.layout')

@section('title', 'nibl - IRC')

@section('content')
<div class="searchbox">
    <br>
    <p>Connecting to #nibl</p>
    <br>
    <div style="text-align: left; padding-left: 20px;">
        <p><strong>Network:</strong> Rizon — <strong>Server:</strong> irc.rizon.net — <strong>Channel:</strong> #nibl</p>
        <br>
        <p>Join using your IRC client (TLS on port 6697 is recommended):</p>
        <pre style="text-align: left;">/server irc.rizon.net +6697<br>/join #nibl</pre>
        <p>Some clients use <code>/server irc.rizon.net 6697</code>. Port 6697 is the TLS connection; plain connections are possible on port 6667, but only use them if TLS is unavailable. Rizon's TLS certificate is self-signed, so accept the prompt if your client asks.</p>
        <br>
        <p>Popular clients: <a href="http://www.mirc.com/" target="_blank" rel="noopener">mIRC</a>,
        <a href="https://hexchat.github.io/" target="_blank" rel="noopener">HexChat</a>,
        <a href="https://weechat.org/" target="_blank" rel="noopener">WeeChat</a>,
        <a href="https://irssi.org/" target="_blank" rel="noopener">irssi</a>,
        <a href="https://thelounge.chat/" target="_blank" rel="noopener">The Lounge</a>,
        <a href="https://kiwiirc.com/nextclient/" target="_blank" rel="noopener">KiwiIRC</a>.</p>
        <br>
        <p>Find what a bot offers on the <a href="/bots">Bots</a> page, then message the bot directly for the file.</p>
        <p>Requesting packs from a bot (XDCC):</p>
        <pre style="text-align: left;">/msg BotName xdcc send #N<br>/msg BotName xdcc batch N1,N2,N3</pre>
        <p>Received files download via DCC. Most modern clients require <code>/dcc get</code> to accept an incoming transfer, or you can enable auto-accept.</p>
        <br>
        <p>Copy and paste DCC get commands from the <a href="/bots">Bots</a> page here, or just stay and chat!</p>
        <br>
    </div>
</div>
<br/>
@endsection
