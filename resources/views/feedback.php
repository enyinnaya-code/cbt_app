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
                                <h4>Feedback</h4>
                            </div>
                            <div class="card-body">
                                <form action="process_feedback.php" method="POST">
                                    <!-- Feedback Title -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Your Feedback Title</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="text" name="feedback_title" class="form-control" required>
                                        </div>
                                    </div>

                                    <!-- Feedback Rating -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Rating</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="rating" class="form-control" required>
                                                <option value="">Select Rating</option>
                                                <option value="1">1 - Poor</option>
                                                <option value="2">2 - Fair</option>
                                                <option value="3">3 - Good</option>
                                                <option value="4">4 - Very Good</option>
                                                <option value="5">5 - Excellent</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Feedback Comments -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Comments</label>
                                        <div class="col-sm-12 col-md-7">
                                            <textarea name="comments" class="form-control" rows="6" placeholder="Enter your comments..." required></textarea>
                                        </div>
                                    </div>

                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Would you recommend this test/course?</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="recommend" class="form-control" required>
                                                <option value="">Select</option>
                                                <option value="Yes">Yes</option>
                                                <option value="No">No</option>
                                            </select>
                                        </div>
                                    </div>

                                    <div class="card-footer text-right">
                                        <button type="submit" class="btn btn-primary">Submit Feedback</button>
                                    </div>
                                </form>
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
