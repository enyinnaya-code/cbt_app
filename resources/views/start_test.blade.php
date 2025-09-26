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
                                <h4>Start Test</h4>
                            </div>
                            <div class="card-body">

                                @if($tests->isEmpty())
                                <p>No tests available at the moment.</p>
                                @else
                                <div class="table-responsive">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>Test Name</th>
                                                <th>Test Type</th>
                                                <th>Duration (min)</th>
                                                <th>Class</th>
                                                <th>Start Date/Time</th>
                                                <th>End Date/Time</th><!-- New Column -->
                                                <th>Time Spent</th><!-- New Column -->
                                                <th>Score</th>
                                                <th>Result</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($tests as $test)
                                            <tr>
                                                <td>{{ $test->test_name }}</td>
                                                <td>{{ $test->test_type }}</td>
                                                <td>{{ $test->duration }}</td>
                                                <td>{{ $test->schoolClass->name }}</td>
                                                <td>{{ \Carbon\Carbon::parse($test->scheduled_date)->format('j-F-Y g:i A') }}</td>

                                                <!-- End Date/Time Column -->
                                                <td>
                                                    @php
                                                    $hasTakenTest = in_array($test->id, $takenTestIds ?? []);
                                                    $endTime = $testEndTimes[$test->id] ?? null;
                                                    @endphp

                                                    @if($hasTakenTest && $endTime)
                                                    {{ \Carbon\Carbon::parse($endTime)->format('j-F-Y g:i A') }}
                                                    @else
                                                    N/A
                                                    @endif
                                                </td>

                                                <!-- Time Spent Column -->
                                                <td>
                                                    @php
                                                    $startTime = $testStartTimes[$test->id] ?? null;
                                                    $endTime = $testEndTimes[$test->id] ?? null;
                                                    @endphp

                                                    @if($hasTakenTest && $startTime && $endTime)
                                                    @php
                                                    $start = \Carbon\Carbon::parse($startTime);
                                                    $end = \Carbon\Carbon::parse($endTime);
                                                    $diff = $start->diff($end);
                                                    $formattedTime = sprintf('%02d:%02d:%02d', $diff->h + ($diff->d * 24), $diff->i, $diff->s);
                                                    @endphp
                                                    {{ $formattedTime }}
                                                    @else
                                                    N/A
                                                    @endif
                                                </td>

                                                <td class="font-weight-bold">
                                                    @php
                                                    $score = $studentScores[$test->id] ?? null;
                                                    $total = $testTotalScores[$test->id] ?? null;
                                                    @endphp

                                                    @if($score !== null && $total !== null)
                                                    {{ $score }}/{{ $total }}
                                                    @else
                                                    N/A
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                    $isPassed = $isPassedFlags[$test->id] ?? null;
                                                    @endphp

                                                    @if(!$hasTakenTest)
                                                    <span class="">N/A</span>
                                                    @elseif($isPassed === 1)
                                                    <span class="text-success">Passed</span>
                                                    @elseif($isPassed === 0)
                                                    <span class="text-danger">Failed</span>
                                                    @else
                                                    <span class="">N/A</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    @php
                                                    $now = now();
                                                    $scheduledDate = \Carbon\Carbon::parse($test->scheduled_date);
                                                    $tenMinutesAfter = $scheduledDate->copy()->addMinutes(10);
                                                    $thirtyMinutesAfter = $scheduledDate->copy()->addMinutes(30);
                                                    $testEndTime = $scheduledDate->copy()->addMinutes($test->duration);
                                                    @endphp

                                                    @if($hasTakenTest)
                                                    <button class="btn btn-info btn-sm" disabled>Test Already Taken By You</button>

                                                    @elseif($now->lt($scheduledDate))
                                                    <button class="btn btn-info btn-sm" disabled>Test will be available at scheduled time.</button>

                                                    @elseif($now->between($scheduledDate, $tenMinutesAfter))
                                                    <button class="btn btn-success btn-sm"
                                                        data-toggle="modal"
                                                        data-target="#confirmModal"
                                                        data-id="{{ $test->id }}"
                                                        data-name="{{ $test->test_name }}"
                                                        data-type="{{ $test->test_type }}"
                                                        data-duration="{{ $test->duration }}"
                                                        data-time="{{ $scheduledDate->format('j-F-Y g:i A') }}">
                                                        Take Test
                                                    </button>

                                                    @elseif($now->between($tenMinutesAfter, $thirtyMinutesAfter))
                                                    <button class="btn btn-warning btn-sm"
                                                        data-toggle="modal"
                                                        data-target="#confirmModal"
                                                        data-id="{{ $test->id }}"
                                                        data-name="{{ $test->test_name }}"
                                                        data-type="{{ $test->test_type }}"
                                                        data-duration="{{ $test->duration }}"
                                                        data-time="{{ $scheduledDate->format('j-F-Y g:i A') }}">
                                                        Take Test (Late)
                                                    </button>

                                                    @else
                                                    <button class="btn btn-danger btn-sm" disabled>Time has passed (more than 30 mins late).</button>
                                                    @endif

                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                @endif

                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Single Reusable Modal -->
    <div class="modal fade" id="confirmModal" tabindex="-1" role="dialog" aria-labelledby="confirmModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header ">
                    <h5 class="modal-title" id="confirmModalLabel">Confirm Test Start</h5>
                    <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><strong>Are you sure you want to start this test?</strong></p>
                    <ul>
                        <li><strong>Test Name:</strong> <span id="modalTestName"></span></li>
                        <li><strong>Test Type:</strong> <span id="modalTestType"></span></li>
                        <li><strong>Duration:</strong> <span id="modalTestDuration"></span> minutes</li>
                        <li><strong>Scheduled Time:</strong> <span id="modalTestTime"></span></li>
                    </ul>
                    <h5><strong>Instructions</strong></h5>
                    <ul>
                        <li class="text-danger">Once you start, <strong>do not leave the page</strong>.</li>
                        <li class="text-danger">Leaving the page will <strong>automatically submit the test</strong> and mark it as completed.</li>
                        <li class="text-danger"><strong>Do not close the page or the browser</strong> during the test.</li>
                        <li class="text-danger"><strong>Do not reload the page</strong>.</li>
                        <li class="text-danger">Ensure you <strong>submit your scores before the countdown expires</strong>.</li>
                    </ul>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <a id="startTestBtn" href="#" class="btn btn-primary">Start Test</a>
                </div>
            </div>
        </div>
    </div>


    @include('includes.footer')

    <!-- JavaScript to populate modal -->
    <script>
        $('#confirmModal').on('show.bs.modal', function(event) {
            var button = $(event.relatedTarget);
            var testId = button.data('id');
            var testName = button.data('name');
            var testType = button.data('type');
            var testDuration = button.data('duration');
            var testTime = button.data('time');

            var modal = $(this);
            modal.find('#modalTestName').text(testName);
            modal.find('#modalTestType').text(testType);
            modal.find('#modalTestDuration').text(testDuration);
            modal.find('#modalTestTime').text(testTime);
            modal.find('#startTestBtn').attr('href', '{{ url("/test") }}/' + testId + '/take');

        });
    </script>

</body>

</html>