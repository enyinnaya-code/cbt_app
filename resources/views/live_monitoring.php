<?php include 'includes/head.php'; ?>

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            <?php include 'includes/right_top_nav.php'; ?>
            <?php include 'includes/side_nav.php'; ?>

            <!-- Main Content -->
            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Live Test Monitoring</h4>
                            </div>
                            <div class="card-body">
                                <!-- Table to display live test monitoring -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="liveMonitoringTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Test Name</th>
                                                <th>Status</th>
                                                <th>Participants</th>
                                                <th>Start Time</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Hardcoded Live Test Data -->
                                            <tr>
                                                <td>1</td>
                                                <td>Maths Final Exam</td>
                                                <td><span class="badge badge-success">In Progress</span></td>
                                                <td>30</td>
                                                <td>2025-05-06 10:00 AM</td>
                                                <td>
                                                    <a href='view_test_details.php?id=1' class='btn btn-info btn-sm'>View</a>
                                                    <a href='end_test.php?id=1' class='btn btn-danger btn-sm'>End Test</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>History Quiz</td>
                                                <td><span class="badge badge-warning">Pending</span></td>
                                                <td>0</td>
                                                <td>2025-05-06 12:00 PM</td>
                                                <td>
                                                    <a href='view_test_details.php?id=2' class='btn btn-info btn-sm'>View</a>
                                                    <a href='start_test.php?id=2' class='btn btn-primary btn-sm'>Start Test</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>Science Midterm</td>
                                                <td><span class="badge badge-danger">Completed</span></td>
                                                <td>25</td>
                                                <td>2025-05-05 03:00 PM</td>
                                                <td>
                                                    <a href='view_test_details.php?id=3' class='btn btn-info btn-sm'>View</a>
                                                    <a href='reopen_test.php?id=3' class='btn btn-success btn-sm'>Reopen</a>
                                                </td>
                                            </tr>
                                            <!-- More rows can be added for other tests -->
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

    <!-- JS Scripts -->
    <script src="assets/js/app.min.js"></script>
    <script>
        // Optional: Add any additional JavaScript functionality here
    </script>

    <?php include 'includes/footer.php'; ?>
</body>
</html>
