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
                            <div class="card-header">
                                <h4>Add New Student</h4>
                            </div>
                            <div class="card-body">
                                <form action="{{ route('students.store') }}" method="POST">
                                    @csrf
                                    <!-- Student Name -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Student Name</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="text" name="student_name" class="form-control" required
                                                oninput="this.value = this.value.toUpperCase();">

                                        </div>
                                    </div>

                                    <!-- Email -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Email</label>
                                        <div class="col-sm-12 col-md-7">
                                            <input type="email" name="email" class="form-control" required>
                                        </div>
                                    </div>


                                    <!-- Gender -->
                                    <div class="form-group row mb-4">
                                        <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Gender</label>
                                        <div class="col-sm-12 col-md-7">
                                            <select name="gender" class="form-control" required>
                                                <option value="">Select Gender</option>
                                                <option value="Male">Male</option>
                                                <option value="Female">Female</option>

                                            </select>
                                        </div>
                                    </div>


                                  <!-- Section Dropdown -->
<div class="form-group row mb-4">
    <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Section</label>
    <div class="col-sm-12 col-md-7">
        <select name="section_id" id="section_id" class="form-control" required>
            <option value="">-- Select Section --</option>
            @foreach($sections as $section)
            <option value="{{ $section->id }}">{{ $section->section_name }}</option>
            @endforeach
        </select>
    </div>
</div>

<!-- Class Dropdown -->
<div class="form-group row mb-4">
    <label class="col-form-label text-md-right col-12 col-md-3 col-lg-3">Class</label>
    <div class="col-sm-12 col-md-7">
        <select name="class_id" id="class_id" class="form-control" required>
            <option value="">-- Select Class --</option>
        </select>
    </div>
</div>


                                    <div class="card-footer text-left mt-5 pt-5">
                                        <button type="submit" class="btn btn-primary">Add Student</button>
                                    </div>

                                </form>
                            </div>
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
    classSelect.innerHTML = '<option value="">-- Select Class --</option>'; // Reset classes

    if (sectionId) {
        fetch(`/get-classes/${sectionId}`)
            .then(res => res.json())
            .then(data => {
                data.forEach(cls => {
                    let option = document.createElement('option');
                    option.value = cls.id;
                    option.textContent = cls.name;
                    classSelect.appendChild(option);
                });
            })
            .catch(err => console.error('Error fetching classes:', err));
    }
});
</script>

    @include('includes.edit_footer')