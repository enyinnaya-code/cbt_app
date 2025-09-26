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
                            <form action="{{ route('teacher.store') }}" method="POST" class="needs-validation" novalidate>
                                @csrf

                                <div class="card-header">
                                    <h4>Add Teacher</h4>
                                </div>
                                <div class="card-body">
                                    <div class="form-group col-md-6 px-0">
                                        <label>Full Name</label>
                                        <input type="text" name="name" class="form-control" required placeholder="e.g. JOHN DOE"
                                            oninput="this.value = this.value.toUpperCase()">

                                        <div class="valid-feedback">
                                            Looks good!
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 px-0">
                                        <label>Email Address</label>
                                        <input type="email" name="email" class="form-control" required placeholder="e.g. user@example.com">
                                        <div class="invalid-feedback">
                                            Please enter a valid email.
                                        </div>
                                    </div>

                                    <div class="form-group col-md-6 px-0">
                                        <label>User Type</label>
                                        <select class="form-control" name="user_type" required>
                                            <option value="3">Teacher</option>
                                        </select>
                                    </div>


                                    <div class="form-group col-md-6 px-0">
                                        <label>Password <small>Default: 123456</small> </label>
                                        <input type="password" name="password" class="form-control" required placeholder="Enter password" value="123456" readonly>
                                        <div class="invalid-feedback">
                                            Please provide a password.
                                        </div>
                                    </div>

                                    {{-- Section Checkboxes --}}
                                    <div class="form-group col-md-12 px-0">
                                        <label>Select Sections (Arms)</label><br>
                                        @foreach ($sections as $section)
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input section-checkbox" type="checkbox" name="section_ids[]" value="{{ $section->id }}" id="section_{{ $section->id }}">
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
                                        <div id="subjectCheckboxes"></div>
                                    </div>




                                </div>

                                <div class="card-footer text-left pt-5 mt-3">
                                    <button class="btn btn-primary" type="submit">Submit</button>
                                </div>
                            </form>
                        </div>


                        <!-- <script>
                            const sectionCheckboxes = document.querySelectorAll('.section-checkbox');
                            const classContainer = document.getElementById('classCheckboxes');
                            let classMap = {}; // Cache classes by section_id

                            sectionCheckboxes.forEach(checkbox => {
                                checkbox.addEventListener('change', function() {
                                    const sectionId = this.value;

                                    if (this.checked) {
                                        // Fetch classes if not already fetched
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
                                    } else {
                                        removeClassCheckboxes(sectionId);
                                    }
                                });
                            });

                            function addClassCheckboxes(sectionId, classes) {
                                const wrapper = document.createElement('div');
                                wrapper.classList.add('class-group');
                                wrapper.dataset.sectionId = sectionId;

                                classes.forEach(cls => {
                                    const div = document.createElement('div');
                                    div.classList.add('form-check', 'form-check-inline');

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.classList.add('form-check-input');
                                    checkbox.name = 'class_ids[]';
                                    checkbox.value = cls.id;
                                    checkbox.id = `class_${cls.id}`;

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

                            function removeClassCheckboxes(sectionId) {
                                const group = classContainer.querySelector(`[data-section-id="${sectionId}"]`);
                                if (group) group.remove();
                            }
                        </script> -->

                        <script>
                            const sectionCheckboxes = document.querySelectorAll('.section-checkbox');
                            const classContainer = document.getElementById('classCheckboxes');
                            const subjectContainer = document.getElementById('subjectCheckboxes');
                            let classMap = {};
                            let subjectMap = {};

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

                                        // Fetch subjects (courses)
                                        if (!subjectMap[sectionId]) {
                                            fetch(`/get-subjects-by-section/${sectionId}`)
                                                .then(res => res.json())
                                                .then(data => {
                                                    subjectMap[sectionId] = data;
                                                    addSubjectCheckboxes(sectionId, data);
                                                })
                                                .catch(err => console.error(err));
                                        } else {
                                            addSubjectCheckboxes(sectionId, subjectMap[sectionId]);
                                        }

                                    } else {
                                        removeClassCheckboxes(sectionId);
                                        removeSubjectCheckboxes(sectionId);
                                    }
                                });
                            });

                            function addClassCheckboxes(sectionId, classes) {
                                const wrapper = document.createElement('div');
                                wrapper.classList.add('class-group');
                                wrapper.dataset.sectionId = sectionId;

                                classes.forEach(cls => {
                                    const div = document.createElement('div');
                                    div.classList.add('form-check', 'form-check-inline');

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.classList.add('form-check-input');
                                    checkbox.name = 'class_ids[]';
                                    checkbox.value = cls.id;
                                    checkbox.id = `class_${cls.id}`;

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

                            function removeClassCheckboxes(sectionId) {
                                const group = classContainer.querySelector(`[data-section-id="${sectionId}"]`);
                                if (group) group.remove();
                            }

                            function addSubjectCheckboxes(sectionId, subjects) {
                                const wrapper = document.createElement('div');
                                wrapper.classList.add('subject-group');
                                wrapper.dataset.sectionId = sectionId;

                                subjects.forEach(subject => {
                                    const div = document.createElement('div');
                                    div.classList.add('form-check', 'form-check-inline');

                                    const checkbox = document.createElement('input');
                                    checkbox.type = 'checkbox';
                                    checkbox.classList.add('form-check-input');
                                    checkbox.name = 'course_ids[]';
                                    checkbox.value = subject.id;
                                    checkbox.id = `subject_${subject.id}`;

                                    const label = document.createElement('label');
                                    label.classList.add('form-check-label');
                                    label.setAttribute('for', `subject_${subject.id}`);
                                    label.innerText = subject.course_name;

                                    div.appendChild(checkbox);
                                    div.appendChild(label);
                                    wrapper.appendChild(div);
                                });

                                subjectContainer.appendChild(wrapper);
                            }

                            function removeSubjectCheckboxes(sectionId) {
                                const group = subjectContainer.querySelector(`[data-section-id="${sectionId}"]`);
                                if (group) group.remove();
                            }
                        </script>

                    </div>
                </section>
            </div>
        </div>
        @include('includes.footer' )