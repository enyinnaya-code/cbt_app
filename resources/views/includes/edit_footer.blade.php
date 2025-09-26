<footer class="main-footer">
    <div class="footer-right">
        <a href="templateshub.net">All Rights Reserved</a></a>
    </div>
    <div class="footer-right">
    </div>
</footer>
</div>
</div>
<!-- @include('includes.footer') -->
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
<script src="{{ asset('bundles/summernote/summernote-bs4.js') }}"></script>

<script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
<script id="MathJax-script" async
  src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
</script>


</body>

</html>