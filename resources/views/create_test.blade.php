@include('includes.head')

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('includes.right_top_nav')
            @include('includes.side_nav')

            <!-- Main Content -->
            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <form class="needs-validation" novalidate method="POST" action="{{ route('tests.store') }}">
                                @csrf
                                <div class="card-header">
                                    <h4>Create New Test</h4>
                                </div>
                                <div class="card-body">

                                    <!-- Test Name -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Name</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="text" placeholder="Test name" name="test_name" class="form-control" oninput="this.value = this.value.toUpperCase()" required>
                                        </div>
                                    </div>

                                    <!-- Test Type -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Type</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="test_type" class="form-control" required>
                                                <!-- <option value="">Select Test Type</option> -->
                                                <option value="multiple_choice">Multiple Choice</option>
                                                <!-- <option value="text_input">Text Input (Short Answer)</option> -->
                                                <!-- <option value="combined">Combined (Multiple Choice + Text)</option> -->
                                            </select>
                                        </div>
                                    </div>


                                    <!-- Duration -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Duration (minutes)</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number" placeholder="e.g 30" name="duration" class="form-control" required>
                                        </div>
                                    </div>


                                    <!-- Passmark -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Passmark</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number" placeholder="e.g 50" name="pass_mark" class="form-control" min="1" required>
                                        </div>
                                    </div>

                                    <!-- Section -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Section</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="section_id" id="section_id" class="form-control" required>
                                                <option value="">Select Section</option>
                                                @foreach($sections as $section)
                                                <option value="{{ $section->id }}">{{ $section->section_name }}</option>
                                                @endforeach

                                            </select>

                                        </div>
                                    </div>


                                    <!-- Classes (Dynamic) -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Class</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="class_id" id="class_id" class="form-control" required>
                                                <option value="">Select a Class</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Course -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Course</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="course_id" class="form-control" required>
                                                <option value="">Select Course</option>
                                                @foreach($courses as $course)
                                                <option value="{{ $course->id }}">{{ $course->course_name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>




                                </div>
                                <div class="card-footer text-left">
                                    <button type="submit" class="btn btn-primary">Create Test</button>
                                </div>
                            </form>

                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>


    <script>
        document.getElementById('section_id').addEventListener('change', function() {
            let sectionId = this.value;
            let classSelect = document.getElementById('class_id');
            classSelect.innerHTML = '<option value="">Loading...</option>';

            if (sectionId) {
                fetch(`/sections/${sectionId}/classes`)
                    .then(response => response.json())
                    .then(data => {
                        let options = '<option value="">Select a Class</option>';
                        data.forEach(cls => {
                            options += `<option value="${cls.id}">${cls.name}</option>`;
                        });
                        classSelect.innerHTML = options;
                    })
                    .catch(() => {
                        classSelect.innerHTML = '<option value="">Error loading classes</option>';
                    });
            } else {
                classSelect.innerHTML = '<option value="">Select a Section First</option>';
            }
        });
    </script>


    @include('includes.edit_footer')