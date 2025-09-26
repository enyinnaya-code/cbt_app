<style>
    /* Unread notification styling */
    .unread-notification {
        background-color: rgba(13, 110, 253, 0.05);
        border-left: 3px solid #0d6efd;
    }

    /* New indicator dot */
    .new-indicator {
        position: absolute;
        top: 0;
        right: 0;
        width: 8px;
        height: 8px;
        background-color: #dc3545;
        border-radius: 50%;
    }

    /* Style the notification badge */
    .notification-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background-color: #dc3545;
        color: white;
        font-size: 10px;
        padding: 2px 5px;
        border-radius: 10px;
        line-height: 1;
        font-weight: bold;
        z-index: 10;
    }
</style>
<nav class="navbar navbar-expand-lg main-navbar sticky">

    <div class="form-inline mr-auto">
        <ul class="navbar-nav mr-3">
            <li><a href="#" data-toggle="sidebar" class="nav-link nav-link-lg
									collapse-btn"> <i data-feather="align-justify"></i></a></li>

            <li>
            </li>
        </ul>
    </div>



    <ul class="navbar-nav navbar-right">
        <li class="dropdown dropdown-list-toggle">
            <a href="#" data-toggle="dropdown" class="nav-link notification-toggle nav-link-lg position-relative">
                <i data-feather="bell" class="bell"></i>
                @if(Auth::check())
                @php
                $unreadCount = Auth::user()->unreadAnnouncementsCount();
                @endphp
                @if($unreadCount > 0)
                <span class="notification-badge">{{ $unreadCount }}</span>
                @endif
                @endif
            </a>

            <div class="dropdown-menu dropdown-list dropdown-menu-right pullDown">
                <div class="dropdown-header">
                    Notifications
                    <div class="float-right">
                        <a href="#" id="markAllRead">Mark All As Read</a>
                    </div>
                </div>
                <div class="dropdown-list-content dropdown-list-icons" id="notificationList">
                    <div class="dropdown-list-content dropdown-list-icons">
                        @forelse ($recentAnnouncements ?? [] as $announcement)
                        @php
                        $isUnread = auth()->check() &&
                        auth()->user()->announcements()
                        ->where('announcement_id', $announcement->id)
                        ->wherePivotNull('read_at')
                        ->exists();
                        @endphp
                        <a href="{{ route('announcements.edit', $announcement->id) }}"
                            class="dropdown-item {{ $isUnread ? 'unread-notification' : '' }}"
                            data-announcement-id="{{ $announcement->id }}">
                            <div class="dropdown-item-icon bg-primary text-white">
                                <i class="fas fa-bullhorn"></i>
                                @if($isUnread)
                                <span class="new-indicator"></span>
                                @endif
                            </div>
                            <div class="dropdown-item-desc {{ $isUnread ? 'font-weight-bold' : '' }}">
                                {!! strip_tags($announcement->content) !!}
                                <small class="text-muted d-block mt-1">
                                    <i class="fas fa-clock"></i> {{ $announcement->created_at->diffForHumans() }}
                                    @if($isUnread)
                                    <span class="text-primary ml-2">New</span>
                                    @endif
                                </small>
                            </div>
                        </a>
                        @empty
                        <div class="dropdown-item text-center text-muted">
                            No announcements yet.
                        </div>
                        @endforelse
                    </div>
                </div>
                <div class="dropdown-footer text-center">
                    <a href="{{ route('announcements.index') }}">View All <i class="fas fa-chevron-right"></i></a>
                </div>
            </div>
        </li>
        <li class="nav-item mt-2 text-dark">
            <div class="dropdown-title">Hello <span style="text-transform: uppercase;" class="font-weight-bold">{{ Auth::user()->name }}</span></div>
        </li>


        <li class="dropdown">

            <a href="#" data-toggle="dropdown" class="nav-link dropdown-toggle nav-link-lg nav-link-user">
                <img alt="image" src="{{ asset('images/profile-place-holder.jpg') }}" class="user-img-radious-style">

            </a>

            <div class="dropdown-menu dropdown-menu-right pullDown">


                <!-- <a href="profile.html" class="dropdown-item has-icon"> <i class="far
										fa-user"></i> Profile
                </a> <a href="timeline.html" class="dropdown-item has-icon"> <i class="fas fa-bolt"></i>
                    Activities
                </a> <a href="#" class="dropdown-item has-icon"> <i class="fas fa-cog"></i>
                    Settings
                </a> -->
                <div class="dropdown-divider"></div>
                <form action="{{ route('logout') }}" method="POST" style="display: inline;">
                    @csrf
                    <button type="submit" class="dropdown-item  has-icon text-danger" style="outline: none;">
                        <i class="fas fa-sign-out-alt mt-2"></i>
                        Logout
                    </button>
                </form>


            </div>
        </li>
    </ul>
</nav>