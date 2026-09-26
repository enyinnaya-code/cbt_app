{{-- Sub-navigation for the admin "Content" screens. --}}
<div class="chips scroll">
    <a class="chip {{ request()->routeIs('console.posts.*') ? 'on' : '' }}" href="{{ route('console.posts.index') }}">Articles</a>
    <a class="chip {{ request()->routeIs('console.videos.*') ? 'on' : '' }}" href="{{ route('console.videos.index') }}">Videos</a>
    <a class="chip {{ request()->routeIs('console.events.*') ? 'on' : '' }}" href="{{ route('console.events.index') }}">Events</a>
    <a class="chip {{ request()->routeIs('console.settings.*') ? 'on' : '' }}" href="{{ route('console.settings.edit') }}">Site settings</a>
</div>
