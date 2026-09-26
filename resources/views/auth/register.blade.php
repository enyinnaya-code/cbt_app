<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta content="width=device-width, initial-scale=1" name="viewport">
    <title>TestaCBT | Create account</title>
    <link rel="stylesheet" href="{{ asset('css/app.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/style.css') }}">
    <link rel="stylesheet" href="{{ asset('css/components.css') }}">
    <link rel="stylesheet" href="{{ asset('css/custom.css') }}">
    <link rel="stylesheet" href="{{ asset('css/testacbt-tokens.css') }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/testa_logo_lg.png') }}" />
</head>

<body>
    <div id="app">
        <section class="section">
            <div class="container mt-5">
                <div class="row">
                    <div class="col-12 col-sm-8 offset-sm-2 col-md-6 offset-md-3 col-xl-4 offset-xl-4 mt-3">
                        <div class="card card-primary">
                            <div class="card-body">
                                <form method="POST" action="{{ route('register.submit') }}">
                                    @csrf
                                    <div class="text-center mb-4">
                                        <img src="{{ asset('images/testa_logo_lg.png') }}" alt="TestaCBT" style="width: 80px; height:60px;">
                                        <h5 class="mt-2">Create your account</h5>
                                    </div>

                                    <div class="form-group">
                                        <label for="name">Full name</label>
                                        <input id="name" type="text" class="form-control @error('name') is-invalid @enderror" name="name" value="{{ old('name') }}" required autofocus>
                                        @error('name')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="email">Email address</label>
                                        <input id="email" type="email" class="form-control @error('email') is-invalid @enderror" name="email" value="{{ old('email') }}" required>
                                        @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="password">Password</label>
                                        <input id="password" type="password" class="form-control @error('password') is-invalid @enderror" name="password" required>
                                        @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                    </div>

                                    <div class="form-group">
                                        <label for="password_confirmation">Confirm password</label>
                                        <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required>
                                    </div>

                                    <div class="form-group">
                                        <label>Which exams are you writing?</label>
                                        @foreach($exams as $exam)
                                        <div class="custom-control custom-checkbox">
                                            <input type="checkbox" class="custom-control-input" id="exam-{{ $exam->slug }}" name="preferred_exams[]" value="{{ $exam->slug }}" @checked(in_array($exam->slug, old('preferred_exams', [])))>
                                            <label class="custom-control-label" for="exam-{{ $exam->slug }}">{{ $exam->name }}</label>
                                        </div>
                                        @endforeach
                                    </div>

                                    <button type="submit" class="btn btn-primary btn-lg btn-block">Create account</button>
                                    <p class="text-center mt-3 mb-0">Already have an account? <a href="{{ route('login') }}">Sign in</a></p>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</body>

</html>
