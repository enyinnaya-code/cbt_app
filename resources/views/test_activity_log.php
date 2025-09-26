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
                                <h4>Test Activity Log</h4>
                            </div>
                            <div class="card-body">
                                <!-- Table to display activity log -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="activityLogTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Activity</th>
                                                <th>Details</th>
                                                <th>Timestamp</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Hardcoded Activity Log Data -->
                                            <tr>
                                                <td>1</td>
                                                <td>Test Created</td>
                                                <td>Test "Maths Final Exam" was created by Admin.</td>
                                                <td>2025-05-05 08:30 AM</td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>Test Started</td>
                                                <td>Test "History Quiz" started by Admin.</td>
                                                <td>2025-05-06 09:00 AM</td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>Test Ended</td>
                                                <td>Test "Science Midterm" was ended by Admin.</td>
                                                <td>2025-05-05 03:30 PM</td>
                                            </tr>
                                            <tr>
                                                <td>4</td>
                                                <td>Test Updated</td>
                                                <td>Test "History Quiz" was updated by Admin.</td>
                                                <td>2025-05-06 11:00 AM</td>
                                            </tr>
                                            <tr>
                                                <td>5</td>
                                                <td>Test Reopened</td>
                                                <td>Test "Science Midterm" was reopened by Admin for review.</td>
                                                <td>2025-05-05 04:00 PM</td>
                                            </tr>
                                            <!-- More rows can be added for other activities -->
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
