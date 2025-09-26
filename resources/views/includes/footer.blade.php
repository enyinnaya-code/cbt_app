<footer class="main-footer">
  <div class="footer-right">
    <a href="templateshub.net">All Rights Reserved</a></a>
  </div>
  <div class="footer-right">
  </div>
</footer>
</div>
</div>

<script>
//   $(document).ready(function() {
//   $('.summernote').summernote({
//     height: 200
//   });
// });


$(document).ready(function() {
  $('.summernote').summernote({
    height: 200,
    callbacks: {
      onChange: function(contents, $editable) {
        MathJax.typesetPromise();
      }
    }
  });
});


</script>

<script>
  // Add this to your JavaScript file
$(document).ready(function() {
    // Mark all as read
    $('#markAllRead').on('click', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: "{{ route('announcements.markAllRead') }}",
            type: 'GET',
            success: function(response) {
                // Remove unread indicators
                $('.notification-badge').remove();
                // You might want to add visual indication that items were read
                // For example, change background color of unread items
                
                // Show a notification if you want
                toastr.success('All notifications marked as read');
            }
        });
    });
    
    // Mark individual announcement as read when clicked
    $('.dropdown-list-content a').on('click', function() {
        let announcementId = $(this).data('announcement-id');
        
        // Only make the request if we have an ID
        if (announcementId) {
            $.ajax({
                url: `/announcements/${announcementId}/mark-read`,
                type: 'POST',
                headers: {
                    'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
                },
                success: function(response) {
                    // Update unread count if needed
                }
            });
        }
    });
});
</script>




<!--  
  <script src="js/app.min.js"></script>

  <script src="bundles/apexcharts/apexcharts.min.js"></script>
  
  <script src="js/page/index.js"></script>

  <script src="js/scripts.js"></script>
  
  <script src="js/custom.js"></script> -->


<script src="js/app.min.js"></script>
<!-- JS Libraies -->
<!-- Page Specific JS File -->
<script src="{{ asset('bundles/datatables/datatables.min.js') }}"></script>
<script src="{{ asset('bundles/datatables/DataTables-1.10.16/js/dataTables.bootstrap4.min.js') }}"></script>
<script src="{{ asset('bundles/datatables/export-tables/dataTables.buttons.min.js') }}"></script>
<script src="{{ asset('bundles/datatables/export-tables/buttons.flash.min.js') }}"></script>
<script src="{{ asset('bundles/datatables/export-tables/jszip.min.js') }}"></script>
<script src="{{ asset('bundles/datatables/export-tables/pdfmake.min.js') }}"></script>
<script src="{{ asset('bundles/datatables/export-tables/vfs_fonts.js') }}"></script>
<script src="{{ asset('bundles/datatables/export-tables/buttons.print.min.js') }}"></script>
<script src="{{ asset('js/page/datatables.js') }}"></script>

<!-- Template JS File -->
<script src="{{ asset('js/scripts.js') }}"></script>

<!-- Custom JS File -->
<script src="{{ asset('js/custom.js') }}"></script>


<script>
  document.addEventListener("DOMContentLoaded", function() {
    const currentPage = window.location.pathname.split("/").pop(); // e.g., "dashboard.php"
    const navLinks = document.querySelectorAll(".sidebar-menu a.nav-link");

    navLinks.forEach(link => {
      const href = link.getAttribute("href");
      if (href === currentPage) {
        // Add 'active' class to the link
        link.classList.add("active");

        // Also add 'active' class to the parent <li class="dropdown">
        const dropdown = link.closest(".dropdown");
        if (dropdown) {
          dropdown.classList.add("active");
        }
      }
    });
  });
</script>

<script>
  document.addEventListener("DOMContentLoaded", function() {
    const sidebar = document.querySelector('.main-sidebar');

    function lockSidebar() {
      if (!sidebar) return;

      sidebar.style.position = 'fixed';
      sidebar.style.top = '0';
      sidebar.style.bottom = '0';
      sidebar.style.height = '100vh';
      sidebar.style.overflowY = 'auto';
      sidebar.style.zIndex = '999';
      sidebar.style.background = '#fff'; // optional: avoid transparency in mini
    }

    lockSidebar();

    // Observe class changes to re-apply lock if needed
    const observer = new MutationObserver(() => {
      lockSidebar();
    });

    observer.observe(document.body, {
      attributes: true,
      attributeFilter: ['class']
    });

    window.addEventListener('resize', lockSidebar);
  });
</script>

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



<!-- <script>
  $(document).ready(function() {
    $('#tableExport').DataTable({
      paging: false, // Disable DataTables pagination
      info: false, // Hide entry info
      searching: true, // Optional: enable search
      ordering: true, // Enable column sort
      destroy: true, // Prevent "Cannot reinitialize" error
      dom: 'Bfrtip', // Add export buttons
      buttons: [
        'csv', 'excel', 'pdf', 'print'
      ]
    });
  });
</script> -->



<script src="https://polyfill.io/v3/polyfill.min.js?features=es6"></script>
<script id="MathJax-script" async
  src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
</script>
</body>


<!-- index.html  21 Nov 2019 03:47:04 GMT -->

</html>