@extends('legacy.layout')

@section('title', 'nibl - news')

@section('content')
<div style="float: right; padding: 0 0.25em; margin-left: 1em;">
    <img src="/legacy/banner.jpg" width="325" height="116" alt="[penguin] *poke poke* *poke poke*">
</div>
<div style="font-size: 2em; font-style: italic; padding: 1.5em 1em;"> ლ(ಠ_ಠლ) Edition <div style="font-size: 0.65em; text-align: right; margin-top: 0.1em;"> Jenga</div></div>
<div style="clear: both; padding-left: 50px;">Welcome to #nibl@irc.rizon.net.<br/>Join us by clicking the <a href="/irc">IRC</a> tab up top or by using an <a href="/links">IRC client</a>.<br/><br/></div>
<div class="news" style="clear: both;">
    <div class="newsitem" id="news2281">
        <div class="newsheader">
            <div class="timestamp" title="07 October 2017">07 October 2017</div>
            <h2 class="title">Nibl site changes<a href="#news2281" class="newsanchor">#</a></h2>
        </div>
        <div class="newssubheader" style="height: 15px;"><span class="right">Posted by Jenga</span></div>
        <div class="text"><br/>Fixed some site functionality and upgraded libraries.<br/>Site uses HTTPS only.<br/>Site should handle NIBL|Arutha without crashing.<br /><br />If there are any problems, please contact jenga in #nibl.<br/><br/></div>
    </div>
    <div class="newsitem" id="news2280">
        <div class="newsheader">
            <div class="timestamp" title="08 March 2016">08 March 2016</div>
            <h2 class="title">BTC means Bitcoin!<a href="#news2280" class="newsanchor">#</a></h2>
        </div>
        <div class="newssubheader" style="height: 15px;"><span class="right">Posted by Jenga</span></div>
        <div class="text"><br/>NIBL needs a facelift!<br/><br/>We are looking to design and develop a new website and need your help to do so.<br/>This time, professionally!<br/><br/>Help us out and send your spare BTC to<br/>15ojJ2uWnCeEzBLzL4PSHPSbxDw7iMDhD1<br/><br/></div>
    </div>
    <div class="newsitem" id="news2278">
        <div class="newsheader">
            <div class="timestamp" title="22 April 2014">22 April 2014</div>
            <h2 class="title">nibl.co.uk v3<a href="#news2278" class="newsanchor">#</a></h2>
        </div>
        <div class="newssubheader" style="height: 15px;"><span class="right">Posted by Sirus</span></div>
        <div class="text"><br/>Hey there guys, it's been forever since we've made a post on this page but rest assured we're all still very much alive and kicking!<br /><br />We're currently working on a v3 website that'll be changing the face of NIBL as you know it, with tons of new and cool features as well as a lot of new content! <br />It's slow going but we're half way there, and we need some help. <br /><br />So if you're a capable Web/IRC coder (PHP, Java, JavaScript, HTML/HTML5, MySQL) stop by our IRC channel and send Sirus or Jenga a message!<br/><br/></div>
    </div>
</div>
@endsection
