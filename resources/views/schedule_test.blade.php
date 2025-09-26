@include('includes.head')

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('includes.right_top_nav')
            @include('includes.side_nav')

            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Schedule Tests</h4>
                                <div class="card-header-action mb-3">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#scheduleFilterCollapse">
                                        <i class="fas fa-filter"></i> Filter Scheduled Tests
                                    </button>
                                </div>
                            </div>

                            <div class="card-body">
                                <!-- Filter Form -->
                                <div class="collapse" id="scheduleFilterCollapse">
                                    <div class="card-body pb-0">
                                        <form method="GET" action="{{ route('tests.schedule') }}" class="row">
                                            <div class="form-group col-md-3">
                                                <label>Test Name</label>
                                                <input type="text" name="filter_test_name" class="form-control" value="{{ request('filter_test_name') }}">
                                            </div>
                                            <div class="form-group col-md-2">
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

                                            <div class="form-group col-md-2">
                                                <label>Duration (min)</label>
                                                <input type="number" name="filter_duration" class="form-control" value="{{ request('filter_duration') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Scheduled Date</label>
                                                <input type="date" name="filter_scheduled_date" class="form-control" value="{{ request('filter_scheduled_date') }}">
                                            </div>
                                            <div class="form-group col-md-2">
                                                <label>Subject</label>
                                                <input type="text" name="filter_subject" class="form-control" value="{{ request('filter_subject') }}">
                                            </div>
                                            <div class="form-group col-md-3">
                                                <label>Schedule Status</label>
                                                <select name="filter_schedule_status" class="form-control">
                                                    <option value="">-- Select --</option>
                                                    <option value="scheduled" {{ request('filter_schedule_status') == 'scheduled' ? 'selected' : '' }}>Scheduled</option>
                                                    <option value="not_scheduled" {{ request('filter_schedule_status') == 'not_scheduled' ? 'selected' : '' }}>Not Scheduled</option>
                                                </select>
                                            </div>
                                            <div class="form-group col-md-3 d-flex align-items-end">
                                                <button type="submit" class="btn btn-primary mr-2"><i class="fas fa-search"></i> Apply</button>
                                                <a href="{{ route('tests.schedule') }}" class="btn btn-light"><i class="fas fa-sync"></i> Reset</a>
                                            </div>
                                        </form>
                                    </div>
                                </div>

                                <!-- Active Filters Display -->
                                @if(
                                request('filter_test_name') || request('filter_class') || request('filter_duration') ||
                                request('filter_scheduled_date') || request('filter_subject') || request('filter_schedule_status')
                                )
                                <div class="mb-3">
                                    <h6>Active Filters:</h6>
                                    <div class="active-filters">
                                        @if(request('filter_test_name'))
                                        <span class="badge badge-info mr-2">Test Name: {{ request('filter_test_name') }}</span>
                                        @endif
                                        @if(request('filter_class'))
                                        @php
                                        $filteredClass = $classes->firstWhere('id', request('filter_class'));
                                        @endphp
                                        <span class="badge badge-info mr-2">Class: {{ $filteredClass->name ?? 'Unknown' }}</span>
                                        @endif

                                        @if(request('filter_duration'))
                                        <span class="badge badge-info mr-2">Duration: {{ request('filter_duration') }} min</span>
                                        @endif
                                        @if(request('filter_scheduled_date'))
                                        <span class="badge badge-info mr-2">Scheduled Date: {{ request('filter_scheduled_date') }}</span>
                                        @endif
                                        @if(request('filter_subject'))
                                        <span class="badge badge-info mr-2">Subject: {{ request('filter_subject') }}</span>
                                        @endif
                                        @if(request('filter_schedule_status'))
                                        <span class="badge badge-info mr-2">Status: {{ ucfirst(str_replace('_', ' ', request('filter_schedule_status'))) }}</span>
                                        @endif
                                        <a href="{{ route('tests.schedule') }}" class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                                @endif

                                <div class="table-responsive">
                                    <!-- Scheduled Tests Table -->
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Test Title</th>
                                                <th>Class</th>
                                                <th>Subject</th>
                                                <th>Scheduled Date</th>
                                                <th>Submission Date</th>
                                                <th>Scheduled By</th>

                                                <th>Status</th>
                                                <th>Action</th>

                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tests as $index => $test)
                                            <tr>
                                                <td>{{ $tests->firstItem() + $index }}</td>
                                                <td>{{ $test->test_name }}</td>
                                                <td>{{ $test->schoolClass->name ?? 'N/A' }}</td>
                                                <td>{{ $test->course->course_name ?? 'N/A' }}</td>
                                                <td>
                                                    <input
                                                        type="datetime-local"
                                                        name="scheduled_date"
                                                        id="scheduledDateInput-{{ $test->id }}"
                                                        class="form-control"
                                                        value="{{ old('scheduled_date', $test->scheduled_date) }}"
                                                        {{ $test->is_started ? 'disabled' : '' }}
                                                        required>
                                                </td>

                                                <td>{{ $test->created_at->format('j-F-Y g:i') }}</td>
                                                <!-- Status Column -->
                                                <td>{{ $test->scheduledBy->name ?? 'N/A' }}</td>

                                                <td>
                                                    @if($test->is_started)
                                                    <span class="badge badge-info">Test Active</span>
                                                    @elseif($test->scheduled_date)
                                                    <span class="badge badge-success">Scheduled</span>
                                                    @else
                                                    <span class="badge badge-warning">Not Scheduled</span>
                                                    @endif
                                                </td>


                                                <!-- Action Column -->
                                                <td>
                                                    @if($test->is_started)
                                                    <!-- Show Force Stop button -->
                                                    <form action="{{ route('tests.forceStop', $test->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel this test, this will clear all the records of already written tests by the students?');">
                                                        @csrf
                                                        <button type="submit" class="btn btn-danger btn-sm">Cancel-Test</button>
                                                    </form>
                                                    @elseif($test->scheduled_date)
                                                    <!-- Reschedule and Cancel buttons -->
                                                    <button type="button" class="btn m-1 btn-warning btn-sm" onclick="validateAndSchedule(this)" data-id="{{ $test->id }}">Reschedule</button>

                                                    <form action="{{ route('tests.cancelSchedule', $test->id) }}" method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to cancel the schedule and reset the test?');">
                                                        @csrf
                                                        <button type="submit" class="btn m-1 btn-primary px-3 btn-sm">Reset</button>
                                                    </form>
                                                    @else
                                                    <!-- Initial schedule button -->
                                                    <button type="button" class="btn btn-success btn-sm" onclick="validateAndSchedule(this)" data-id="{{ $test->id }}">Schedule</button>
                                                    @endif
                                                </td>

                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>

                                    <!-- Pagination -->
                                    <div class="d-flex justify-content-end">
                                        {{ $tests->appends(request()->query())->links() }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Scheduling Confirmation Modal -->
    <div class="modal fade" id="scheduleModal" tabindex="-1" role="dialog" aria-labelledby="scheduleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <form id="scheduleForm" method="POST">
                @csrf
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="scheduleModalLabel">Confirm Scheduling</h5>
                        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                            <span aria-hidden="true">&times;</span>
                        </button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to schedule this test for the selected Date/Time?
                        <input type="hidden" name="scheduled_date" id="modalScheduledDate">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Yes, Schedule</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modified function to validate date before showing modal
        function validateAndSchedule(button) {
            const testId = button.getAttribute('data-id');
            const input = document.getElementById(`scheduledDateInput-${testId}`);
            const scheduledDate = input ? input.value : '';

            if (!scheduledDate) {
                // Show toastr error and prevent modal from opening
                toastr.error("Please select a date and time before scheduling.");
                return false; // This prevents further execution
            }

            // If we have a date, set up modal data
            const urlParams = new URLSearchParams(window.location.search);

            // Base schedule URL
            let scheduleUrl = "{{ route('tests.saveSchedule', ':id') }}".replace(':id', testId);

            // Append current filters to maintain state after form submission
            const filterParams = [];
            ['filter_test_name', 'filter_class', 'filter_duration',
                'filter_scheduled_date', 'filter_subject', 'filter_schedule_status'
            ].forEach(param => {
                if (urlParams.has(param)) {
                    filterParams.push(`${param}=${urlParams.get(param)}`);
                }
            });

            // Add filter parameters to the form action if any exist
            if (filterParams.length > 0) {
                scheduleUrl += `?${filterParams.join('&')}`;
            }

            const form = document.getElementById('scheduleForm');
            form.action = scheduleUrl;
            document.getElementById('modalScheduledDate').value = scheduledDate;

            // Now we can show the modal
            $('#scheduleModal').modal('show');
            return true;
        }
    </script>

    @include('includes.footer')
</body>