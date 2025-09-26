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
                                <h4>Resume Test</h4>
                            </div>
                            <div class="card-body">
                                <!-- Test Information -->
                                <div class="form-group row mb-4">
                                    <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Name:</label>
                                    <div class="col-sm-12 col-md-7">
                                        <input type="text" class="form-control" value="Math Final Exam" readonly>
                                    </div>
                                </div>

                                <div class="form-group row mb-4">
                                    <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Type:</label>
                                    <div class="col-sm-12 col-md-7">
                                        <input type="text" class="form-control" value="Multiple Choice" readonly>
                                    </div>
                                </div>

                                <div class="form-group row mb-4">
                                    <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Time Left:</label>
                                    <div class="col-sm-12 col-md-7">
                                        <input type="text" class="form-control" value="45 minutes left" readonly>
                                    </div>
                                </div>

                                <!-- Resume Test Button -->
                                <div class="form-group row">
                                    <div class="col-12">
                                        <a href="test_page.php" class="btn btn-primary">Resume Test</a>
                                    </div>
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

    <?php include 'includes/footer.php'; ?>
</body>
</html>
