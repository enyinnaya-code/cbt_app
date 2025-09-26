@include('includes.head')

<body>
    <!-- <div class="loader"></div> -->
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
                            <form action="{{ route('users.update', encrypt($user->id)) }}" method="POST" class="needs-validation" novalidate>
                                @csrf
                                @method('PUT')

                                <div class="card-header">
                                    <h4>Edit User</h4>
                                </div>
                                <div class="card-body">
                                    <!-- Full Name -->
                                    <div class="form-group col-md-6 px-0">
                                        <label>Full Name</label>
                                        <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required placeholder="e.g. John Doe">
                                        <div class="valid-feedback">
                                            Looks good!
                                        </div>
                                    </div>

                                    <!-- Email Address -->
                                    <div class="form-group col-md-6 px-0">
                                        <label>Email Address</label>
                                        <input type="email" name="email" class="form-control" value="{{ old('email', $user->email) }}" required placeholder="e.g. user@example.com">
                                        <div class="invalid-feedback">
                                            Please enter a valid email.
                                        </div>
                                    </div>

                                    <!-- User Type -->
                                    <div class="form-group col-md-6 px-0">
                                        <label>User Type</label>
                                        <select class="form-control" name="user_type" required>
                                            <option value="">Select User Type</option>
                                            @if(Auth::user()->user_type == 1)
                                                <option value="1" {{ old('user_type', $user->user_type) == 1 ? 'selected' : '' }}>superAdmin</option>
                                            @endif
                                            <option value="2" {{ old('user_type', $user->user_type) == 2 ? 'selected' : '' }}>Admin</option>
                                            <option value="3" {{ old('user_type', $user->user_type) == 3 ? 'selected' : '' }}>Teacher</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="card-footer text-left pt-5 mt-3">
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
        @include('includes.edit_footer')
