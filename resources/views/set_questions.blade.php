@include('includes.head')
<style>
    .ql-formula-tooltip {
        /* Make it position absolute relative to editor */
        position: absolute !important;
        top: 50% !important;
        left: 20% !important;
        /* Vertical center */
        left: 50% !important;
        /* Horizontal center */
        transform: translate(-50%, -50%) !important;
        /* Offset to true center */
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.15);
        z-index: 9999;
    }

    #editor {
        position: relative;
    }

    .ql-snow .ql-tooltip {
        background-color: #fff;
        border: 1px solid #ccc;
        box-shadow: 0px 0px 5px #ddd;
        color: #444;
        padding: 5px 12px;
        white-space: nowrap;
        position: absolute;
        left: 20% !important;
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
                            <form class="needs-validation" method="POST" action="{{ route('questions.store') }}" id="questionForm">
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
                                                        <a href="#" class="btn btn-sm btn-primary" data-toggle="modal" data-target="#commentsModal{{ $test->id }}">
                                                            <i class="fas fa-comments" title="View Comments"></i>
                                                        </a>


                                                    </td>

                                                </tr>
                                            </tbody>
                                        </table>

                                    </div>


                                </div>




                                @if($test->test_type === 'multiple_choice')
                                @include('includes.multiple_choice_input')
                                @elseif($test->test_type === 'combined')
                                @include('includes.combined_question_input')
                                @elseif($test->test_type === 'text_input')
                                @include('includes.text_input_question')
                                @else
                                <div class="card-body">
                                    <p class="text-warning">Question input type for <strong>{{ $test->test_type }}</strong> not implemented yet.</p>
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
    <div class="modal fade" id="commentsModal{{ $test->id }}" tabindex="-1" role="dialog" aria-labelledby="commentsModalLabel{{ $test->id }}" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h6 class="modal-title" id="commentsModalLabel{{ $test->id }}">Comments for {{ $test->test_name }}</h6>
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
    <div class="modal fade" id="helpModal" tabindex="-1" role="dialog" aria-labelledby="helpModalLabel" aria-hidden="true">
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



    <!-- Quill Init -->
    <!-- <script>
        var quill = new Quill('#editor', {
            theme: 'snow',
            modules: {
                formula: true,
                toolbar: '#toolbar'
            }
        });

        const tooltip = quill.theme.tooltip; // Access tooltip instance

        // Override show method to position tooltip center of editor
        const originalShow = tooltip.show.bind(tooltip);
        tooltip.show = function() {
            originalShow();

            // Get editor position and size
            const editorBounds = quill.container.getBoundingClientRect();

            // Calculate center position
            const top = editorBounds.height / 2;
            const left = editorBounds.width / 2;

            // Position tooltip inside editor container
            this.root.style.position = 'absolute';
            this.root.style.top = `${top}px`;
            this.root.style.left = `${left}px`;
            this.root.style.transform = 'translate(-50%, -50%)';
        };

         document.querySelector('form').addEventListener('submit', function() {
    document.querySelector('#question_text').value = quill.root.innerHTML.trim();
  });
    </script> -->

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

    <!-- <script>
        // Re-render math when modal opens
        $('#helpModal').on('shown.bs.modal', function() {
            if (window.MathJax) MathJax.typeset();
        });

        
    </script> -->

    @include('includes.edit_footer')