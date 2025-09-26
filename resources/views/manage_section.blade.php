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
                <h4>Manage Sections</h4>
              </div>
              <div class="card-body">
                <div class="table-responsive">
                  <table class="table table-striped table-hover" id="tableExport" style="width:100%;">
                    <thead>
                      <tr>
                        <th>S/N</th>
                        <th>Section Name</th>
                        <th>Created On</th>
                        <th>Created By</th>
                        <th>Actions</th>
                      </tr>
                    </thead>
                    <tbody>
                      <!-- Table with Modal Delete Buttons -->
                      @foreach($sections as $index => $section)
                      <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ $section->section_name }}</td>
                        <td>{{ $section->created_at->format('d M Y') }}</td>
                        <td>{{ $section->createdBy->name }}</td>
                        <td>

                          <a href="{{ route('edit_section', $section->id) }}" class="btn btn-primary btn-sm">Edit</a>

                          <form action="{{ route('delete_section', $section->id) }}" method="POST" style="display: inline;">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                          </form>
                        </td>

                      </tr>
                      @endforeach
                    </tbody>
                  </table>
                </div>
              </div>
            </div>
          </div>
        </section>
      </div>
    </div>
  </div>

  <!-- Delete Confirmation Modal -->
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
          Are you sure you want to delete this section?
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
          <!-- Form to submit DELETE request -->
          <form id="deleteForm" method="POST">
            @csrf
            @method('DELETE')
            <button type="submit" class="btn btn-danger">Delete</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Script -->
  <script>
    $(document).ready(function() {
      $('#deleteModal').on('show.bs.modal', function(event) {
        const button = $(event.relatedTarget);
        const url = button.data('url');
        const id = button.data('id');

        // Set the form action URL
        $('#deleteForm').attr('action', url);
      });
    });
  </script>

  @include('includes.footer')
</body>