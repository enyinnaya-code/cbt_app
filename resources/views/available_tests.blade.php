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
                            <div class="card-header">
                                <h4>Available Approved Tests</h4>

                                <div class="card-header-action">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter Tests
                                    </button>
                                </div>
                            </div>

                            <div class="collapse" id="filterCollapse">
                                <div class="card-body row px-5 pb-0">
                                    <form action="{{ route('tests.available') }}" method="GET" class="row mb-4">
                                        <div class="form-group col-md-3">
                                            <label>Test Name</label>
                                            <input type="text" class="form-control" name="filter_test_name" value="{{ request('filter_test_name') }}">
                                        </div>

                                        @if(in_array(Auth::user()->user_type, [1, 2]))
                                        <div class="form-group col-md-3">
                                            <label>Class</label>
                                            <select class="form-control" name="filter_class">
                                                <option value="">-- Select Class --</option>
                                                @foreach($classes as $class)
                                                <option value="{{ $class->id }}" {{ request('filter_class') == $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        @endif

                                        <div class="form-group col-md-3">
                                            <label>Schedule From</label>
                                            <input type="date" class="form-control" name="filter_schedule_from" value="{{ request('filter_schedule_from') }}">
                                        </div>

                                        <div class="form-group col-md-3">
                                            <label>Schedule To</label>
                                            <input type="date" class="form-control" name="filter_schedule_to" value="{{ request('filter_schedule_to') }}">
                                        </div>

                                        <div class="form-group col-md-12 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                <i class="fas fa-search"></i> Apply Filters
                                            </button>
                                            <a href="{{ route('tests.available') }}" class="btn btn-light">
                                                <i class="fas fa-sync"></i> Reset
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Table to display available tests -->
                                @if(request('filter_test_name') || request('filter_class') || request('filter_schedule_from') || request('filter_schedule_to'))
                                <div class="mb-3">
                                    <h6>Active Filters:</h6>
                                    <div class="active-filters">
                                        @if(request('filter_test_name'))
                                        <span class="badge badge-info mr-2">Test Name: {{ request('filter_test_name') }}</span>
                                        @endif
                                        @if(request('filter_class'))
                                        <span class="badge badge-info mr-2">Class: {{ $classes->where('id', request('filter_class'))->first()->name ?? 'Unknown' }}</span>
                                        @endif
                                        @if(request('filter_schedule_from'))
                                        <span class="badge badge-info mr-2">Schedule From: {{ request('filter_schedule_from') }}</span>
                                        @endif
                                        @if(request('filter_schedule_to'))
                                        <span class="badge badge-info mr-2">Schedule To: {{ request('filter_schedule_to') }}</span>
                                        @endif

                                        <a href="{{ route('tests.available') }}" class="btn btn-sm m-1 btn-outline-danger">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Test Name</th>
                                                <th>Test Type</th>
                                                <th>Duration (Minutes)</th>
                                                <th>Approval Status</th>
                                                <th>Scheduled Date</th>
                                                <!-- <th>Actions</th> -->
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @forelse ($tests as $index => $test)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $test->test_name }}</td>
                                                <td>{{ $test->test_type }}</td>
                                                <td>{{ $test->duration ?? 'N/A' }}</td>
                                                <td>
                                                    @if($test->is_approved)
                                                    <span class="badge badge-success">Approved</span>
                                                    @else
                                                    <span class="badge badge-warning">Not Approved</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @if($test->scheduled_date)
                                                    {{ \Carbon\Carbon::parse($test->scheduled_date)->format('d M, Y  g:i A') }}
                                                    @else
                                                    <span class="badge badge-warning">Not Scheduled</span>
                                                    @endif
                                                </td>
                                                <!-- <td>
                                                    <a href="{{ route('tests.take', $test->id) }}" class="btn btn-sm btn-primary">Take Test</a>
                                                </td> -->
                                            </tr>
                                            @empty
                                            <tr>
                                                <td colspan="7" class="text-center">No available tests found.</td>
                                            </tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('includes.footer')