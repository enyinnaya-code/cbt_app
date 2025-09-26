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
                                <h4>Grade Submissions</h4>
                            </div>
                            <div class="card-body">
                                <!-- Table to display submissions -->
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="submissionsTable" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Student Name</th>
                                                <th>Test Name</th>
                                                <th>Submission Date</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Hardcoded Submissions Data -->
                                            <tr>
                                                <td>1</td>
                                                <td>John Doe</td>
                                                <td>Maths Final Exam</td>
                                                <td>2025-05-05 10:00 AM</td>
                                                <td><span class="badge badge-success">Submitted</span></td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#gradeModal1">Grade</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>Jane Smith</td>
                                                <td>History Quiz</td>
                                                <td>2025-05-06 02:30 PM</td>
                                                <td><span class="badge badge-warning">In Review</span></td>
                                                <td>
                                                    <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#gradeModal2">Grade</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>3</td>
                                                <td>Michael Johnson</td>
                                                <td>Science Midterm</td>
                                                <td>2025-05-05 04:45 PM</td>
                                                <td><span class="badge badge-danger">Not Submitted</span></td>
                                                <td>
                                                    <button class="btn btn-secondary btn-sm" disabled>Not Available</button>
                                                </td>
                                            </tr>
                                            <!-- More rows can be added dynamically -->
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

    <!-- Modal for Grading Submission 1 -->
    <div class="modal fade" id="gradeModal1" tabindex="-1" role="dialog" aria-labelledby="gradeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gradeModalLabel">Grade Submission: John Doe</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="process_grade_submission.php" method="POST">
                        <div class="form-group">
                            <label for="grade">Grade</label>
                            <input type="number" name="grade" class="form-control" required placeholder="Enter Grade (0-100)">
                        </div>
                        <div class="form-group">
                            <label for="comments">Comments</label>
                            <textarea name="comments" class="form-control" rows="4" placeholder="Add comments..."></textarea>
                        </div>
                        <input type="hidden" name="student_id" value="1">
                        <input type="hidden" name="test_id" value="1">
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Submit Grade</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal for Grading Submission 2 -->
    <div class="modal fade" id="gradeModal2" tabindex="-1" role="dialog" aria-labelledby="gradeModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="gradeModalLabel">Grade Submission: Jane Smith</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <form action="process_grade_submission.php" method="POST">
                        <div class="form-group">
                            <label for="grade">Grade</label>
                            <input type="number" name="grade" class="form-control" required placeholder="Enter Grade (0-100)">
                        </div>
                        <div class="form-group">
                            <label for="comments">Comments</label>
                            <textarea name="comments" class="form-control" rows="4" placeholder="Add comments..."></textarea>
                        </div>
                        <input type="hidden" name="student_id" value="2">
                        <input type="hidden" name="test_id" value="2">
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Submit Grade</button>
                        </div>
                    </form>
                </div>
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
