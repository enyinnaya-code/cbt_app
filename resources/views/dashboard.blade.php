@include('includes.head')


<body>
  <div class="loader"></div>
  <div id="app">
    <div class="main-wrapper main-wrapper-1">
      <div class="navbar-bg"></div>
      @include('includes.right_top_nav' )
      @include('includes.side_nav')
      <!-- Main Content -->
      <div class="main-content">
        <section class="section mb-5 pb-5">
          @auth
          @if(auth()->user()->user_type == 1 || auth()->user()->user_type == 2)
          <div class="row">

            <!-- Total Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Total Created Tests</h5>
                          <h2 class="mb-3 font-18">{{ $totalTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-pencil-ruler fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 1 || auth()->user()->user_type == 2)
            <!-- Submitted Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Submitted Tests</h5>
                          <h2 class="mb-3 font-18">{{ $submittedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-paper-plane fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth




            @auth
            @if(auth()->user()->user_type == 1 || auth()->user()->user_type == 2)
            <!-- Approved Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Approved Tests</h5>
                          <h2 class="mb-3 font-18">{{ $approvedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-check-circle fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 1 || auth()->user()->user_type == 2)
            <!-- Not Submitted Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Not Submitted Tests</h5>
                          <h2 class="mb-3 font-18">{{ $notSubmittedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-exclamation-circle fa-3x text-danger" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth




            @auth
            @if(auth()->user()->user_type == 1 || auth()->user()->user_type == 2)
            <!-- Submitted But Not Yet Approved -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Submitted But Not Yet Approved</h5>
                          <h2 class="mb-3 font-18">{{ $submittedNotApprovedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-exclamation-circle fa-3x text-danger" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Approved but Not Scheduled -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Approved but Not Scheduled</h5>
                          <h2 class="mb-3 font-18">{{ $approvedNotScheduledTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-calendar-times fa-3x text-warning" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>



            <!-- Scheduled Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Scheduled Tests</h5>
                          <h2 class="mb-3 font-18">{{ $scheduledTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-calendar-alt fa-3x text-purple" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>


            <!-- Tests Taken -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Total Tests Taken</h5>
                          <h2 class="mb-3 font-18">{{ $testsTaken }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-play-circle fa-3x text-info" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>


          </div>


          <div class="row">
            <!-- Total Students -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Students</h5>
                          <h2 class="mb-3 font-18">{{ $totalStudents }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-users fa-3x text-info" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>



            <!-- Total Teachers -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Teachers</h5>
                          <h2 class="mb-3 font-18">{{ $totalTeachers }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-chalkboard-teacher fa-3x text-custom-primary" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>


            <!-- Total Admins -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Admins</h5>
                          <h2 class="mb-3 font-18">{{ $totalAdmins }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-user-shield fa-3x text-warning" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            <!-- Suspended Users -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Suspended Users</h5>
                          <h2 class="mb-3 font-18">{{ $suspendedUsers }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-user-slash fa-3x text-danger" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>



          </div>
          @endif
          @endauth


          <!-- Teachers cards -->
          <div class="row">


            @auth
            @if(auth()->user()->user_type == 3 )
            <!-- Total Tests created by teacher -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">My Created Tests</h5>
                          <h2 class="mb-3 font-18">{{ $myCreatedTests }}</h2>
                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-pencil-ruler fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            @endif
            @endauth



            @auth
            @if(auth()->user()->user_type == 3)
            <!-- Submitted Tests for currently logged in user -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">My Submitted Tests</h5>
                          <h2 class="mb-3 font-18">{{ $mySubmittedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-paper-plane fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 3)
            <!-- Approved Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">My Approved Tests</h5>
                          <h2 class="mb-3 font-18">{{ $myApprovedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-check-circle fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 3)
            <!-- Not Submitted Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">My Not Submitted Tests</h5>
                          <h2 class="mb-3 font-18">{{ $myNotSubmittedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-exclamation-circle fa-3x text-danger" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth

          </div>



          <div class="row">



            @auth
            @if(auth()->user()->user_type == 4)
            <!-- Tests Taken by Student -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Tests Taken</h5>
                          <h2 class="mb-1 font-18">{{ $studentTestsTaken }}</h2>
                          <p class="mb-0 text-success" style="font-size: 14px;">({{ $submittedCount }} submitted)</p>
                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-clipboard-check fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 4)
            <!-- Available Tests -->
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Failed Tests</h5>
                          <h2 class="mb-3 font-18">{{ $failedTestsCount }}</h2>
                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-times-circle fa-3x text-danger" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>

            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 4)
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class="align-items-center justify-content-between">
                    <div class="row px-3 py-2">
                      <div class="col-6 pr-0 pt-3">
                        <div class="card-content">
                          <h5 class="font-15">Passed Tests</h5>
                          <h2 class="mb-3 font-18">{{ $passedTests }}</h2>

                        </div>
                      </div>
                      <div class="col-6 pl-0 text-center pt-4">
                        <i class="fas fa-check-circle fa-3x text-success" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth


            @auth
            @if(auth()->user()->user_type == 4)
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6 col-xs-12">
              <div class="card" style="height: 180px;">
                <div class="card-statistic-4">
                  <div class=" row align-items-center px-3 justify-content-between">
                    <div class="row px-3 py-4">
                      <div class="col-12 text-center">
                        <div class="card-content" style="font-weight: 700;">

                          <span class="col-green">{{ $passPercentage }}%</span> Passed<br>

                          <span class="col-red">{{ $failPercentage }}%</span> Failed

                        </div>
                      </div>
                    </div>
                    <div class="row px-3">
                      <div class="col-12 text-center">
                        <i class="fas fa-chart-pie fa-2x text-info mt-2" style="font-size: 1.2rem;"></i>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
            @endif
            @endauth

          </div>


          @auth
          @if(auth()->user()->user_type == 4)
          <div class="row">
            <div class="col-12 col-sm-12 col-lg-12">
              <div class="card ">

                <div class="card-header">
                  <h4>Last Five Test Scores</h4>
                  <div class="card-header-action">


                  </div>
                </div>
                <div class="card-body">
                  <canvas id="examChart" height="100"></canvas>
                </div>

              </div>
            </div>
            <script>
              const examLabels = @json($examLabels);
              const scores = @json($scores);
            </script>



          </div>
          @endif
          @endauth

          @auth
          @if(auth()->user()->user_type == 1 || auth()->user()->user_type == 2 || auth()->user()->user_type == 3)
          <div class="row mt-4">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h4>Test Pass/Fail Statistics</h4>
                </div>
                <div class="card-body">
                  <div style="overflow-x: auto; white-space: nowrap;">
                    <canvas id="passFailChart" height="180" style="min-width: 700px;"></canvas>
                  </div>
                </div>
              </div>
            </div>
          </div>

          @endif
          @endauth


          <!-- <div class="row">
            <div class="col-12 col-sm-12 col-lg-6">
              <div class="card">
                <div class="card-header">
                  <h4>Students Who Took Tests (Last 5 Years)</h4>
                </div>
                <div class="card-body">
                  <canvas id="chart4" height="180"></canvas>
                </div>
              </div>
            </div>





            <div class="col-12 col-sm-12 col-lg-6">
              <div class="card">
                <div class="card-header">
                  <h4>Success vs Failure Rate (Past 5 Years)</h4>
                </div>
                <div class="card-body">
                  <div class="summary">
                    <div class="summary-chart active" data-tab-group="summary-tab" id="summary-chart">
                      <canvas id="chart3" height="180"></canvas>
                    </div>
                    <div data-tab-group="summary-tab" id="summary-text">
                     
                    </div>
                  </div>
                </div>
              </div>
            </div>

          </div> -->


          @auth
          @if(auth()->user()->user_type == 4)
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h4>Last Five Tests</h4>
                  <div class="card-header-form">
                  </div>
                </div>
                <div class="card-body p-0 px-4">
                  <div class="table-responsive">
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
                  </div>
                </div>
              </div>
            </div>
          </div>

          @endif
          @endauth
          <!-- <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h4>Exam Statistics by Year</h4>
                  <div class="card-header-form">
                    <form>
                      <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search Year">
                        <div class="input-group-btn">
                          <button class="btn btn-primary"><i class="fas fa-search"></i></button>
                        </div>
                      </div>
                    </form>
                  </div>
                </div>
                <div class="card-body p-0 px-4">
                  <div class="table-responsive">
                    <table class="table table-striped table-bordered">
                      <tr>
                        <th>Year</th>
                        <th>Students Enrolled</th>
                        <th>No. Who Wrote</th>
                        <th>Absentees</th>
                        <th>Passes</th>
                        <th>Failures</th>
                      </tr>
                      <tr>
                        <td>2022</td>
                        <td>1,000</td>
                        <td>950</td>
                        <td>50</td>
                        <td>780</td>
                        <td>170</td>
                      </tr>
                      <tr>
                        <td>2023</td>
                        <td>1,200</td>
                        <td>1,150</td>
                        <td>50</td>
                        <td>890</td>
                        <td>260</td>
                      </tr>
                      <tr>
                        <td>2024</td>
                        <td>1,100</td>
                        <td>1,070</td>
                        <td>30</td>
                        <td>910</td>
                        <td>160</td>
                      </tr>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </div> -->

          <!-- <div class="row">
            <div class="col-md-6 col-lg-12 col-xl-6">
             
              <div class="card">
                <div class="card-header">
                  <h4>Support Tickets</h4>
                  <form class="card-header-form">
                    <input type="text" name="search" class="form-control" placeholder="Search Tickets">
                  </form>
                </div>
                <div class="card-body">
                  <div class="support-ticket media pb-1 mb-3">
                    <img src="images/profile-place-holder.jpg" class="user-img mr-2" alt="">
                    <div class="media-body ml-3">
                      <div class="badge badge-pill badge-info mb-1 float-right">Student</div>
                      <span class="font-weight-bold">#ST1001</span>
                      <a href="javascript:void(0)">Issue with exam portal login</a>
                      <p class="my-1">I am unable to log into the exam portal. Kindly assist.</p>
                      <small class="text-muted">Created by <span class="font-weight-bold font-13">Emily Stone</span>
                        &nbsp;&nbsp; - 1 day ago</small>
                    </div>
                  </div>

                  <div class="support-ticket media pb-1 mb-3">
                    <img src="images/profile-place-holder.jpg" class="user-img mr-2" alt="">
                    <div class="media-body ml-3">
                      <div class="badge badge-pill badge-primary mb-1 float-right">Teacher</div>
                      <span class="font-weight-bold">#TC2045</span>
                      <a href="javascript:void(0)">Unable to upload results</a>
                      <p class="my-1">The result upload form shows an error after submission.</p>
                      <small class="text-muted">Created by <span class="font-weight-bold font-13">Mr. David Carter</span>
                        &nbsp;&nbsp; - 2 days ago</small>
                    </div>
                  </div>

                  <div class="support-ticket media pb-1 mb-3">
                    <img src="images/profile-place-holder.jpg" class="user-img mr-2" alt="">
                    <div class="media-body ml-3">
                      <div class="badge badge-pill badge-warning mb-1 float-right">Student</div>
                      <span class="font-weight-bold">#ST1022</span>
                      <a href="javascript:void(0)">Result not showing on dashboard</a>
                      <p class="my-1">My result page is blank after the recent update.</p>
                      <small class="text-muted">Created by <span class="font-weight-bold font-13">Michael Lee</span>
                        &nbsp;&nbsp; - 3 days ago</small>
                    </div>
                  </div>
                </div>
                <a href="javascript:void(0)" class="card-footer card-link text-center small">View All</a>
              </div>

          
            </div>
            <div class="col-md-6 col-lg-12 col-xl-6">
              <div class="card">
                <div class="card-header">
                  <h4>Recent Test Submissions</h4>
                </div>
                <div class="card-body">
                  <div class="table-responsive">
                    <table class="table table-hover mb-0">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Student Name</th>
                          <th>Test</th>
                          <th>Score</th>
                          <th>Submitted On</th>
                        </tr>
                      </thead>
                      <tbody>
                        <tr>
                          <td>1</td>
                          <td>Mary Johnson</td>
                          <td>Mathematics</td>
                          <td>85%</td>
                          <td>06-05-2025</td>
                        </tr>
                        <tr>
                          <td>2</td>
                          <td>James Smith</td>
                          <td>English</td>
                          <td>92%</td>
                          <td>06-05-2025</td>
                        </tr>
                        <tr>
                          <td>3</td>
                          <td>Olivia Brown</td>
                          <td>Biology</td>
                          <td>76%</td>
                          <td>05-05-2025</td>
                        </tr>
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>

            </div>
          </div> -->
        </section>
      </div>


      <!-- Chart.js Library (before closing body tag if not already present) -->
      <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

      <!-- <script>
        const ctx = document.getElementById('examChart').getContext('2d');

        const examChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: ['Math', 'English', 'Biology', 'Physics', 'History'],
            datasets: [{
                label: 'Pass Rate (%)',
                data: [85, 78, 90, 72, 80],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                fill: true,
                tension: 0.4
              },
              {
                label: 'Fail Rate (%)',
                data: [15, 22, 10, 28, 20],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                fill: true,
                tension: 0.4
              }
            ]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                position: 'top'
              },
              tooltip: {
                mode: 'index',
                intersect: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                title: {
                  display: true,
                  text: 'Percentage'
                }
              }
            }
          }
        });
      </script> -->

      <script>
        const ctx = document.getElementById('examChart').getContext('2d');

        const examChart = new Chart(ctx, {
          type: 'line',
          data: {
            labels: examLabels,
            datasets: [{
              label: 'Exam Score',
              data: scores,
              borderColor: '#007bff',
              backgroundColor: 'rgba(0, 123, 255, 0.1)',
              fill: true,
              tension: 0.4
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                position: 'top'
              },
              tooltip: {
                mode: 'index',
                intersect: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                title: {
                  display: true,
                  text: 'Score (%)'
                }
              }
            }
          }
        });
      </script>


      <script>
        const testPassFailLabels = @json($testLabels);
        const testPassCounts = @json($passCounts);
        const testFailCounts = @json($failCounts);

        const passFailChartCanvas = document.getElementById('passFailChart').getContext('2d');
        const testPassFailBarChart = new Chart(passFailChartCanvas, {
          type: 'bar',
          data: {
            labels: testPassFailLabels,
            datasets: [{
                label: 'Passed',
                data: testPassCounts,
                backgroundColor: 'rgba(40, 167, 69, 0.7)',
                borderColor: 'rgba(40, 167, 69, 1)',
                borderWidth: 1
              },
              {
                label: 'Failed',
                data: testFailCounts,
                backgroundColor: 'rgba(220, 53, 69, 0.7)',
                borderColor: 'rgba(220, 53, 69, 1)',
                borderWidth: 1
              }
            ]
          },
          options: {
            responsive: true,
            scales: {
              y: {
                beginAtZero: true,
                precision: 0,
                title: {
                  display: true,
                  text: 'Number of Students'
                }
              },
              x: {
                title: {
                  display: true,
                  text: 'Tests (Class)'
                }
              }
            }
          }
        });
      </script>




      <script>
        const ctx4 = document.getElementById('chart4').getContext('2d');

        new Chart(ctx4, {
          type: 'line',
          data: {
            labels: ['2020', '2021', '2022', '2023', '2024'],
            datasets: [{
              label: 'Students Tested',
              data: [320, 450, 510, 600, 750], // Hardcoded numbers
              borderColor: '#007bff',
              backgroundColor: 'rgba(0, 123, 255, 0.1)',
              fill: true,
              tension: 0.4
            }]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                display: true
              },
              tooltip: {
                mode: 'index',
                intersect: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                title: {
                  display: true,
                  text: 'Number of Students'
                }
              }
            }
          }
        });
      </script>



      <script>
        const ctx3 = document.getElementById('chart3').getContext('2d');

        new Chart(ctx3, {
          type: 'line',
          data: {
            labels: ['2020', '2021', '2022', '2023', '2024'],
            datasets: [{
                label: 'Success Rate (%)',
                data: [75, 80, 85, 78, 82],
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                borderDash: [2, 2], // dotted line
                fill: false,
                tension: 0.4
              },
              {
                label: 'Failure Rate (%)',
                data: [25, 20, 15, 22, 18],
                borderColor: '#dc3545',
                backgroundColor: 'rgba(220, 53, 69, 0.1)',
                borderDash: [2, 2], // dotted line
                fill: false,
                tension: 0.4
              }
            ]
          },
          options: {
            responsive: true,
            plugins: {
              legend: {
                display: true
              },
              tooltip: {
                mode: 'index',
                intersect: false
              }
            },
            scales: {
              y: {
                beginAtZero: true,
                max: 100,
                title: {
                  display: true,
                  text: 'Percentage (%)'
                }
              }
            }
          }
        });
      </script>


      @include('includes.footer')