@include('includes.head')

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('includes.right_top_nav')
            @include('includes.side_nav')

            <!-- Main Content -->
            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <form class="needs-validation" class="col-md-6">
                                <div class="card-header">
                                    <h4>Manage Announcements</h4>
                                    <!-- Filter Button -->
                                    <div class="card-header-action">
                                        <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                            <i class="fas fa-filter"></i> Filter
                                        </button>
                                    </div>
                                </div>

                                <!-- Filter Collapse Panel -->
                                <div class="collapse" id="filterCollapse">
                                    <div class="card-body row px-5 pb-0">
                                        <form action="{{ route('announcements.index') }}" method="GET" class="row mb-4">

                                            <div class="form-group col-md-4">
                                                <label>Search</label>
                                                <input type="text" class="form-control" name="filter_text" value="{{ request('filter_text') }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Date From</label>
                                                <input type="date" class="form-control" name="filter_date_from" value="{{ request('filter_date_from') }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Date To</label>
                                                <input type="date" class="form-control" name="filter_date_to" value="{{ request('filter_date_to') }}">
                                            </div>
                                            <div class="form-group col-md-12 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary mr-2">
                                                    <i class="fas fa-search"></i> Apply Filters
                                                </button>
                                                <a href="{{ route('schoolClass.manage') }}" class="btn btn-light">
                                                    <i class="fas fa-sync"></i> Reset
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Display Active Filters -->
                                    @if(request('filter_text') || request('filter_date_from') || request('filter_date_to'))
                                    <div class="mb-3">
                                        <h6>Active Filters:</h6>
                                        <div class="active-filters">
                                            @if(request('filter_text'))
                                            <span class="badge badge-info mr-2">Search: {{ request('filter_text') }}</span>
                                            @endif
                                            @if(request('filter_date_from'))
                                            <span class="badge badge-info mr-2">From: {{ request('filter_date_from') }}</span>
                                            @endif
                                            @if(request('filter_date_to'))
                                            <span class="badge badge-info mr-2">To: {{ request('filter_date_to') }}</span>
                                            @endif
                                            <a href="{{ route('announcements.index') }}" class="btn btn-sm m-1 btn-outline-danger">
                                                <i class="fas fa-times"></i> Clear All
                                            </a>
                                        </div>
                                    </div>
                                    @endif

                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>#</th>
                                                    <th>Content</th>
                                                    <th>Sender</th>
                                                    <th>Date Sent</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @forelse ($announcements as $index => $announcement)
                                                <tr>
                                                    <td>{{ $announcements->firstItem() + $index }}</td>
                                                    <td>{!! Str::limit($announcement->content, 100) !!}</td>
                                                    <td>{{ $announcement->user->name ?? 'N/A' }}</td>
                                                    <td>{{ $announcement->created_at->format('d M Y, h:i A') }}</td>
                                                    <td>
                                                        <a href="{{ route('announcements.edit', $announcement->id) }}" class="btn m-1 btn-sm btn-warning" title="Edit">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form action="{{ route('announcements.destroy', $announcement->id) }}" method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this announcement?')">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn m-1 btn-sm btn-danger" title="Delete">
                                                                <i class="fas fa-trash-alt"></i>
                                                            </button>
                                                        </form>
                                                    </td>
                                                </tr>
                                                @empty
                                                <tr>
                                                    <td colspan="5" class="text-center">No announcements found.</td>
                                                </tr>
                                                @endforelse
                                            </tbody>
                                        </table>

                                        <div class="mt-3">
                                           {{ $announcements->appends(request()->query())->links() }}

                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    @include('includes.footer')
</body>