@include('includes.head')
<style>
    /* Styles for the comment box */
    .comment-box {
        position: fixed;
        /* Changed from absolute to fixed */
        right: 20px;
        top: 50px;
        width: 400px;
        background-color: #f8f9fa;
        border: 1px solid #ddd;
        padding: 15px;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        border-radius: 5px;
        z-index: 999 !important;
        cursor: move;
        /* Added cursor style to indicate draggable */
    }

    /* Added style for comment box header */
    .comment-box-header {
        padding: 10px 0;
        cursor: move;
        background-color: #f0f0f0;
        margin: -15px -15px 15px -15px;
        padding: 10px 15px;
        border-bottom: 1px solid #ddd;
        border-radius: 5px 5px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
</style>

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
                            <div class="row justify-content-between px-2">
                                <div class=" p-4">
                                    <h6>Questions for Test: {{ $test->test_name }}</h6>
                                </div>

                                <!-- <div class="m-3">
                                    <a href="{{ route('tests.view') }}" class="btn btn-primary">Back to Approve Test</a>
                                </div> -->
                            </div>

                            <div class="card-body">
                                <div class="question-paper">
                                    @foreach ($paginator as $index => $question)
                                    @if ($question->not_question)
                                    <div class="instruction">
                                        <p class="instruction-text">{!! $question->question !!}</p>
                                    </div>
                                    @else
                                    <div class="question">
                                        <h5><strong>Q{{ $paginator->firstItem() }}:</strong> {!! $question->question !!}</h5>
                                        <div class="options">
                                            <strong>Options:</strong>
                                            <ul class="list-unstyled">
                                                @foreach(json_decode($question->options, true) as $key => $option)
                                                <li><strong>{{ $key }}:</strong> {!! nl2br(e($option)) !!}</li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        <div class="correct-option">
                                            <strong>Correct Answer:</strong> {!! nl2br(e($question->answer)) !!}
                                        </div>
                                    </div>
                                    @endif
                                    @endforeach
                                </div>

                                <div class="text-center mt-4">
                                    @php
                                    $pageButtons = [];
                                    $counter = 1;
                                    @endphp

                                    @foreach($questions as $key => $q)
                                    @php
                                    $label = $q->not_question ? 'Text' : $counter++;
                                    @endphp

                                    <a href="{{ request()->url() }}?page={{ $key + 1 }}"
                                        class="btn btn-sm {{ ($paginator->currentPage() == $key + 1) ? 'btn-primary' : 'btn-outline-primary' }} mx-1">
                                        {{ $label }}
                                    </a>
                                    @endforeach
                                </div>


                                <div class="text-center mt-5 pt-5">
                                    <a href="{{ route('tests.view') }}" class="btn btn-primary">Back to Approve Test</a>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Draggable Comment Box -->
    @if(!$test->is_approved)
    <!-- Draggable Comment Box -->
    <div class="comment-box" id="draggableCommentBox">
        <div class="comment-box-header">
            <label for="comment" class="d-inline-block">Admin Comment</label><br>
            <span id="minimize-btn" style="cursor: pointer;">−</span>
        </div>
        <div>
            <small class="text-info"><strong>Info:</strong> Submitting the comment(s) will reset the test as not submitted</small>
        </div>
        <div id="comment-box-content">
            <form method="POST" action="{{ route('tests.comment', ['test' => $test->id]) }}">
                @csrf
                <div class="form-group">
                    <textarea name="comment" id="comment" class="form-control" rows="">{{ old('comment', $test->comments) }}</textarea>
                </div>
                <button type="submit" class="btn btn-success">Submit Comment</button>
            </form>
        </div>
    </div>
    @endif


    <script>
        // Make the comment box draggable
        document.addEventListener("DOMContentLoaded", function() {
            const commentBox = document.getElementById("draggableCommentBox");
            const commentHeader = commentBox.querySelector(".comment-box-header");
            const minimizeBtn = document.getElementById("minimize-btn");
            const commentContent = document.getElementById("comment-box-content");

            let isDragging = false;
            let offsetX, offsetY;

            // Function to handle the start of dragging
            function startDrag(e) {
                isDragging = true;

                // Get the current position of the mouse relative to the comment box
                const rect = commentBox.getBoundingClientRect();
                offsetX = e.clientX - rect.left;
                offsetY = e.clientY - rect.top;

                // Add event listeners for movement and ending drag
                document.addEventListener("mousemove", onDrag);
                document.addEventListener("mouseup", stopDrag);

                // Prevent text selection during drag
                e.preventDefault();
            }

            // Function to handle dragging
            function onDrag(e) {
                if (!isDragging) return;

                // Calculate the new position
                const x = e.clientX - offsetX;
                const y = e.clientY - offsetY;

                // Update the position
                commentBox.style.left = x + "px";
                commentBox.style.top = y + "px";

                // Remove the default right positioning
                commentBox.style.right = "auto";
            }

            // Function to handle the end of dragging
            function stopDrag() {
                isDragging = false;
                document.removeEventListener("mousemove", onDrag);
                document.removeEventListener("mouseup", stopDrag);
            }

            // Add event listener to the header for dragging
            commentHeader.addEventListener("mousedown", startDrag);

            // Add minimize/maximize functionality
            let isMinimized = false;
            minimizeBtn.addEventListener("click", function() {
                if (isMinimized) {
                    commentContent.style.display = "block";
                    minimizeBtn.textContent = "−";
                    isMinimized = false;
                } else {
                    commentContent.style.display = "none";
                    minimizeBtn.textContent = "+";
                    isMinimized = true;
                }
            });
        });
    </script>

    @include('includes.edit_footer')