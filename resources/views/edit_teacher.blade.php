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
                            {{-- Update route to include $teacher->id --}}
                            <form action="{{ route('teachers.update', Crypt::encrypt($teacher->id)) }}" method="POST" class="needs-validation" novalidate>

                                @csrf
                                @method('PUT') {{-- PUT method for update --}}

                                <div class="card-header">
                                    <h4>Edit Teacher</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group col-md-6 px-0">
                                        <label>Full Name</label>
                                        <input type="text" name="name" class="form-control" required placeholder="e.g. JOHN DOE"
                                            value="{{ old('name', $teacher->name) }}"
                                            oninput="this.value = this.value.toUpperCase()">

                                        <div class="valid-feedback">
                                            Looks good!
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 px-0">
                                        <label>Email Address</label>
                                        <input type="email" name="email" class="form-control" required placeholder="e.g. user@example.com"
                                            value="{{ old('email', $teacher->email) }}">
                                        <div class="invalid-feedback">
                                            Please enter a valid email.
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 px-0">
                                        <label>User Type</label>
                                        <select class="form-control" name="user_type" required disabled>
                                            <option value="3" selected>Teacher</option>
                                        </select>
                                        {{-- Disabled so user_type is fixed as Teacher --}}
                                    </div>


                                    {{-- Section Checkboxes --}}
                                    <div class="form-group col-md-12 px-0">
                                        <label>Select Sections (Arms)</label><br>
                                        @foreach ($sections as $section)
                                        <div class="form-check form-check-inline">
                                            <input
                                                class="form-check-input section-checkbox"
                                                type="checkbox"
                                                name="section_ids[]"
                                                value="{{ $section->id }}"
                                                id="section_{{ $section->id }}"
                                                {{ in_array($section->id, old('section_ids', $assignedSectionIds ?? [])) ? 'checked' : '' }}>
                                            <label class="form-check-label" for="section_{{ $section->id }}">{{ $section->section_name }}</label>
                                        </div>
                                        @endforeach
                                    </div>

                                    {{-- Classes Checkboxes Will Be Injected Here --}}
                                    <div class="form-group col-md-12 px-0">
                                        <label>Associated Classes</label>
                                        <div id="classCheckboxes"></div>
                                    </div>

                                    <div class="form-group col-md-12 px-0">
                                        <label>Associated Courses</label>
                                        <div id="courseCheckboxes"></div>
                                    </div>


                                </div>

                                <div class="card-footer text-left pt-5 mt-3">
                                    <button class="btn btn-primary" type="submit">Update</button>
                                </div>
                            </form>
                        </div>


                        <script>
                            const sectionCheckboxes = document.querySelectorAll('.section-checkbox');
                            const classContainer = document.getElementById('classCheckboxes');
                            const courseContainer = document.getElementById('courseCheckboxes');

                            let classMap = {};
                            let courseMap = {};

                            const assignedClassIds = @json(old('class_ids', $assignedClassIds ?? []));
                            const assignedCourseIds = @json(old('course_ids', $assignedCourseIds ?? []));

                            function addClassCheckboxes(sectionId, classes) {
                                const wrapper = document.createElement('div');
                                wrapper.classList.add('class-group');
                                wrapper.dataset.sectionId = sectionId;

                                const label = document.createElement('label');
                                // label.innerText = `Classes for Section ${sectionId}:`;
                                wrapper.appendChild(label);

                                classes.forEach(cls => {
                                    const div = document.createElement('div');
                                    div.classList.add('form-check', 'form-check-inline');

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.classList.add('form-check-input');
                                    checkbox.name = 'class_ids[]';
                                    checkbox.value = cls.id;
                                    checkbox.id = `class_${cls.id}`;

                                    if (assignedClassIds.includes(cls.id)) {
                                        checkbox.checked = true;
                                    }

                                    const label = document.createElement('label');
                                    label.classList.add('form-check-label');
                                    label.setAttribute('for', `class_${cls.id}`);
                                    label.innerText = cls.name;

                                    div.appendChild(checkbox);
                                    div.appendChild(label);
                                    wrapper.appendChild(div);
                                });

                                classContainer.appendChild(wrapper);
                            }

                            function addCourseCheckboxes(sectionId, courses) {
                                const wrapper = document.createElement('div');
                                wrapper.classList.add('course-group');
                                wrapper.dataset.sectionId = sectionId;

                                const label = document.createElement('label');
                                // label.innerText = `Courses for Section ${sectionId}:`;
                                wrapper.appendChild(label);

                                courses.forEach(course => {
                                    const div = document.createElement('div');
                                    div.classList.add('form-check', 'form-check-inline');

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.classList.add('form-check-input');
                                    checkbox.name = 'course_ids[]';
                                    checkbox.value = course.id;
                                    checkbox.id = `course_${course.id}`;

                                    if (assignedCourseIds.includes(course.id)) {
                                        checkbox.checked = true;
                                    }

                                    const label = document.createElement('label');
                                    label.classList.add('form-check-label');
                                    label.setAttribute('for', `course_${course.id}`);
                                    label.innerText = course.course_name;

                                    div.appendChild(checkbox);
                                    div.appendChild(label);
                                    wrapper.appendChild(div);
                                });

                                courseContainer.appendChild(wrapper);
                            }

                            function removeClassCheckboxes(sectionId) {
                                const group = classContainer.querySelector(`[data-section-id="${sectionId}"]`);
                                if (group) group.remove();
                            }

                            function removeCourseCheckboxes(sectionId) {
                                const group = courseContainer.querySelector(`[data-section-id="${sectionId}"]`);
                                if (group) group.remove();
                            }

                            sectionCheckboxes.forEach(checkbox => {
                                checkbox.addEventListener('change', function() {
                                    const sectionId = this.value;

                                    if (this.checked) {
                                        // Fetch classes
                                        if (!classMap[sectionId]) {
                                            fetch(`/get-classes-by-sections?section_ids[]=${sectionId}`)
                                                .then(res => res.json())
                                                .then(data => {
                                                    classMap[sectionId] = data;
                                                    addClassCheckboxes(sectionId, data);
                                                })
                                                .catch(err => console.error(err));
                                        } else {
                                            addClassCheckboxes(sectionId, classMap[sectionId]);
                                        }

                                        // Fetch courses
                                        if (!courseMap[sectionId]) {
                                            fetch(`/get-subjects-by-section/${sectionId}`)
                                                .then(res => res.json())
                                                .then(data => {
                                                    courseMap[sectionId] = data;
                                                    addCourseCheckboxes(sectionId, data);
                                                })
                                                .catch(err => console.error(err));
                                        } else {
                                            addCourseCheckboxes(sectionId, courseMap[sectionId]);
                                        }

                                    } else {
                                        removeClassCheckboxes(sectionId);
                                        removeCourseCheckboxes(sectionId);
                                    }
                                });
                            });

                            window.addEventListener('DOMContentLoaded', () => {
                                sectionCheckboxes.forEach(cb => {
                                    if (cb.checked) {
                                        const sectionId = cb.value;

                                        // Fetch & render classes
                                        if (!classMap[sectionId]) {
                                            fetch(`/get-classes-by-sections?section_ids[]=${sectionId}`)
                                                .then(res => res.json())
                                                .then(data => {
                                                    classMap[sectionId] = data;
                                                    addClassCheckboxes(sectionId, data);
                                                })
                                                .catch(err => console.error(err));
                                        } else {
                                            addClassCheckboxes(sectionId, classMap[sectionId]);
                                        }

                                        // Fetch & render courses
                                        if (!courseMap[sectionId]) {
                                            fetch(`/get-subjects-by-section/${sectionId}`)
                                                .then(res => res.json())
                                                .then(data => {
                                                    courseMap[sectionId] = data;
                                                    addCourseCheckboxes(sectionId, data);
                                                })
                                                .catch(err => console.error(err));
                                        } else {
                                            addCourseCheckboxes(sectionId, courseMap[sectionId]);
                                        }
                                    }
                                });
                            });
                        </script>

                    </div>
                </section>
            </div>
        </div>
        @include('includes.edit_footer')