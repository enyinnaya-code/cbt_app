{{-- Active announcements as closable cards. Closing one is remembered in this browser only, so a new announcement still shows. --}}
@php
    $shown = \App\Models\Notice::forStudents('web');
    $toneBg = ['info' => 'var(--blue-soft)', 'success' => 'var(--primary-soft)', 'warning' => 'var(--accent-soft)'];
@endphp
@foreach($shown as $n)
    <div class="card row between notice" data-notice="{{ $n->id }}" hidden style="background:{{ $toneBg[$n->tone] ?? $toneBg['info'] }};border:0;align-items:flex-start;gap:12px">
        <div class="grow">
            <p class="h3">{{ $n->title }}</p>
            @if($n->body)<p class="small" style="white-space:pre-line;margin-top:2px">{{ $n->body }}</p>@endif
            @if($n->link_url)<p style="margin-top:8px"><a class="btn btn-p btn-sm" href="{{ $n->link_url }}" target="_blank" rel="noopener noreferrer">{{ $n->link_label ?: 'Read more' }}</a></p>@endif
        </div>
        <button type="button" class="iconbtn notice-x" aria-label="Close this announcement" style="width:34px;height:34px"><x-icon name="x" size="s"/></button>
    </div>
@endforeach
@if($shown->isNotEmpty())
<script>
(function () {
    var KEY = 'testacbt.closedNotices';
    var closed = [];
    try { closed = JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { closed = []; }
    document.querySelectorAll('.notice').forEach(function (card) {
        var id = Number(card.getAttribute('data-notice'));
        if (closed.indexOf(id) === -1) { card.hidden = false; }
        card.querySelector('.notice-x').addEventListener('click', function () {
            card.hidden = true;
            closed.push(id);
            try { localStorage.setItem(KEY, JSON.stringify(closed.slice(-50))); } catch (e) { /* private window: it just comes back next visit */ }
        });
    });
})();
</script>
@endif
