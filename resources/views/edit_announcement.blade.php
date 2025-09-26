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
                            <form method="POST" action="{{ route('announcements.update', $announcement->id) }}">
                                @csrf
                                @method('PUT')

                                <div class="card-header">
                                    <h4>Edit Announcement</h4>
                                </div>
                                <div class="card-body">

                                    <div class="form-group mb-4">
                                        <label for="content">Content</label>
                                        <textarea
                                            id="announcementContent"
                                            name="content"
                                            class="summernote-simple form-control"
                                            required
                                            maxlength="500">{{ old('content', $announcement->content) }}</textarea>
                                        <small id="charCount" class="form-text text-muted">0 / 500 characters used</small>
                                        @error('content') <div class="text-danger mt-1">{{ $message }}</div> @enderror
                                    </div>

                                    
                                    <button type="submit" class="btn btn-primary">Update Announcement</button>
                                    <a href="{{ route('announcements.index') }}" class="btn btn-secondary ml-2">Back</a>
                                </div>
                            </form>


                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>


    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const textarea = document.getElementById('announcementContent');
            const charCount = document.getElementById('charCount');
            const maxChars = 500;

            // Initialize Summernote if not already initialized
            if (typeof $(textarea).summernote !== 'function') {
                console.warn('Summernote not initialized properly');
            } else {
                // Ensure Summernote is initialized
                $(textarea).summernote({
                    height: 150,
                    toolbar: [
                        ['style', ['style']],
                        ['font', ['bold', 'underline', 'italic']],
                        ['para', ['ul', 'ol', 'paragraph']],
                        ['insert', ['link']]
                    ],
                    callbacks: {
                        // This callback runs when the editor content changes
                        onChange: function(contents) {
                            updateCharCount(contents);
                        }
                    }
                });
            }

            function updateCharCount(contents) {
                // Remove HTML tags to get just the text content
                const textOnly = contents.replace(/<[^>]*>/g, '');
                // Count remaining characters
                const currentLength = textOnly.length;

                // Update the counter display
                charCount.textContent = `${currentLength} / ${maxChars} characters used`;

                // Visual indicator when approaching/exceeding limit
                if (currentLength > maxChars) {
                    charCount.classList.add('text-danger');
                    charCount.classList.remove('text-muted', 'text-warning');
                } else if (currentLength > maxChars * 0.8) {
                    charCount.classList.add('text-warning');
                    charCount.classList.remove('text-muted', 'text-danger');
                } else {
                    charCount.classList.add('text-muted');
                    charCount.classList.remove('text-danger', 'text-warning');
                }
            }

            // For the form submission validation
            document.querySelector('form.needs-validation').addEventListener('submit', function(event) {
                const contents = $(textarea).summernote('code');
                const textOnly = contents.replace(/<[^>]*>/g, '');

                if (textOnly.length > maxChars) {
                    event.preventDefault();
                    event.stopPropagation();
                    alert(`Your announcement exceeds the maximum of ${maxChars} characters. Please shorten your text.`);
                }
            });

            // Initialize character count on page load
            const initialContent = $(textarea).summernote('code') || '';
            updateCharCount(initialContent);
        });
    </script>


    @include('includes.edit_footer')