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
                <section class="section px-3 pb-5">
                    <div class="col-12 col-md-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Contact Support</h4>
                            </div>
                            <div class="card-body col-md-6">
                                <form method="POST" action="submit_support_ticket.php" class="needs-validation" novalidate>
                                    <!-- Name -->
                                    <div class="form-group">
                                        <label for="name">Full Name</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>

                                    <!-- Email -->
                                    <div class="form-group">
                                        <label for="email">Email Address</label>
                                        <input type="email" class="form-control" name="email" required>
                                    </div>

                                    <!-- Subject -->
                                    <div class="form-group">
                                        <label for="subject">Subject</label>
                                        <input type="text" class="form-control" name="subject" required>
                                    </div>

                                    <!-- Message -->
                                    <div class="form-group">
                                        <label for="message">Message</label>
                                        <textarea class="form-control" name="message" rows="6" required></textarea>
                                    </div>

                                    <div class="form-group text-left">
                                        <button type="submit" class="btn btn-primary">Send Message</button>
                                    </div>
                                </form>
                            </div>
                            <div class="card-footer text-muted text-center">
                                Response time: within 24 hours
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
