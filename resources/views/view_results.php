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
                                <h4>Your Test Results</h4>
                            </div>
                            <div class="card-body">
                                <!-- Table displaying student results -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" style="width: 100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Test Name</th>
                                                <th>Test Type</th>
                                                <th>Date Taken</th>
                                                <th>Score</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Hardcoded data for now -->
                                            <tr>
                                                <td>1</td>
                                                <td>Math Final Exam</td>
                                                <td>Multiple Choice</td>
                                                <td>2025-05-01</td>
                                                <td>85%</td>
                                                <td><span class="badge badge-success">Passed</span></td>
                                                <td><a href="result_details.php?test_id=1" class="btn btn-info btn-sm">View</a></td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>English Grammar Test</td>
                                                <td>Short Answer</td>
                                                <td>2025-04-25</td>
                                                <td>62%</td>
                                                <td><span class="badge badge-warning">Average</span></td>
                                                <td><a href="result_details.php?test_id=2" class="btn btn-info btn-sm">View</a></td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>History Quiz</td>
                                                <td>True/False</td>
                                                <td>2025-04-20</td>
                                                <td>48%</td>
                                                <td><span class="badge badge-danger">Failed</span></td>
                                                <td><a href="result_details.php?test_id=3" class="btn btn-info btn-sm">View</a></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <!-- /table -->
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- JS Scripts -->
    <script src="assets/js/app.min.js"></script>
    <?php include 'includes/footer.php'; ?>
</body>
</html>
