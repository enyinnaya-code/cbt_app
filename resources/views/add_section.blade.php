@include('includes.head')

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('includes.right_top_nav' )
            @include('includes.side_nav')
            <!-- Main Content -->
            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <form action="{{ route('section.store') }}" method="POST" class="needs-validation col-md-6" novalidate>
                                @csrf
                                <div class="card-header">
                                    <h4>Add Section/Arm</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group col-md-12 px-0">
                                        <label for="section_name">Section/Arm Name</label>
                                        <input type="text" name="section_name" id="section_name" class="form-control" required placeholder="Section/Arm Name">
                                        <div class="valid-feedback">
                                            Looks good!
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-left mt-5 pt-5">
                                    <button class="btn btn-primary" type="submit">Submit</button>
                                </div>
                            </form>

                        </div>

                        <!-- Validation Script -->
                        <script>
                            // Bootstrap validation
                            (function() {
                                'use strict';
                                window.addEventListener('load', function() {
                                    const forms = document.getElementsByClassName('needs-validation');
                                    Array.prototype.filter.call(forms, function(form) {
                                        form.addEventListener('submit', function(event) {
                                            if (!form.checkValidity()) {
                                                event.preventDefault();
                                                event.stopPropagation();
                                            }
                                            form.classList.add('was-validated');
                                        }, false);
                                    });
                                }, false);
                            })();
                        </script>


                    </div>


                </section>
            </div>
        </div>


        @include('includes.footer')