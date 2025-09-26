<!DOCTYPE html>
<html lang="en">


<!-- auth-login.html  21 Nov 2019 03:49:32 GMT -->

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
    <title>TestaCBT</title>
    <!-- General CSS Files -->
    <link rel="stylesheet" href="{{ asset('css/app.min.css') }}">
    <link rel="stylesheet" href="{{ asset('bundles/bootstrap-social/bootstrap-social.css') }}">
    <!-- Template CSS -->
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <!-- Custom style CSS -->
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel='shortcut icon' type='image/x-icon' href="{{ asset('images/testa_logo_lg.png') }}" />
    <!-- Add this just before closing </body> tag for Toastr scripts -->
    <!-- Toastr CSS -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">

</head>


<body>
    <div class="loader"></div>
    <div id="app">
        <section class="section">
            <div class="container mt-5">
                <div class="row">
                    <div class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-lg-6 offset-lg-3 col-xl-4 offset-xl-4 mt-5">
                        <div class="card card-primary">
                            <div class="card-body">
                                <form method="POST" action="{{ route('login.submit') }}" class="needs-validation" novalidate="">
                                    @csrf <!-- Add CSRF token for protection -->

                                    <div class="text-center mb-4">
                                        <img src="{{ asset('images/testa_logo_lg.png') }}" alt="Logo" style="width: 80px; height:60px;">
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email</label>
                                        <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" tabindex="1" autofocus>
                                        <div class="invalid-feedback">
                                            Please fill in your email
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <div class="d-block">
                                            <label for="password" class="control-label">Password</label>
                                        </div>
                                        <input id="password" type="password" class="form-control" name="password" tabindex="2">
                                        <div class="invalid-feedback">
                                            Please fill in your password
                                        </div>
                                    </div>

                                    <div class="form-group">
                                        <button type="submit" class="btn btn-primary btn-lg btn-block" tabindex="4">
                                            Login
                                        </button>
                                    </div>
                                </form>




                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
    <!-- General JS Scripts -->
    <script src="{{ asset('js/app.min.js') }}"></script>
    <!-- JS Libraries -->
    <!-- Page Specific JS File -->
    <!-- Template JS File -->
    <script src="{{ asset('js/scripts.js') }}"></script>
    <!-- Custom JS File -->
    <script src="{{ asset('js/custom.js') }}"></script>



    <!-- Add this just before closing </body> tag for Toastr scripts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/js/toastr.min.js"></script>

    <!-- Display Toastr Notifications -->
    @if(session('error'))
    <script>
        toastr.error("{{ session('error') }}");
    </script>
    @elseif(session('success'))
    <script>
        toastr.success("{{ session('success') }}");
    </script>
    @elseif(session('info'))
    <script>
        toastr.info("{{ session('info') }}");
    </script>
    @endif



</body>


<!-- auth-login.html  21 Nov 2019 03:49:32 GMT -->

</html>