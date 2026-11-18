<div class="pagination">
    @php
        $pageQuery = 'bot='.urlencode($bot->getName())
            .($searchQuery ? '&search='.urlencode($searchQuery) : '');
    @endphp

    @if ($hasPrev)
        <a href="/bots?{{ $pageQuery }}&page=0">« First</a>
        <a href="/bots?{{ $pageQuery }}&page={{ $page - 1 }}">← Previous</a>
    @else
        <span class="nav">« First</span>
        <span class="nav">← Previous</span>
    @endif

    <span class="page">Page {{ $page + 1 }}</span>

    @if ($hasNext)
        <a href="/bots?{{ $pageQuery }}&page={{ $page + 1 }}">Next →</a>
        <a href="/bots?{{ $pageQuery }}&page={{ $lastPage }}">Last »</a>
    @else
        <span class="nav">Next →</span>
        <span class="nav">Last »</span>
    @endif
</div>