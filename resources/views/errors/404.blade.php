<!DOCTYPE html>
<html lang="en">


<head>
  <meta charset="UTF-8">
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, shrink-to-fit=no" name="viewport">
  <title>TestaCBT</title>

  <!-- General CSS Files -->
  <link rel="stylesheet" href="{{ asset('css/app.min.css') }}">

  <!-- Template CSS -->
  <link rel="stylesheet" href="{{ asset('css/style.css') }}">
  <link rel="stylesheet" href="{{ asset('css/components.css') }}">

  <!-- Custom style CSS -->
  <link rel="stylesheet" href="{{ asset('css/custom.css') }}">

  <!-- Favicon -->
  <link rel="shortcut icon" type="image/x-icon" href="{{ asset('images/testa_logo_lg.png') }}" />

  <!-- Extra Styles -->
  <link rel="stylesheet" href="{{ asset('style.css') }}">

  <!-- FullCalendar CSS -->
  <link rel="stylesheet" href="{{ asset('bundles/fullcalendar/fullcalendar.min.css') }}">

  <!-- Toastr CSS -->
  <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/css/toastr.min.css" rel="stylesheet">
</head>


<body>
  <div class="loader"></div>
  <div id="app">
    <section class="section">
      <div class="container mt-5">
        <div class="page-error">
          <div class="page-inner">
            <h1>404</h1>
            <div class="page-description">
              The page you were looking for could not be found.
            </div>
            <div class="page-search">
              <form>
                <div class="form-group floating-addon floating-addon-not-append">
                  
                </div>
              </form>
              <div class="mt-3">
                <a href="{{ route('dashboard') }}" class="text-dark" style="text-decoration: underline;">Back to Dashboard</a>

              </div>
            </div>
          </div>
        </div>
      </div>
    </section>
  </div>

  <script src="{{ asset('js/app.min.js') }}"></script>
  <!-- JS Libraries -->
  <!-- Page Specific JS File -->
  <!-- Template JS File -->
  <script src="{{ asset('js/scripts.js') }}"></script>
  <!-- Custom JS File -->
  <script src="{{ asset('js/custom.js') }}"></script>
</body>


<!-- errors-404.html  21 Nov 2019 04:05:02 GMT -->

</html>