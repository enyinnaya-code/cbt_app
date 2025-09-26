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
                                <h4>Previous Test</h4>
                            </div>
                            <div class="card-body">

                                @if($tests->isEmpty())
                                <p>No tests available at the moment.</p>
                                @else
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Test Name</th>
                                            <th>Test Type</th>
                                            <th>Duration (min)</th>
                                            <th>Class</th>
                                            <th>Start Date/Time</th>
                                            <th>End Time</th>
                                            <th>Time Spent</th>
                                            <th>Score</th>
                                            <th>Result</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tests as $test)
                                        @php
                                        $data = $studentTestData[$test->id] ?? null;
                                        @endphp
                                        <tr>
                                            <td>{{ $test->test_name }}</td>
                                            <td>{{ $test->test_type }}</td>
                                            <td>{{ $test->duration }}</td>
                                            <td>{{ $test->schoolClass->name }}</td>
                                            <td>
                                                @if($data && $data->start_time)
                                                {{ \Carbon\Carbon::parse($data->start_time)->format('j-F-Y g:i A') }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td>
                                                @if($data && $data->end_time)
                                                {{ \Carbon\Carbon::parse($data->end_time)->format('j-F-Y g:i A') }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td>
                                                @if($data && $data->start_time && $data->end_time)
                                                @php
                                                $start = \Carbon\Carbon::parse($data->start_time);
                                                $end = \Carbon\Carbon::parse($data->end_time);
                                                @endphp
                                                {{ $start->diff($end)->format('%H:%I:%S') }}
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td class="font-weight-bold">
                                                @if($data)
                                                {{ $data->score }}/{{ $data->test_total_score }}
                                                @else
                                                Not Taken
                                                @endif
                                            </td>
                                            <td>
                                                @if($data)
                                                @if($data->is_passed)
                                                <span class="text-success">Passed</span>
                                                @else
                                                <span class="text-danger">Failed</span>
                                                @endif
                                                @else
                                                -
                                                @endif
                                            </td>
                                            <td>
                                                <a href="{{ route('tests.viewPast', ['testId' => $test->id]) }}" class="btn btn-primary btn-sm" title="View Past Questions">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>


                                @endif
                                <div class="d-flex justify-content-center mt-4">
                                    {{ $tests->links() }}
                                </div>

                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    @include('includes.footer')