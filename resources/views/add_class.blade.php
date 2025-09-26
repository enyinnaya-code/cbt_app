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
                            <form action="{{ route('schoolClass.store') }}" method="POST" class="needs-validation" novalidate>
                                @csrf
                                <div class="card-header">
                                    <h4>Add Class</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group col-md-6 px-0">
                                        <label>Class Name</label>
                                        <input type="text" name="name" class="form-control" required placeholder="e.g. JSS1A">

                                        <div class="valid-feedback">
                                            Looks good!
                                        </div>
                                    </div>


                                    <div class="form-group col-md-6 px-0">
                                        <label>Section</label>
                                        <select name="section_id" class="form-control">
                                            <option value="" required>Select a Section</option>
                                            @foreach($sections as $section)
                                            <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                            @endforeach
                                        </select>
                                    </div>



                                </div>
                                <div class="card-footer text-left mt-5 pt-5">
                                    <button class="btn btn-primary" type="submit">Submit</button>
                                </div>
                            </form>
                        </div>

                        <!-- Validation Script -->
                        <script>
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

        @include('includes.footer' )