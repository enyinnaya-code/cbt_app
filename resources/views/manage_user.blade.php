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
                                <h4>Manage Admin</h4>
                                <div class="card-header-action">
                                    <button class="btn btn-primary" type="button" data-toggle="collapse"
                                        data-target="#filterCollapse">
                                        <i class="fas fa-filter"></i> Filter Users
                                    </button>
                                </div>
                            </div>

                            <!-- Filter Collapse Panel -->
                            <div class="collapse" id="filterCollapse">
                                <div class="card-body pb-0">
                                    <form action="{{ route('users.index') }}" method="GET" class="row">
                                        <div class="form-group col-md-4">
                                            <label>Name</label>
                                            <input type="text" class="form-control" name="filter_name"
                                                value="{{ request('filter_name') }}">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Email</label>
                                            <input type="text" class="form-control" name="filter_email"
                                                value="{{ request('filter_email') }}">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>User Type</label>
                                            <select class="form-control" name="filter_user_type">
                                               
                                                <option value="2" {{ request('filter_user_type') == '2' ? 'selected' : '' }}>Admin</option>
                                               
                                            </select>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Status</label>
                                            <select class="form-control" name="filter_status">
                                                <option value="">All Statuses</option>
                                                <option value="1" {{ request('filter_status') == '1' ? 'selected' : '' }}>Active</option>
                                                <option value="0" {{ request('filter_status') == '0' ? 'selected' : '' }}>Deactivated</option>
                                            </select>
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Date From</label>
                                            <input type="date" class="form-control" name="filter_date_from"
                                                value="{{ request('filter_date_from') }}">
                                        </div>
                                        <div class="form-group col-md-4">
                                            <label>Date To</label>
                                            <input type="date" class="form-control" name="filter_date_to"
                                                value="{{ request('filter_date_to') }}">
                                        </div>
                                        <div class="form-group col-md-12 d-flex align-items-end">
                                            <button type="submit" class="btn btn-primary mr-2">
                                                <i class="fas fa-search"></i> Apply Filters
                                            </button>
                                            <a href="{{ route('users.index') }}" class="btn btn-light">
                                                <i class="fas fa-sync"></i> Reset
                                            </a>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card-body">
                                <!-- Display Active Filters -->
                                @if(request('filter_name') || request('filter_email') || request('filter_user_type') !== null ||
                                request('filter_status') !== null || request('filter_date_from') || request('filter_date_to'))
                                <div class="mb-3">
                                    <h6>Active Filters:</h6>
                                    <div class="active-filters">
                                        @if(request('filter_name'))
                                        <span class="badge badge-info mr-2">Name: {{ request('filter_name') }}</span>
                                        @endif
                                        @if(request('filter_email'))
                                        <span class="badge badge-info mr-2">Email: {{ request('filter_email') }}</span>
                                        @endif
                                        @if(request('filter_user_type') !== null && request('filter_user_type') !== '')
                                        @php
                                        $roles = ['0' => 'User', '1' => 'SuperAdmin', '2' => 'Admin', '3' => 'Teacher'];
                                        $user_type_name = $roles[request('filter_user_type')] ?? 'Unknown';
                                        @endphp
                                        <span class="badge badge-info mr-2">User Type: {{ $user_type_name }}</span>
                                        @endif
                                        @if(request('filter_status') !== null && request('filter_status') !== '')
                                        <span class="badge badge-info mr-2">Status: {{ request('filter_status') == '1' ? 'Active' : 'Deactivated' }}</span>
                                        @endif
                                        @if(request('filter_date_from'))
                                        <span class="badge badge-info mr-2">From: {{ request('filter_date_from') }}</span>
                                        @endif
                                        @if(request('filter_date_to'))
                                        <span class="badge badge-info mr-2">To: {{ request('filter_date_to') }}</span>
                                        @endif
                                        <a href="{{ route('users.index') }}" class="btn btn-sm  btn-outline-danger">
                                            <i class="fas fa-times"></i> Clear All
                                        </a>
                                    </div>
                                </div>
                                @endif



                                <div class="table-responsive">
                                    <table class="table table-striped table-hover" id="users-table" style="width:100%;">
                                        <thead>
                                            <tr>
                                                <th>S/N</th>
                                                <th>Name</th>
                                                <th>Email</th>
                                                <th>User Type</th>
                                                <th>Date Added</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach($users as $index => $user)
                                            <tr>
                                                <td>{{ ($users->currentPage() - 1) * $users->perPage() + $index + 1 }}</td>
                                                <td>{{ $user->name }}</td>
                                                <td>{{ $user->email }}</td>
                                                <td>
                                                    @php
                                                    $roles = ['0' => 'User', '1' => 'SuperAdmin', '2' => 'Admin', '3' => 'Teacher'];
                                                    @endphp
                                                    {{ $roles[$user->user_type] ?? 'Unknown' }}
                                                </td>
                                                <td>{{ $user->created_at->format('M d, Y') }}</td>
                                                <td>
                                                    @if($user->is_active)
                                                    <span class="badge badge-success">Active</span>
                                                    @else
                                                    <span class="badge badge-danger">Deactivated</span>
                                                    @endif
                                                </td>
                                                <td>
                                                    <!-- Action buttons with modals -->
                                                    <a href="{{ route('users.edit', ['id' => Crypt::encrypt($user->id)]) }}" class="btn btn-sm m-1 btn-warning" title="Edit">
                                                        <i class="fas fa-edit"></i>
                                                    </a>

                                                    <form action="{{ route('users.toggleActive', ['id' => Crypt::encrypt($user->id)]) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        @method('PATCH')
                                                        <button type="submit" class="btn btn-sm m-1 {{ $user->is_active ? 'btn-secondary' : 'btn-success' }}"
                                                            title="{{ $user->is_active ? 'Suspend' : 'Activate' }}">
                                                            <i class="fas {{ $user->is_active ? 'fa-ban' : 'fa-check' }}"></i>
                                                        </button>
                                                    </form>

                                                    <a href="{{ route('users.reset', Crypt::encrypt($user->id)) }}" class="btn btn-sm m-1 btn-info" title="Reset Password">
                                                        <i class="fas fa-key"></i>
                                                    </a>

                                                    <form action="{{ route('users.destroy', ['id' => Crypt::encrypt($user->id)]) }}" method="POST" style="display:inline;">
                                                        @csrf
                                                        @method('DELETE')
                                                        <button type="submit" class="btn btn-sm m-1 btn-danger" title="Delete">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </td>

                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>

                                <!-- Pagination Controls with filter parameters -->
                                <div class="mt-4">
                                    {{ $users->appends(request()->query())->links() }}
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Status Toggle Confirmation Modal -->
    <div class="modal fade" id="toggleModal" tabindex="-1" role="dialog" aria-labelledby="toggleModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="toggleModalLabel">Confirm Status Change</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to <span id="statusAction"></span> user: <strong id="userName"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form id="toggleForm" method="POST">
                        @csrf
                        @method('PATCH')
                        <button type="submit" class="btn btn-primary">Confirm</button>
                    </form>
                </div>
            </div>
        </div>
    </div>


    <!-- Delete User Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    Are you sure you want to delete user: <strong id="deleteUserName"></strong>?
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Scripts -->
    <script>
        $(document).ready(function() {
            // Toggle Status Modal
            $('#toggleModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const id = button.data('id');
                const isActive = button.data('status');
                const name = button.data('name');

                // Set the user name and action text in the modal
                $('#userName').text(name);
                $('#statusAction').text(isActive ? 'deactivate' : 'activate');

                // Set the form action URL to match your route name
                $('#toggleForm').attr('action', '{{ url("toggle-user") }}/' + id);
            });

            // Delete User Modal
            $('#deleteModal').on('show.bs.modal', function(event) {
                const button = $(event.relatedTarget);
                const id = button.data('id');
                const name = button.data('name');

                // Set the user name in the modal
                $('#deleteUserName').text(name);

                // Set the form action URL to match your route name
                $('#deleteForm').attr('action', '{{ url("delete-user") }}/' + id);
            });

         
        });
    </script>
    @include('includes.footer')