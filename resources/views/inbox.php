<?php include 'includes/head.php' ?>

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
                                <h4>All Announcements</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="tableExport" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Heading</th>
                                                <th>Content</th>
                                                <th>Date Posted</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- Example row - replace with dynamic PHP loop -->
                                            <tr>
                                                <td>1</td>
                                                <td>Exam Timetable Released</td>
                                                <td>The final exam timetable is now available. Check your portal.</td>
                                                <td>2025-05-01</td>
                                                <td>
                                                    <a href="edit_announcement.php?id=1" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="delete_announcement.php?id=1" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?');">Delete</a>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>Midterm Break</td>
                                                <td>School will be closed from May 10 to May 15 for midterm break.</td>
                                                <td>2025-04-28</td>
                                                <td>
                                                    <a href="edit_announcement.php?id=2" class="btn btn-sm btn-primary">Edit</a>
                                                    <a href="delete_announcement.php?id=2" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this announcement?');">Delete</a>
                                                </td>
                                            </tr>
                                            <!-- End sample row -->
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

    <?php include 'includes/footer.php' ?>

