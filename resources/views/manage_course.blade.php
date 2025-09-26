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
                            <form class="needs-validation" novalidate class="col-md-6">
                                <div class="card-header">
                                    <h4>Manage Courses</h4>
                                    <!-- Filter Button -->
                                    <div class="card-header-action">
                                        <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                            <i class="fas fa-filter"></i> Filter Classes
                                        </button>
                                    </div>
                                </div>

                                <!-- Filter Collapse Panel -->
                                <div class="collapse" id="filterCollapse">
                                    <div class="card-body row px-5 pb-0">
                                        <form action="{{ route('course.manage') }}" method="GET" class="row mb-4">
                                            <div class="form-group col-md-4">
                                                <label>Course/Subject</label>
                                                <input type="text" class="form-control" name="filter_name" value="{{ request('filter_name') }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Date From</label>
                                                <input type="date" class="form-control" name="filter_date_from" value="{{ request('filter_date_from') }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Date To</label>
                                                <input type="date" class="form-control" name="filter_date_to" value="{{ request('filter_date_to') }}">
                                            </div>
                                            <div class="form-group col-md-4">
                                                <label>Section</label>
                                                <select class="form-control" name="filter_section">
                                                    <option value="">-- Select Section --</option>
                                                    @foreach($sections as $section)
                                                    <option value="{{ $section->id }}" {{ request('filter_section') == $section->id ? 'selected' : '' }}>
                                                        {{ $section->section_name }}
                                                    </option>
                                                    @endforeach
                                                </select>
                                            </div>

                                            <div class="form-group col-md-12 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary mr-2">
                                                    <i class="fas fa-search"></i> Apply Filters
                                                </button>
                                                <a href="{{ route('course.manage') }}" class="btn btn-light">
                                                    <i class="fas fa-sync"></i> Reset
                                                </a>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <div class="card-body">
                                    <!-- Display Active Filters -->
                                    @if(request('filter_name') || request('filter_date_from') || request('filter_date_to'))
                                    <div class="mb-3">
                                        <h6>Active Filters:</h6>
                                        <div class="active-filters">
                                            @if(request('filter_name'))
                                            <span class="badge badge-info mr-2">Class Name: {{ request('filter_name') }}</span>
                                            @endif
                                            @if(request('filter_date_from'))
                                            <span class="badge badge-info mr-2">From: {{ request('filter_date_from') }}</span>
                                            @endif
                                            @if(request('filter_date_to'))
                                            <span class="badge badge-info mr-2">To: {{ request('filter_date_to') }}</span>
                                            @endif
                                            @if(request('filter_section'))
                                            @php
                                            $selectedSection = $sections->firstWhere('id', request('filter_section'));
                                            @endphp
                                            <span class="badge badge-info mr-2">
                                                Section: {{ $selectedSection ? $selectedSection->section_name : 'Unknown' }}
                                            </span>
                                            @endif

                                            <a href="{{ route('course.manage') }}" class="btn btn-sm m-1 btn-outline-danger">
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
                                                    <th>Course Name</th>
                                                    <th>Section</th>
                                                    <th>Added On</th>
                                                    <th>Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach($courses as $index => $course)
                                                <tr>
                                                    <td>{{ $courses->firstItem() + $index }}</td>
                                                    <td>{{ $course->course_name }}</td>
                                                    <td>{{ $course->section->section_name ?? 'N/A' }}</td>
                                                    <td>{{ $course->created_at->format('d M Y') }}</td>
                                                    <td>
                                                        <a href="{{ route('course.edit', $course->id) }}" class="btn btn-primary btn-sm m-1">
                                                            <i class="fas fa-edit"></i>
                                                        </a>
                                                        <form action="{{ route('course.delete', $course->id) }}" method="POST" style="display:inline-block;">
                                                            @csrf
                                                            @method('DELETE')
                                                            <button type="submit" class="btn btn-danger btn-sm m-1">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </td>

                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>

                                        <div class="card-footer">
                                            {{ $courses->appends(request()->query())->links() }}
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
    @include('includes.edit_footer')