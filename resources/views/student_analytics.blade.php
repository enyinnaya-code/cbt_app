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
                            <div class="row justify-content-between px-2">
                                <div class=" p-4">
                                    <h6>Analytics</h6>
                                </div>
                            </div>

                            <div class="card-body">






                                @if($tests->isEmpty())
                                <div class="alert alert-info">You haven't taken any tests yet.</div>
                                @else
                                <table class="table table-bordered">
                                    <thead>
                                        <tr>
                                            <th>Test Name</th>
                                            <th>Score</th>
                                            <th>Total Marks</th>
                                            <th>Percentage</th>
                                            <th>Date Taken</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach($tests as $test)
                                        <tr>
                                            <td>{{ $test->test_name }}</td>
                                            <td>{{ $test->score }}</td>
                                            <td>{{ $test->test_total_score }}</td>
                                            <td>
                                                @if($test->test_total_score > 0)
                                                {{ round(($test->score / $test->test_total_score) * 100, 2) }}%
                                                @else
                                                N/A
                                                @endif
                                            </td>

                                            <td>{{ \Carbon\Carbon::parse($test->created_at)->format('d M, Y') }}</td>
                                        </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @endif

                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('includes.edit_footer')