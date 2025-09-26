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
                                <h4>Manage Tests</h4>
                                <div class="card-header-action mb-3">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter Tests
                                    </button>

                                </div>
                            </div>

                            <div class="card-body">

                        
                                <div class="collapse" id="filterCollapse">
                                    <div class="card-body pb-0">
                                        <form method="GET" action="{{ route('tests.index') }}" class="row">
                                            <div class="form-group col-md-3">
                                                <label>Test Name</label>
                                                <input type="text" name="filter_test_name" class="form-control" value="{{ request('filter_test_name') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Test Type</label>
                                                <input type="text" name="filter_test_type" class="form-control" value="{{ request('filter_test_type') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Duration (min)</label>
                                                <input type="number" name="filter_duration" class="form-control" value="{{ request('filter_duration') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Section</label>
                                                <input type="text" name="filter_section" class="form-control" value="{{ request('filter_section') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Course</label>
                                                <input type="text" name="filter_course" class="form-control" value="{{ request('filter_course') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Created By</label>
                                                <input type="text" name="filter_creator" class="form-control" value="{{ request('filter_creator') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Date From</label>
                                                <input type="date" name="filter_date_from" class="form-control" value="{{ request('filter_date_from') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Date To</label>
                                                <input type="date" name="filter_date_to" class="form-control" value="{{ request('filter_date_to') }}">
                                            </div>
                                            <div class="form-group col-md-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Apply</button>
                                                <a href="{{ route('tests.index') }}" class="btn btn-light"><i class="fas fa-sync"></i> Reset</a>
                                            </div>
                                        </form>
                                    </div>
                                </div>


                                @if(
                                request('filter_test_name') || request('filter_test_type') || request('filter_duration') ||
                                request('filter_section') || request('filter_course') || request('filter_creator') ||
                                request('filter_date_from') || request('filter_date_to')
                                )
                                <div class="mb-3">
                                    <h6>Active Filters:</h6>
                                    <div class="active-filters">
                                        @if(request('filter_test_name'))
                                        <span class="badge badge-info mr-2">Test Name: {{ request('filter_test_name') }}</span>
                                        @endif

                                        @if(request('filter_test_type'))
                                        <span class="badge badge-info mr-2">Test Type: {{ ucfirst(str_replace('_', ' ', request('filter_test_type'))) }}</span>
                                        @endif

                                        @if(request('filter_duration'))
                                        <span class="badge badge-info mr-2">Duration: {{ request('filter_duration') }} min</span>
                                        @endif

                                        @if(request('filter_section'))
                                        <span class="badge badge-info mr-2">Section: {{ request('filter_section') }}</span>
                                        @endif

                                        @if(request('filter_course'))
                                        <span class="badge badge-info mr-2">Course: {{ request('filter_course') }}</span>
                                        @endif

                                        @if(request('filter_creator'))
                                        <span class="badge badge-info mr-2">Created By: {{ request('filter_creator') }}</span>
                                        @endif

                                        @if(request('filter_date_from'))
                                        <span class="badge badge-info mr-2">From: {{ request('filter_date_from') }}</span>
                                        @endif

                                        @if(request('filter_date_to'))
                                        <span class="badge badge-info mr-2">To: {{ request('filter_date_to') }}</span>
                                        @endif

                                        <a href="{{ route('tests.index') }}" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                                @endif

                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="testTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Test Name</th>
                                                <th>Test Type</th>
                                                <th>Duration (min)</th>
                                                <th>Section</th>
                                                <th>Course</th>
                                                <th>Created By</th>
                                                <th>Date Created</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tests as $index => $test)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $test->test_name }}</td>
                                                <td>{{ ucfirst(str_replace('_', ' ', $test->test_type)) }}</td>
                                                <td>{{ $test->duration }}</td>
                                                <td>{{ $test->section->section_name ?? 'N/A' }}</td>
                                                <td>{{ $test->course->course_name ?? 'N/A' }}</td>
                                                <td>{{ $test->creator->name ?? 'N/A' }}</td>
                                                <td>{{ $test->created_at->format('j-F-Y, g:i A') }}</td>
                                                <td>
                                                    <!-- <a href="{{ route('tests.show', $test->id) }}" class="btn btn-info btn-sm">View</a> -->
                                                    <!-- Edit Button with Icon -->
                                                    <a href="{{ route('tests.edit', $test->id) }}" class="btn btn-primary btn-sm m-1" title="Edit">
                                                         <i class="fas fa-edit"></i>
                                                    </a>

                                                    <!-- Delete Button with Icon -->
                                                    <form action="{{ route('tests.destroy', $test->id) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-danger btn-sm m-1" onclick="return confirm('Are you sure?')" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>

                                                </td>
                                            </tr>
                                            @endforeach
                                            @if($tests->isEmpty())
                                            <tr>
                                                <td colspan="9" class="text-center">No tests found.</td>
                                            </tr>
                                            @endif
                                        </tbody>
                                    </table>
                                    <div class="d-flex justify-content-end mt-3">
                                        {{ $tests->links() }}
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('includes.footer')