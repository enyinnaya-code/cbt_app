<?php include 'includes/head.php' ?>

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            <?php include 'includes/right_top_nav.php' ?>
            <?php include 'includes/side_nav.php' ?>
            <!-- Main Content -->
            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Review Test Results</h4>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="tableExport" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Student Name</th>
                                                <th>Class</th>
                                                <th>Test Title</th>
                                                <th>Score</th>
                                                <th>Total Marks</th>
                                                <th>Percentage</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>1</td>
                                                <td>Jane Doe</td>
                                                <td>JSS1</td>
                                                <td>Maths First Term</td>
                                                <td>36</td>
                                                <td>40</td>
                                                <td>90%</td>
                                                <td><span class="badge badge-success">Passed</span></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm">View Answers</button>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td>2</td>
                                                <td>John Smith</td>
                                                <td>SSS2</td>
                                                <td>English Second Term</td>
                                                <td>18</td>
                                                <td>40</td>
                                                <td>45%</td>
                                                <td><span class="badge badge-danger">Failed</span></td>
                                                <td>
                                                    <button class="btn btn-info btn-sm">View Answers</button>
                                                </td>
                                            </tr>
                                            <!-- More results can be dynamically added -->
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
