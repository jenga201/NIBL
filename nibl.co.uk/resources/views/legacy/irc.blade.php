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
        <p>Join using your IRC client:</p>
        <pre style="text-align: left;">/server irc.rizon.net +6697<br>/join #nibl</pre>
        <p>Port 6697 is the TLS connection. Plain connections are possible on port 6667.</p>
        <br>
        <p>Copy and paste DCC get commands from the <a href="/bots">Bots</a> page here, or just stay and chat!</p>
        <br>
        <p>Requesting packs from a bot (XDCC):</p>
        <pre style="text-align: left;">/msg BotName xdcc send #123<br>/msg BotName xdcc batch 1,2,3</pre>
        <br>
        <p>Popular clients: <a href="http://www.mirc.com/" target="_blank">mIRC</a>,
        <a href="https://hexchat.github.io/" target="_blank">HexChat</a>,
        <a href="https://weechat.org/" target="_blank">WeeChat</a>,
        <a href="https://irssi.org/" target="_blank">irssi</a>,
        <a href="https://thelounge.chat/" target="_blank">The Lounge</a>,
        <a href="https://kiwiirc.com/nextclient/" target="_blank">KiwiIRC</a>.</p>
    </div>
    <br>
</div>
<br/>
@endsection
