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
                                <h4>Manage Test Questions</h4>
                                <div class="card-header-action mb-3">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter Test Questions
                                    </button>

                                </div>
                            </div>

                            <div class="card-body">


                                <div class="collapse" id="filterCollapse">
                                    <div class="card-body pb-0">
                                        <form method="GET" action="{{ route('questions.index') }}" class="row">
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
                                                <label>Approval Status</label>
                                                <select name="filter_approval_status" class="form-control">
                                                    <option value="">-- Select --</option>
                                                    <option value="not_submitted" {{ request('filter_approval_status') == 'not_submitted' ? 'selected' : '' }}>Not Submitted</option>
                                                    <option value="action_needed" {{ request('filter_approval_status') == 'action_needed' ? 'selected' : '' }}>Action Needed</option>
                                                    <option value="not_approved" {{ request('filter_approval_status') == 'not_approved' ? 'selected' : '' }}>Not Approved</option>
                                                    <option value="approved" {{ request('filter_approval_status') == 'approved' ? 'selected' : '' }}>Approved</option>
                                                </select>
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
                                                <a href="{{ route('questions.index') }}" class="btn btn-light"><i class="fas fa-sync"></i> Reset</a>
                                            </div>
                                        </form>
                                    </div>
                                </div>


                                @if(
                                request('filter_test_name') || request('filter_test_type') || request('filter_duration') ||
                                request('filter_section') || request('filter_course') || request('filter_creator') ||
                                request('filter_date_from') || request('filter_date_to') || request('filter_approval_status')
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


                                        @if(request('filter_approval_status'))
                                        <span class="badge badge-info mr-2">
                                            Approval Status:
                                            @switch(request('filter_approval_status'))
                                            @case('not_submitted') Not Submitted @break
                                            @case('action_needed') Action Needed @break
                                            @case('not_approved') Not Approved @break
                                            @case('approved') Approved @break
                                            @endswitch
                                        </span>
                                        @endif




                                        <a href="{{ route('questions.index') }}" class="btn btn-sm btn-outline-danger">
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
                                                <th>Class</th>
                                                <th>Course</th>
                                                <th>Created By</th>
                                                <th>Date Created</th>
                                                <th>Submitted By</th>

                                                <th>Submission Date</th>
                                                <th>Approval Date</th>
                                                <th>Approval</th>
                                                <th>Approved By</th>
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
                                                <td>{{ $test->schoolClass->name ?? 'N/A' }}</td>
                                                <td>{{ $test->course->course_name ?? 'N/A' }}</td>
                                                <td>{{ $test->creator->name ?? 'N/A' }}</td>
                                                <td>{{ $test->created_at->format('j F Y g:i A') }}</td>
                                                <td>{{ $test->submittedBy->name ?? 'N/A' }}</td>

                                                <td>{{ $test->submission_date ? \Carbon\Carbon::parse($test->submission_date)->format('j F Y G:i A') : 'N/A' }}</td>
                                                <td>{{ $test->approval_date ? \Carbon\Carbon::parse($test->approval_date)->format('j F Y G:i A') : 'N/A' }}</td>


                                                <td>
                                                    @if ($test->is_submitted == 2)
                                                    <div class="badge badge-info">Action<br>Needed</div>
                                                    @elseif ($test->is_submitted)
                                                    @if ($test->is_approved)
                                                    <div class="badge badge-success">Approved</div>
                                                    @else
                                                    <div class="badge badge-warning">Not Yet<br>Approved</div>
                                                    @endif
                                                    @else
                                                    <div class="badge badge-danger">Not Yet<br>Submitted</div>
                                                    @endif
                                                </td>

                                                <td>
                                                    {{ $test->approvedBy->name ?? 'N/A' }}
                                                </td>

                                                <td>
                                                    @if (!$test->is_submitted || !$test->is_approved)

                                                    <a href="{{ route('tests.setQuestions', ['test' => $test->id]) }}" class="btn btn-success btn-sm m-1" title="Set Questions">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <a href="{{ route('questions.view', ['test' => $test->id]) }}" class="btn btn-info btn-sm m-1" title="View Questions">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    <!-- Submit for Approval Button -->
                                                    <button class="btn btn-warning btn-sm m-1"
                                                        data-toggle="modal"
                                                        data-target="#submitApprovalModal"
                                                        data-test="{{ $test->id }}"
                                                        data-name="{{ $test->test_name }}"
                                                        data-type="{{ ucfirst(str_replace('_', ' ', $test->test_type)) }}"
                                                        data-duration="{{ $test->duration }}"
                                                        data-created_at="{{ $test->created_at->format('Y-m-d') }}">
                                                        <i class="fas fa-check-circle"></i>
                                                    </button>

                                                    @else

                                                    <a href="{{ route('questions.view', ['test' => $test->id]) }}" class="btn btn-info btn-sm m-1" title="View Questions">
                                                        <i class="fas fa-eye"></i>
                                                    </a>

                                                    @endif
                                                </td>
                                            </tr>

                                            @endforeach
                                            @if($tests->isEmpty())
                                            <tr>
                                                <td colspan="15" class="text-center">No tests found.</td>
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

    <!-- Submit for Approval Modal -->
    <div class="modal fade" id="submitApprovalModal" tabindex="-1" role="dialog" aria-labelledby="submitApprovalModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="submitApprovalModalLabel">Submit Test for Approval</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to submit the test <strong id="testName"></strong> for approval?</p>
                    <form id="submitForm" method="POST" action="">
                        @csrf
                        <input type="hidden" name="test_id" id="test_id">
                        <div class="form-group pt-1 mb-0">
                            <label>Test Type</label>
                            <p id="test_type" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group pt-1 mb-0">
                            <label>Duration (minutes)</label>
                            <p id="test_duration" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group pt-1 mb-0">
                            <label>Date Created</label>
                            <p id="test_created_at" class="form-control-plaintext"></p>
                        </div>
                        <div class="form-group text-center">
                            <button type="submit" class="btn btn-primary">Yes, Submit</button>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">No, Cancel</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>



    <script>
        // Wait for the document to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // When the modal is about to be shown
            $('#submitApprovalModal').on('show.bs.modal', function(event) {
                console.log('Modal opening...');

                // Get the button that triggered the modal
                var button = $(event.relatedTarget);

                // Debug output to check what data is available
                console.log('Button data attributes:', {
                    test: button.data('test'),
                    name: button.data('name'),
                    type: button.data('type'),
                    duration: button.data('duration'),
                    created_at: button.data('created_at')
                });

                // Get data from the button's data attributes
                var testId = button.data('test') || '';
                var testName = button.data('name') || 'Unknown Test';
                var testType = button.data('type') || 'N/A';
                var testDuration = button.data('duration') || 'N/A';
                var testCreatedAt = button.data('created_at') || 'N/A';

                // Get the modal
                var modal = $(this);

                // Populate the hidden field and text fields
                modal.find('#test_id').val(testId);
                modal.find('#testName').text(testName);
                modal.find('#test_name').text(testName);
                modal.find('#test_type').text(testType);
                modal.find('#test_duration').text(testDuration);
                modal.find('#test_created_at').text(testCreatedAt);

                // Set the form action URL dynamically
                var formActionUrl = "{{ route('tests.submitForApproval', ['test' => ':test_id']) }}";
                formActionUrl = formActionUrl.replace(':test_id', testId);
                modal.find('#submitForm').attr('action', formActionUrl);

                console.log('Modal populated with data:', {
                    testId: testId,
                    testName: testName,
                    testType: testType,
                    testDuration: testDuration,
                    testCreatedAt: testCreatedAt,
                    formActionUrl: formActionUrl
                });
            });
        });
    </script>


    @include('includes.footer')