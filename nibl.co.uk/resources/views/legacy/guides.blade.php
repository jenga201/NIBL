@extends('legacy.layout')

@section('title', 'nibl - Guides')

@section('content')
<br/><br/>
<div class="searchbox">
    <p>Operation and usage of Ooinuza.</p><br/>
    <div style="text-align: left; padding-left: 20px;">
        Our friend goes by the name Ooinuza. He has been around for a while now;<br/>
        Conceptualized by Sirus. Version 1 by Jenga. Version 2 (current) by _Maru_ and Jenga.<br/>
        Quite a bit of debugging by Aashiqmunda as well.<br/>
        <br/>
        Search for a series using:<br/>
        !search Search Term Here<br/>
        Or<br/>
        !search Search Term Here [episode#-episode#]<br/>
        Or<br/>
        !search Search Term Here !DONTWANT [episode#-episode#]<br/>
        <br/>
        Searching [episode#-episode#] means to search episodes in order.<br/>
        Using !DONTWANT means you don't want to include those results.<br/>
        <br/>
        Example:<br/>
        !search naruto [10-12] !raw<br/>
        <br/>
        will return<br/>
        naruto episode 10.avi<br/>
        naruto episode 11.avi<br/>
        naruto episode 12.avi<br/>
        <br/>
        It will NOT return 'naruto episode 10 RAW.avi'<br/>
        <br/>
        To Stop a listing:<br/>
        /msg ooinuza stop<br/>
        <br/>
        There is a maximum of 15 results returned.<br/>
    </div>
</div>
<br/>
@endsection
