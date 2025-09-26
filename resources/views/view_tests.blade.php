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
                                <h4>Approve Tests</h4>
                                <div class="card-header-action mb-3">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter Tests
                                    </button>

                                </div>
                            </div>
                            <div class="card-body">

                                <div class="collapse" id="filterCollapse">
                                    <div class="card-body pb-0">
                                        <form method="GET" action="{{ route('tests.view') }}" class="row">
                                            <div class="form-group col-md-3">
                                                <label>Test Title</label>
                                                <input type="text" name="filter_test_title" class="form-control" value="{{ request('filter_test_title') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Class</label>
                                                <select name="filter_class" class="form-control">
                                                    <option value="">-- Select Class --</option>
                                                    @foreach($classes as $class)
                                                    <option value="{{ $class->id }}" {{ request('filter_class') == $class->id ? 'selected' : '' }}>
                                                        {{ $class->name }}
                                                    </option>
                                                    @endforeach
                                                </select>

                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Subject</label>
                                                <input type="text" name="filter_subject" class="form-control" value="{{ request('filter_subject') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Duration (mins)</label>
                                                <input type="number" name="filter_duration" class="form-control" value="{{ request('filter_duration') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Total Questions</label>
                                                <input type="number" name="filter_total_questions" class="form-control" value="{{ request('filter_total_questions') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Created By</label>
                                                <input type="text" name="filter_created_by" class="form-control" value="{{ request('filter_created_by') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Submitted By</label>
                                                <input type="text" name="filter_submitted_by" class="form-control" value="{{ request('filter_submitted_by') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Submitted On</label>
                                                <input type="date" name="filter_submitted_on" class="form-control" value="{{ request('filter_submitted_on') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Approved On</label>
                                                <input type="date" name="filter_approved_on" class="form-control" value="{{ request('filter_approved_on') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Approved By</label>
                                                <input type="text" name="filter_approved_by" class="form-control" value="{{ request('filter_approved_by') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Status</label>
                                                <select name="filter_status" class="form-control">
                                                    <option value="">-- Select --</option>
                                                    <option value="not_submitted" {{ request('filter_status') == 'not_submitted' ? 'selected' : '' }}>Not Submitted</option>
                                                    <option value="not_approved" {{ request('filter_status') == 'not_approved' ? 'selected' : '' }}>Not Approved</option>
                                                    <option value="action_needed" {{ request('filter_status') == 'action_needed' ? 'selected' : '' }}>Action Needed</option>
                                                    <option value="approved" {{ request('filter_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Apply</button>
                                                <a href="{{ route('tests.view') }}" class="btn btn-light"><i class="fas fa-sync"></i> Reset</a>
                                            </div>
                                        </form>


                                    </div>
                                </div>


                                @if(
                                request('filter_test_title') || request('filter_class') || request('filter_subject') ||
                                request('filter_duration') || request('filter_total_questions') || request('filter_created_by') ||
                                request('filter_submitted_by') || request('filter_submitted_on') || request('filter_approved_on') ||
                                request('filter_approved_by') || request('filter_status')
                                )
                                <div class="mb-3">
                                    <h6>Active Filters:</h6>
                                    <div class="active-filters">
                                        @if(request('filter_test_title'))
                                        <span class="badge badge-info mr-2">Test Title: {{ request('filter_test_title') }}</span>
                                        @endif

                                        @php
                                        $selectedClass = $classes->firstWhere('id', request('filter_class'));
                                        @endphp

                                        @if(request('filter_class') && $selectedClass)
                                        <span class="badge badge-info mr-2">Class: {{ $selectedClass->name }}</span>
                                        @endif


                                        @if(request('filter_subject'))
                                        <span class="badge badge-info mr-2">Subject: {{ request('filter_subject') }}</span>
                                        @endif

                                        @if(request('filter_duration'))
                                        <span class="badge badge-info mr-2">Duration: {{ request('filter_duration') }} mins</span>
                                        @endif

                                        @if(request('filter_total_questions'))
                                        <span class="badge badge-info mr-2">Total Questions: {{ request('filter_total_questions') }}</span>
                                        @endif

                                        @if(request('filter_created_by'))
                                        <span class="badge badge-info mr-2">Created By: {{ request('filter_created_by') }}</span>
                                        @endif

                                        @if(request('filter_submitted_by'))
                                        <span class="badge badge-info mr-2">Submitted By: {{ request('filter_submitted_by') }}</span>
                                        @endif

                                        @if(request('filter_submitted_on'))
                                        <span class="badge badge-info mr-2">Submitted On: {{ request('filter_submitted_on') }}</span>
                                        @endif

                                        @if(request('filter_approved_on'))
                                        <span class="badge badge-info mr-2">Approved On: {{ request('filter_approved_on') }}</span>
                                        @endif

                                        @if(request('filter_approved_by'))
                                        <span class="badge badge-info mr-2">Approved By: {{ request('filter_approved_by') }}</span>
                                        @endif

                                        @if(request('filter_status'))
                                        <span class="badge badge-info mr-2">
                                            Status:
                                            @switch(request('filter_status'))
                                            @case('not_submitted') Not Submitted @break
                                            @case('not_approved') Not Approved @break
                                            @case('approved') Approved @break
                                            @default {{ request('filter_status') }}
                                            @endswitch
                                        </span>
                                        @endif

                                        <a href="{{ route('tests.view') }}" class="btn btn-sm btn-outline-danger">
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
                                                <th>Test Title</th>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th>Duration (mins)</th>
                                                <th>Total Questions</th>
                                                <th>Created By</th>
                                                <th>Submitted By</th>
                                                <th>Submitted On</th>
                                                <th>Approved On</th>
                                                <th>Approved By</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tests as $index => $test)
                                            <tr>
                                                <td>{{ $index + 1 }}</td>
                                                <td>{{ $test->test_name }}</td>
                                                <td>{{ $test->schoolClass->name ?? 'N/A' }}</td>
                                                <td>{{ $test->course->course_name ?? 'N/A' }}</td>
                                                <td>{{ $test->duration }}</td>
                                                <td>{{ $test->total_questions }}</td> <!-- Display total questions -->
                                                <td>{{ $test->createdBy->name ?? 'N/A' }}</td>
                                                <td>{{ $test->submittedBy->name ?? 'N/A' }}</td>
                                                <td>{{ $test->submission_date ? $test->submission_date->format('j F Y g:i A') : 'N/A' }}</td>

                                                <td>{{ $test->approval_date  ?$test->approval_date->format('j F Y g:i A') : 'N/A' }}</td>


                                                <td>{{ $test->approvedBy->name ?? 'N/A' }}</td>


                                                <td>
                                                    @if($test->is_submitted == 2)
                                                    <span class="badge badge-info">Action Needed</span>
                                                    @elseif($test->is_submitted == 0)
                                                    <span class="badge badge-warning">Not Submitted</span>
                                                    @elseif($test->is_approved == 0)
                                                    <span class="badge badge-danger">Not Approved</span>
                                                    @else
                                                    <span class="badge badge-success">Approved</span>
                                                    @endif
                                                </td>


                                                <td>
                                                    @if($test->is_submitted == 1)
                                                    <!-- View Button: Display if test is approved or just submitted -->
                                                    <a href="{{ route('tests.check', ['test' => $test->id]) }}" class="btn btn-info btn-sm m-1" title="View Test">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <!-- Approve Button: Only if submitted but not yet approved -->
                                                    @if($test->is_approved == 0)
                                                    <button
                                                        class="btn btn-success btn-sm m-1"
                                                        title="Approve Test"
                                                        data-toggle="modal"
                                                        data-target="#approveModal"
                                                        data-id="{{ $test->id }}"
                                                        onclick="setApproveLink(this)">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>
                                                    @endif
                                                    @else
                                                    <span class="badge badge-light">x</span>
                                                    @endif
                                                </td>


                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                    <div class="d-flex justify-content-center mt-4">
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

    <!-- Approval Confirmation Modal -->
    <div class="modal fade" id="approveModal" tabindex="-1" role="dialog" aria-labelledby="approveModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="approveModalLabel">Confirm Approval</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to approve this test?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a href="#" id="confirmApproveBtn" class="btn btn-success">Yes, Approve</a>
                </div>
            </div>
        </div>
    </div>



    <script>
        function setApproveLink(button) {
            const testId = button.getAttribute('data-id');
            const approveUrl = "{{ route('tests.approve', ':test') }}".replace(':test', testId);
            document.getElementById('confirmApproveBtn').href = approveUrl;
        }
    </script>


    @include('includes.footer')