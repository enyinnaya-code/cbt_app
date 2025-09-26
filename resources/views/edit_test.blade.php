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
                            <form class="needs-validation" novalidate method="POST" action="{{ route('tests.update', $test->id) }}">
                                @csrf
                                @method('PUT') <!-- Indicate it's an update request -->
                                <div class="card-header">
                                    <h4>Edit Test</h4>
                                </div>
                                <div class="card-body">
                                    <!-- Test Name -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Name</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="text" placeholder="Test name" name="test_name" class="form-control"
                                                value="{{ old('test_name', $test->test_name) }}" oninput="this.value = this.value.toUpperCase()" required>
                                        </div>
                                    </div>

                                    <!-- Test Type -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Type</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="test_type" class="form-control" required>
                                                <option value="">Select Test Type</option>
                                                <option value="multiple_choice" {{ old('test_type', $test->test_type) == 'multiple_choice' ? 'selected' : '' }}>Multiple Choice (A–E)</option>
                                                <option value="text_input" {{ old('test_type', $test->test_type) == 'text_input' ? 'selected' : '' }}>Text Input (Short Answer)</option>
                                                <option value="combined" {{ old('test_type', $test->test_type) == 'combined' ? 'selected' : '' }}>Combined (Multiple Choice + Text)</option>
                                            </select>
                                        </div>
                                    </div>

                                    <!-- Duration -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Test Duration (minutes)</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number" placeholder="e.g 30" name="duration" class="form-control" value="{{ old('duration', $test->duration) }}">
                                        </div>
                                    </div>

                                    <!-- Passmark -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Passmark</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="number"
                                                placeholder="e.g 50"
                                                name="pass_mark"
                                                class="form-control"
                                                min="1"
                                                value="{{ old('pass_mark', $test->pass_mark) }}"
                                                required>
                                        </div>
                                    </div>


                                    <!-- Section -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Section</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="section_id" id="section_id" class="form-control" required>
                                                <option value="">Select Section</option>
                                                @foreach($sections as $section)
                                                <option value="{{ $section->id }}" {{ old('section_id', $test->section_id) == $section->id ? 'selected' : '' }}>
                                                    {{ $section->section_name }}
                                                </option>
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
                                                <!-- Classes will be populated dynamically via JavaScript -->
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
                                                <option value="{{ $course->id }}" {{ old('course_id', $test->course_id) == $course->id ? 'selected' : '' }}>
                                                    {{ $course->course_name }}
                                                </option>
                                                @endforeach
                                            </select>
                                        </div>
                                    </div>
                                </div>
                                <div class="card-footer text-left">
                                    <button type="submit" class="btn btn-primary">Update Test</button>
                                </div>
                            </form>


                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>


    <script>
        const sectionId = "{{ old('section_id', $test->section_id) }}";
        const selectedClassId = "{{ old('class_id', $test->class_id) }}";

        function loadClasses(sectionId, selectedClassId = null) {
            let classSelect = document.getElementById('class_id');
            classSelect.innerHTML = '<option value="">Loading...</option>';

            fetch(`/sections/${sectionId}/classes`)
                .then(response => response.json())
                .then(data => {
                    let options = '<option value="">Select a Class</option>';
                    data.forEach(cls => {
                        const selected = selectedClassId == cls.id ? 'selected' : '';
                        options += `<option value="${cls.id}" ${selected}>${cls.name}</option>`;
                    });
                    classSelect.innerHTML = options;
                })
                .catch(() => {
                    classSelect.innerHTML = '<option value="">Error loading classes</option>';
                });
        }

        // Load classes when page loads if section is preselected
        if (sectionId) {
            loadClasses(sectionId, selectedClassId);
        }

        // Re-load classes on section change
        document.getElementById('section_id').addEventListener('change', function() {
            loadClasses(this.value);
        });
    </script>


    @include('includes.edit_footer')