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
                                <h4>Manage Students</h4>

                                <div class="card-header-action">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse" data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter students
                                    </button>
                                </div>
                            </div>

                            <!-- Filter Collapse Panel -->
                            <div class="collapse" id="filterCollapse">
                                <div class="card-body row px-5 pb-0">
                                    <form action="{{ route('students.index') }}" method="GET" class="row mb-4">
                                        <div class="form-group col-md-3">
                                            <label>Student Name</label>
                                            <input type="text" class="form-control" name="filter_name" value="{{ request('filter_name') }}">
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>Class</label>
                                            <select class="form-control" name="filter_class">
                                                <option value="">-- Select Class --</option>
                                                @foreach($classes as $class)
                                                <option value="{{ $class->id }}" {{ request('filter_class') == $class->id ? 'selected' : '' }}>{{ $class->name }}</option>
                                                @endforeach
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>Gender</label>
                                            <select class="form-control" name="filter_gender">
                                                <option value="">-- Select Gender --</option>
                                                <option value="Male" {{ request('filter_gender') == 'Male' ? 'selected' : '' }}>Male</option>
                                                <option value="Female" {{ request('filter_gender') == 'Female' ? 'selected' : '' }}>Female</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-3">
                                            <label>Date Added</label>
                                            <input type="date" class="form-control" name="filter_date_added" value="{{ request('filter_date_added') }}">
                                        </div>
                                        <div class="form-group col-md-12 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                <i class="fas fa-search"></i> Apply Filters
                                            </button>
                                            <a href="{{ route('students.index') }}" class="btn btn-light">
                                                <i class="fas fa-sync"></i> Reset
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            <div class="card-body">
                                <!-- Table to display students -->
                                @if(request('filter_name') || request('filter_class') || request('filter_gender') || request('filter_date_added'))
                                <div class="mb-3">
                                    <h6>Active Filters:</h6>
                                    <div class="active-filters">
                                        @if(request('filter_name'))
                                        <span class="badge badge-info mr-2">Student Name: {{ request('filter_name') }}</span>
                                        @endif

                                        @if(request('filter_class'))
                                        @php
                                        $selectedClass = $classes->firstWhere('id', request('filter_class'));
                                        @endphp
                                        <span class="badge badge-info mr-2">
                                            Class: {{ $selectedClass ? $selectedClass->name : 'N/A' }}
                                        </span>
                                        @endif

                                        @if(request('filter_gender'))
                                        <span class="badge badge-info mr-2">Gender: {{ request('filter_gender') }}</span>
                                        @endif

                                        @if(request('filter_date_added'))
                                        <span class="badge badge-info mr-2">Date Added: {{ request('filter_date_added') }}</span>
                                        @endif

                                        <a href="{{ route('students.index') }}" class="btn btn-sm m-1 btn-outline-danger">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    </div>
                                </div>

                                @endif

                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Name</th>
                                                <th>Email</th>

                                                <th>Gender</th>
                                                <th>Class</th>
                                                <th>Status</th>

                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($students as $index => $student)
                                            <tr>
                                                <td>{{ $students->firstItem() + $index }}</td>
                                                <td>{{ $student->name }}</td>
                                                <td>{{ $student->email }}</td>
                                                <td>{{ ucfirst($student->gender ?? '-') }}</td>
                                                <td>{{ $student->class->name ?? 'N/A' }}</td>
                                                <td>
                                                    @if($student->is_active)
                                                    <span class="badge badge-success">Active</span>
                                                    @else
                                                    <span class="badge badge-danger">Deactivated</span>
                                                    @endif
                                                </td>

                                                <td>
                                                    <a href="{{ route('students.edit', $student->id) }}" class="btn btn-sm btn-info" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    @if($student->is_active)
                                                    <button type="button" class="btn btn-sm btn-warning" title="Suspend"
                                                        data-toggle="modal" data-target="#suspendModal{{ $student->id }}">
                                                        <i class="fas fa-user-slash"></i>
                                                    </button>
                                                    @else
                                                    <button type="button" class="btn btn-sm btn-success" title="Activate"
                                                        data-toggle="modal" data-target="#activateModal{{ $student->id }}">
                                                        <i class="fas fa-user-check"></i>
                                                    </button>
                                                    @endif

                                                    <button type="button" class="btn btn-sm btn-secondary" title="Reset Password"
                                                        data-toggle="modal" data-target="#resetPasswordModal{{ $student->id }}">
                                                        <i class="fas fa-key"></i>
                                                    </button>

                                                    <button type="button" class="btn btn-sm btn-danger" title="Delete"
                                                        data-toggle="modal" data-target="#deleteModal{{ $student->id }}">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>


                                    </table>
                                    <div class="mt-3">
                                        {{ $students->links() }}
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Add these modals at the end of your page, before the @include('includes.footer') -->

    <!-- Suspend Student Confirmation Modal -->
    @foreach($students as $student)
    <div class="modal fade" id="suspendModal{{ $student->id }}" tabindex="-1" role="dialog" aria-labelledby="suspendModalLabel{{ $student->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="suspendModalLabel{{ $student->id }}">Suspend Student</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to suspend <strong>{{ $student->name }}</strong>?
                    <p class="text-warning mt-2">This will prevent the student from accessing the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form action="{{ route('students.suspend', $student->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-warning">Yes, Suspend Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Activate Student Confirmation Modal -->
    <div class="modal fade" id="activateModal{{ $student->id }}" tabindex="-1" role="dialog" aria-labelledby="activateModalLabel{{ $student->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="activateModalLabel{{ $student->id }}">Activate Student</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to activate <strong>{{ $student->name }}</strong>?
                    <p class="text-success mt-2">This will restore the student's access to the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form action="{{ route('students.activate', $student->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-success">Yes, Activate Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Reset Password Confirmation Modal -->
    <div class="modal fade" id="resetPasswordModal{{ $student->id }}" tabindex="-1" role="dialog" aria-labelledby="resetPasswordModalLabel{{ $student->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="resetPasswordModalLabel{{ $student->id }}">Reset Password</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to reset the password for <strong>{{ $student->name }}</strong>?
                    <p class="text-info mt-2">A new random password will be generated and sent to the student's email address.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form action="{{ route('students.reset_password', $student->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-info">Yes, Reset Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Student Confirmation Modal -->
    <div class="modal fade" id="deleteModal{{ $student->id }}" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel{{ $student->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel{{ $student->id }}">Delete Student</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!
                    </div>
                    <p>Are you sure you want to permanently delete <strong>{{ $student->name }}</strong>?</p>
                    <p>All associated student data will be removed from the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form action="{{ route('students.destroy', $student->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Yes, Delete Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach


    <!-- Replace the existing delete form with a button that triggers the modal -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Add click handlers for delete buttons
            const deleteButtons = document.querySelectorAll('.delete-student-btn');
            deleteButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    const studentId = this.getAttribute('data-student-id');
                    $('#deleteModal' + studentId).modal('show');
                });
            });
        });
    </script>

    <!-- Delete Student Confirmation Modal -->
    @foreach($students as $student)
    <div class="modal fade" id="deleteModal{{ $student->id }}" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel{{ $student->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel{{ $student->id }}">Delete Student</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Warning: This action cannot be undone!
                    </div>
                    <p>Are you sure you want to permanently delete <strong>{{ $student->name }}</strong>?</p>
                    <p>All associated student data will be removed from the system.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form action="{{ route('students.destroy', $student->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Yes, Delete Student</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    @endforeach

    @include('includes.edit_footer')