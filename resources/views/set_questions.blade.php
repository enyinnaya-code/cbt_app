@include('includes.head')
<style>
    .modal-backdrop {
        background-color: transparent !important;
        display: none;
    }

    .modal {
        position: fixed;
        top: 10rem;
        left: 0;
        z-index: 1050 !important;
        margin: auto;
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
                            <form class="needs-validation" method="POST" action="{{ route('questions.store') }}"
                                id="questionForm">
                                @csrf
                                <input type="hidden" name="test_id" value="{{ $test->id }}">
                                <div class="card-body mx-0 px-3 mb-3">
                                    <div class="table-responsive">
                                        <table class="table table-bordered table-striped">
                                            <thead>
                                                <tr>
                                                    <th>Duration</th>
                                                    <th>Test Name</th>
                                                    <th>Created By</th>
                                                    <th>Section</th>
                                                    <th>Course</th>
                                                    <th>Class</th>
                                                    <th>Type</th>
                                                    <th>Comments</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <tr>
                                                    <td>{{ $test->duration }} mins</td>
                                                    <td>{{ $test->test_name }}</td>
                                                    <td>{{ $test->creator->name ?? 'N/A' }}</td>
                                                    <td>{{ $test->section->section_name ?? 'N/A' }}</td>
                                                    <td>{{ $test->course->course_name ?? 'N/A' }}</td>
                                                    <td>{{ $test->schoolClass->name ?? 'N/A' }}</td>
                                                    <td>{{ ucfirst(str_replace('_', ' ', $test->test_type)) }}</td>
                                                    <td>
                                                        <a href="#" class="btn btn-sm btn-primary" data-toggle="modal"
                                                            data-target="#commentsModal{{ $test->id }}">
                                                            <i class="fas fa-comments" title="View Comments"></i>
                                                        </a>


                                                    </td>

                                                </tr>
                                            </tbody>
                                        </table>

                                    </div>


                                </div>




                                @if($test->test_type === 'multiple_choice')
                                <div class="px-3 card-header text-dark">
                                    @if (!empty($currentQuestion))
                                    @if (!$currentQuestion->not_question)
                                    <h5><strong>Question {{ $questionNumber }}</strong></h5>
                                    @else
                                    <h5><strong>Text</strong></h5>
                                    @endif
                                    @endif
                                </div>



                                <div class="p-3">
                                    <div class="row px-2" style="gap: 1rem;">
                                        <div class="form-group col-md-8">

                                            @if (!empty($currentQuestion))
                                            <input type="hidden" name="question_id" value="{{ $currentQuestion->id }}">
                                            @endif

                                            <label for="question_text">Question/Text</label>
                                            <textarea class="summernote" name="question_text" required
                                                rows="4">{{ old('question_text', $currentQuestion->question ?? '') }}</textarea>


                                            <div class="form-check mt-2">
                                                <input class="form-check-input" type="checkbox" name="is_instruction"
                                                    id="is_instruction" {{ old('is_instruction',
                                                    $currentQuestion->not_question ?? false) ? 'checked' : '' }}>
                                                <label class="form-check-label" for="is_instruction">
                                                    <em>Check this box if the text is not a question (e.g.,
                                                        comprehension passage, instruction)</em>
                                                </label>
                                            </div>
                                        </div>

                                        <div class="col-md-3">
                                            <!-- Dynamic Option Inputs Container -->
                                            <label class="form-label">Options</label>
                                            <div id="options-container">
                                                @php
                                                $options = json_decode($currentQuestion->options ?? '{}', true);
                                                $optionLabels = ['A', 'B', 'C', 'D', 'E'];
                                                $optionKeys = array_keys($options);
                                                @endphp

                                                @forelse ($options as $key => $value)
                                                <div class="form-group mb-2 option-row" data-option="{{ $key }}">
                                                    <label>Option {{ $key }}</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control"
                                                            name="options[{{ $key }}]" value="{{ $value }}">
                                                        <div class="input-group-append">
                                                            @if ($loop->last && $loop->count < 5) <button type="button"
                                                                class="btn btn-sm btn-primary m-1 add-option">+</button>
                                                                @endif
                                                                @if ($loop->count > 1)
                                                                <button type="button"
                                                                    class="btn btn-sm btn-danger m-1 remove-option">-</button>
                                                                @endif
                                                        </div>
                                                    </div>
                                                </div>
                                                @empty
                                                <div class="form-group mb-2 option-row" data-option="A">
                                                    <label>Option A</label>
                                                    <div class="input-group">
                                                        <input type="text" class="form-control" name="options[A]">
                                                        <div class="input-group-append">
                                                            <button type="button"
                                                                class="btn btn-sm btn-primary m-1 add-option">+</button>
                                                        </div>
                                                    </div>
                                                </div>
                                                @endforelse
                                            </div>
                                        </div>
                                    </div>

                                    <div class="row col-md-6">
                                        <div class="form-group col-md-6">
                                            <label for="correct_option">Correct Option</label>
                                            <select class="form-control" name="correct_option" id="correct_option">
                                                @foreach ($options as $key => $value)
                                                <option value="{{ $key }}" {{ (old('correct_option', $currentQuestion->
                                                    answer ?? '') == $key) ? 'selected' : '' }}>{{ $key }}</option>
                                                @endforeach
                                            </select>
                                        </div>

                                        <div class="form-group col-md-6">
                                            <label for="mark">Mark</label>
                                            <input type="number" class="form-control" name="mark" min="1"
                                                value="{{ old('mark', $currentQuestion->mark ?? '') }}"
                                                placeholder="Type number here">
                                        </div>
                                    </div>

                                    <div class="row justify-content-start px-3" style="gap: 2rem;">
                                        <div class=" mt-5 pt-5 px-0 mx-0 ">
                                            <button type="submit" class="btn btn-success px-5">Save</button>
                                        </div>
                                        <div class=" mt-5 pt-5 px-0 mx-0">
                                            <a href="{{ route('questions.index') }}" class="btn btn-primary">Back to
                                                Manage Questions</a>
                                        </div>
                                    </div>

                                    <!-- Dynamic Question Navigator -->
                                    <div class="mt-4">
                                        <strong>Jump to Question:</strong>
                                        @if (!empty($currentQuestion))
                                        <div class="mb-3">
                                            <a href="{{ route('questions.set', ['test' => $test->id]) }}"
                                                class="btn btn-success btn-sm">
                                                + New Question
                                            </a>
                                        </div>
                                        @endif

                                        <div class="btn-group my-4">
                                            @php $questionCount = 0; @endphp
                                            @foreach($test->questions as $q)
                                            @if (!$q->not_question)
                                            @php $questionCount++; @endphp
                                            <a href="{{ route('questions.set', ['test' => $test->id, 'question_id' => $q->id]) }}"
                                                class="btn btn-outline-secondary p-2 btn-sm {{ isset($currentQuestion) && $q->id === $currentQuestion->id ? 'active' : '' }}">
                                                {{ $questionCount }}
                                            </a>
                                            @else
                                            <a href="{{ route('questions.set', ['test' => $test->id, 'question_id' => $q->id]) }}"
                                                class="btn btn-outline-info p-2 btn-sm {{ isset($currentQuestion) && $q->id === $currentQuestion->id ? 'active' : '' }}">
                                                Text
                                            </a>
                                            @endif
                                            @endforeach
                                        </div>

                                    </div>


                                </div>
                                @else
                                <div class="card-body">
                                    <p class="text-warning">Question input type for <strong>{{ $test->test_type
                                            }}</strong> not implemented yet.</p>
                                </div>
                                @endif
                            </form>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <!-- Modal -->
    <div class="modal fade" id="commentsModal{{ $test->id }}" tabindex="-1" role="dialog"
        aria-labelledby="commentsModalLabel{{ $test->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="commentsModalLabel{{ $test->id }}">Comments for {{ $test->test_name }}
                    </h6>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    {{ $test->comments ?? 'No comments available.' }}
                </div>
            </div>
        </div>
    </div>

    <!-- Help Modal -->
    <div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">🧮 Math & Physics Formula Help</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p>Enter the following LaTeX code into the formula editor:</p>
                    <table class="table table-bordered table-sm">
                        <thead class="thead-light">
                            <tr>
                                <th>Description</th>
                                <th>LaTeX Code</th>
                                <th>Output</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Quadratic Formula</td>
                                <td><code>\frac{-b \pm \sqrt{b^2 - 4ac}}{2a}</code></td>
                                <td>$$\frac{-b \pm \sqrt{b^2 - 4ac}}{2a}$$</td>
                            </tr>
                            <tr>
                                <td>Einstein's Energy</td>
                                <td><code>E = mc^2</code></td>
                                <td>$$E = mc^2$$</td>
                            </tr>
                            <tr>
                                <td>Kinetic Energy</td>
                                <td><code>KE = \frac{1}{2}mv^2</code></td>
                                <td>$$KE = \frac{1}{2}mv^2$$</td>
                            </tr>
                            <tr>
                                <td>Gravitational Force</td>
                                <td><code>F = G \frac{m_1 m_2}{r^2}</code></td>
                                <td>$$F = G \frac{m_1 m_2}{r^2}$$</td>
                            </tr>
                            <tr>
                                <td>Integral</td>
                                <td><code>\int_0^\infty x^2 dx</code></td>
                                <td>$$\int_0^\infty x^2 dx$$</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>




    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const maxOptions = 5;
            const optionLabels = ['A', 'B', 'C', 'D', 'E'];

            // Utility function to manage options in containers
            function setupOptionManagement(containerId, correctSelectId) {
                const container = document.getElementById(containerId);
                const correctSelect = document.getElementById(correctSelectId);

                if (!container || !correctSelect) return;

                function updateCorrectOptions() {
                    correctSelect.innerHTML = "";
                    const rows = container.querySelectorAll(".option-row");
                    rows.forEach(row => {
                        const opt = row.getAttribute("data-option");
                        correctSelect.innerHTML += `<option value="${opt}">${opt}</option>`;
                    });
                }

                container.addEventListener("click", function(e) {
                    if (e.target.classList.contains("add-option")) {
                        const existing = container.querySelectorAll(".option-row").length;
                        if (existing < maxOptions) {
                            const nextOpt = optionLabels[existing];
                            const div = document.createElement("div");
                            div.className = "form-group mb-2 option-row";
                            div.setAttribute("data-option", nextOpt);
                            div.innerHTML = `
                                <label>Option ${nextOpt}</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" name="options[${nextOpt}]" required>
                                    <div class="input-group-append">
                                        <button type="button" class="btn m-1 btn-sm btn-danger remove-option">-</button>
                                        ${existing < maxOptions - 1 ? '<button type="button" class="btn btn-sm m-1 btn-primary add-option">+</button>' : ''}
                                    </div>
                                </div>
                            `;
                            container.appendChild(div);

                            // Remove + from previous
                            const prev = container.querySelectorAll(".option-row")[existing - 1];
                            const prevBtns = prev.querySelector(".input-group-append");
                            if (prevBtns) {
                                const addBtn = prevBtns.querySelector(".add-option");
                                if (addBtn) addBtn.remove();
                            }
                            updateCorrectOptions();
                        }
                    } else if (e.target.classList.contains("remove-option")) {
                        const row = e.target.closest(".option-row");
                        row.remove();
                        const rows = container.querySelectorAll(".option-row");

                        // Add + to the last row again if under max
                        if (rows.length && rows.length < maxOptions) {
                            const lastRow = rows[rows.length - 1];
                            const btnGroup = lastRow.querySelector(".input-group-append");
                            if (!btnGroup.querySelector(".add-option")) {
                                btnGroup.insertAdjacentHTML('beforeend', `<button type="button" class="btn btn-sm btn-primary add-option">+</button>`);
                            }
                        }
                        updateCorrectOptions();
                    }
                });
            }

            // Dynamic form type handlers
            function setupFormTypeHandlers() {
                // Combined Question Type Handler
                const questionInputTypeSelect = document.getElementById("question_input_type");
                const multipleChoiceContainer = document.getElementById("multiple-choice-container");
                const freeTextContainer = document.getElementById("free-text-container");
                const correctOptionContainer = document.getElementById("correct-option-container");

                if (questionInputTypeSelect) {
                    questionInputTypeSelect.addEventListener("change", function() {
                        const selectedType = this.value;

                        // Reset and hide all containers
                        if (multipleChoiceContainer) multipleChoiceContainer.style.display = "none";
                        if (freeTextContainer) freeTextContainer.style.display = "none";
                        if (correctOptionContainer) correctOptionContainer.style.display = "none";

                        // Show appropriate container based on selection
                        if (selectedType === "multiple_choice") {
                            if (multipleChoiceContainer) multipleChoiceContainer.style.display = "block";
                            if (correctOptionContainer) correctOptionContainer.style.display = "block";
                        } else if (selectedType === "free_text") {
                            if (freeTextContainer) freeTextContainer.style.display = "block";
                        }
                    });
                }

                // Text Input Type Handler
                const textInputTypeSelect = document.getElementById("text_input_type");
                const wordLimitContainer = document.getElementById("word-limit-container");
                const numericConfigContainer = document.getElementById("numeric-config-container");
                const numericRangeContainer = document.getElementById("numeric-range-container");

                if (textInputTypeSelect) {
                    textInputTypeSelect.addEventListener("change", function() {
                        const selectedType = this.value;

                        // Hide all configuration containers
                        if (wordLimitContainer) wordLimitContainer.style.display = "none";
                        if (numericConfigContainer) numericConfigContainer.style.display = "none";
                        if (numericRangeContainer) numericRangeContainer.style.display = "none";

                        // Show appropriate container based on selection
                        if (selectedType === "short" || selectedType === "long") {
                            if (wordLimitContainer) wordLimitContainer.style.display = "block";
                        }

                        if (selectedType === "numeric") {
                            if (numericConfigContainer) numericConfigContainer.style.display = "block";
                        }
                    });

                    // Numeric type selection handler
                    const numericTypeSelect = document.querySelector('select[name="numeric_type"]');
                    if (numericTypeSelect) {
                        numericTypeSelect.addEventListener("change", function() {
                            const selectedNumericType = this.value;

                            if (numericRangeContainer) {
                                numericRangeContainer.style.display =
                                    selectedNumericType === "range" ? "block" : "none";
                            }
                        });
                    }
                }
            }

            // Initialize all form handlers
            setupOptionManagement("options-container", "correct_option");
            setupFormTypeHandlers();
        });
    </script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // Get the form
            const form = document.querySelector("form.needs-validation");

            // Check if form exists
            if (!form) return;

            // Add submit event listener to the form
            form.addEventListener("submit", function(event) {
                // Get the checkbox and text area
                const isNotQuestionCheckbox = document.querySelector('input[name="is_not_question"]');
                const textArea = document.querySelector('textarea[name="question_text"]');
                const markInput = document.querySelector('input[name="mark"]');
                const optionsContainer = document.getElementById("options-container");

                // Validation flags
                let isValid = true;
                let errorMessage = "";

                // Check if checkbox exists
                if (isNotQuestionCheckbox) {
                    // If checkbox is checked (text is NOT a question)
                    if (isNotQuestionCheckbox.checked) {
                        // Validate text area is not empty
                        if (!textArea || !textArea.value.trim()) {
                            isValid = false;
                            errorMessage = "Please enter text content for instruction or passage.";
                        }
                    }
                    // If checkbox is not checked (text IS a question)
                    else {
                        // Validate marks field
                        if (!markInput || !markInput.value.trim()) {
                            isValid = false;
                            errorMessage = "Please enter marks for the question.";
                        }

                        // Validate options exist (for multiple choice questions)
                        const questionType = document.getElementById("question_input_type");

                        // If it's a multiple choice question, verify options exist
                        if (questionType && questionType.value === "multiple_choice" ||
                            !questionType && optionsContainer) { // Fallback for direct multiple choice

                            const optionCount = optionsContainer ?
                                optionsContainer.querySelectorAll(".option-row").length : 0;

                            if (optionCount === 0) {
                                isValid = false;
                                errorMessage = errorMessage || "Please add at least one option for the multiple choice question.";
                            }
                        }

                        // Validate that question text is not empty
                        if (!textArea || !textArea.value.trim()) {
                            isValid = false;
                            errorMessage = errorMessage || "Please enter question text.";
                        }
                    }
                }

                // If validation fails, prevent form submission and show error message
                if (!isValid) {
                    event.preventDefault();
                    event.stopPropagation();

                    // Show toaster error
                    toastr.error(errorMessage);
                }
                // No success message here - let the controller handle that after successful submission
            });
        });
    </script>

    <script>
        $(document).ready(function() {
            $('.summernote').summernote({
                height: 300,
                callbacks: {
                    onImageUpload: function(files) {
                        uploadImage(files[0]);
                    }
                }
            });

            function uploadImage(file) {
                let data = new FormData();
                data.append("image", file);
                $.ajax({
                    url: "{{ route('summernote.image.upload') }}",
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}'
                    },
                    method: "POST",
                    data: data,
                    contentType: false,
                    processData: false,
                    success: function(url) {
                        $('.summernote').summernote('insertImage', url);
                    },
                    error: function() {
                        alert("Image upload failed.");
                    }
                });
            }
        });
    </script>

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
    <script id="MathJax-script" async src="https://cdn.jsdelivr.net/npm/mathjax@3/es5/tex-mml-chtml.js">
    </script>


</body>

</html>