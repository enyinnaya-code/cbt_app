@include('includes.head')

<body>
    <div class="loader"></div>
    <div id="app">
        <div class="main-wrapper main-wrapper-1">
            <div class="navbar-bg"></div>
            @include('includes.right_top_nav')
            @include('includes.side_nav')

            <!-- Timer - Now movable -->
            <div id="timer" style="position: fixed; top: 10px; right: 10px; z-index: 9999; font-size: 18px; font-weight:600; background-color: white; color: green; padding: 10px; border-radius: 5px; box-shadow: 0 2px 10px rgba(0,0,0,0.2); cursor: move;">
                Time Left: <span id="time-left"></span>
            </div>

            <div class="main-content pt-5 mt-5">
                <section class="section mb-5 pb-1 px-0">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Take Test: {{ $test->test_name }}</h4>
                            </div>

                            <div class="card-body">
                                @if($questions->count() > 0)
                                <form id="test-form" action="{{ route('tests.submit', $test->id) }}" method="POST">
                                    @csrf
                                    <!-- Hidden field for tracking exhausted time -->
                                    <input type="hidden" name="exhausted_time" id="exhausted_time" value="0">

                                    @php $questionNumber = 1; @endphp
                                    @foreach($questions as $question)
                                    @if($question->not_question)
                                    <!-- Render instructions or comprehension -->
                                    <div class="instruction bg-light p-3 mb-4 rounded border">
                                        <p class="mb-0">{!! $question->question !!}</p>
                                    </div>
                                    @else
                                    <!-- Render actual question -->
                                    <div class="question mb-4">
                                        <h5><strong>Q{{ $questionNumber }}:</strong> {!! $question->question !!}</h5>
                                        <div class="options">
                                            @foreach(json_decode($question->options, true) as $key => $option)
                                            <div class="form-check">
                                                <input class="form-check-input answer-option"
                                                    type="radio"
                                                    name="answers[{{ $question->id }}]"
                                                    id="q{{ $question->id }}_{{ $key }}"
                                                    value="{{ $key }}"
                                                    data-question-id="{{ $question->id }}"
                                                    data-test-id="{{ $test->id }}"
                                                    @if(isset($savedAnswers[$question->id]) && strtoupper($savedAnswers[$question->id]) === strtoupper($key)) checked @endif
                                                >

                                                <label class="form-check-label" for="q{{ $question->id }}_{{ $key }}">
                                                    <strong>{{ $key }}:</strong> {!! nl2br(e($option)) !!}
                                                </label>
                                            </div>
                                            @endforeach
                                        </div>
                                    </div>
                                    @php $questionNumber++; @endphp
                                    @endif
                                    @endforeach

                                    <!-- Submit button -->
                                    <div class="text-center mt-4 pt-5 pb-4">
                                        <button type="button" id="submit-btn" class="btn btn-success btn-lg">Submit Test</button>
                                    </div>
                                </form>
                                @else
                                <p class="text-center">No questions available for this test.</p>
                                @endif
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade" id="confirmationModal" tabindex="-1" role="dialog" aria-labelledby="confirmationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmationModalLabel">Confirm Test Submission</h5>
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                        <span aria-hidden="true">&times;</span>
                    </button>
                </div>
                <div class="modal-body">
                    <p><strong>Are you sure you want to submit your test?</strong></p>
                    <p>Once submitted, you will not be able to make any changes to your answers.</p>
                    <div id="submission-summary" class="mt-3">
                        <!-- Summary will be populated by JavaScript -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                    <button type="button" id="confirm-submit" class="btn btn-success">Yes, Submit Test</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Navigation Warning Modal -->
    <div class="modal fade" id="navigationWarningModal" tabindex="-1" role="dialog" aria-labelledby="navigationWarningModalLabel" aria-hidden="true" data-backdrop="static" data-keyboard="false">
        <div class="modal-dialog modal-dialog-centered" role="document">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title" id="navigationWarningModalLabel">⚠️ Navigation Warning</h5>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <strong>Warning!</strong> If you leave this page, your test will be automatically submitted.
                    </div>
                    <p><strong>Are you sure you want to continue?</strong></p>
                    <p>This action cannot be undone and you will not be able to return to the test.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" id="stay-on-page" class="btn btn-primary">Stay on Page</button>
                    <button type="button" id="leave-and-submit" class="btn btn-danger">Leave & Submit Test</button>
                </div>
            </div>
        </div>
    </div>

    @include('includes.edit_footer')

    <script src="{{ asset('js/jquery.min.js') }}"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/4.6.0/js/bootstrap.min.js"></script>
    <script>
        // Make the timer draggable
        const timer = document.getElementById('timer');
        let isDragging = false;
        let offsetX, offsetY;

        timer.addEventListener('mousedown', function(e) {
            isDragging = true;
            offsetX = e.clientX - timer.getBoundingClientRect().left;
            offsetY = e.clientY - timer.getBoundingClientRect().top;
            timer.style.cursor = 'grabbing';
        });

        document.addEventListener('mousemove', function(e) {
            if (isDragging) {
                timer.style.left = (e.clientX - offsetX) + 'px';
                timer.style.top = (e.clientY - offsetY) + 'px';
                timer.style.right = 'auto';
            }
        });

        document.addEventListener('mouseup', function() {
            if (isDragging) {
                isDragging = false;
                timer.style.cursor = 'move';
            }
        });

        // Timer functionality with actual start time
        const duration = {{ $test->duration }}; // Duration in minutes
        const examStartTime = new Date('{{ $examStartTime }}');
        const testId = {{ $test->id }};
        const formElement = document.getElementById('test-form');
        const warningThreshold = 10 * 60; // 10 minutes in seconds

        // Flags to track test state
        let isSubmitting = false;
        let isLegitimateNavigation = false;
        let navigationAttempted = false;

        // Calculate elapsed time since exam started
        function getElapsedTime() {
            const now = new Date();
            const elapsedMs = now.getTime() - examStartTime.getTime();
            return Math.floor(elapsedMs / 1000); // Convert to seconds
        }

        // Calculate remaining time
        function getRemainingTime() {
            const totalDurationSeconds = duration * 60;
            const elapsedSeconds = getElapsedTime();
            return Math.max(0, totalDurationSeconds - elapsedSeconds);
        }

        // Function to format the time in mm:ss
        function formatTime(seconds) {
            let minutes = Math.floor(seconds / 60);
            let secondsRemaining = seconds % 60;
            return minutes.toString().padStart(2, '0') + ":" + secondsRemaining.toString().padStart(2, '0');
        }

        // Function to submit the form with exhausted time
        function submitFormWithExhaustedTime() {
            // Prevent multiple submissions
            if (isSubmitting) return;
            isSubmitting = true;
            isLegitimateNavigation = true;
            
            const exhaustedTime = Math.floor(getElapsedTime()); // Ensure it's an integer
            document.getElementById('exhausted_time').value = exhaustedTime;
            formElement.submit();
        }

        // Initialize timer display
        let timeLeft = getRemainingTime();
        document.getElementById("time-left").textContent = formatTime(timeLeft);

        // Update timer color based on initial time
        if (timeLeft <= warningThreshold) {
            timer.style.color = 'red';
        }

        // Update the timer every second
        let timerInterval = setInterval(function() {
            timeLeft = getRemainingTime();
            document.getElementById("time-left").textContent = formatTime(timeLeft);

            // Change color to red when 10 minutes or less remain
            if (timeLeft <= warningThreshold) {
                timer.style.color = 'red';
            }

            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                // Automatically submit without any alert
                submitFormWithExhaustedTime();
            }
        }, 1000);

        // Enhanced back button prevention
        function preventBackNavigation() {
            // Push multiple states to make it harder to go back
            for (let i = 0; i < 10; i++) {
                history.pushState(null, null, window.location.href);
            }
        }

        // Initialize back button prevention
        preventBackNavigation();

        // Handle popstate events (back/forward button)
        window.addEventListener('popstate', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (isSubmitting || isLegitimateNavigation) {
                return;
            }

            // Prevent the navigation and push state again
            history.pushState(null, null, window.location.href);
            
            // Show warning modal
            navigationAttempted = true;
            $('#navigationWarningModal').modal('show');
        });

        // Enhanced beforeunload event
        window.addEventListener('beforeunload', function(e) {
            // Don't show warning for legitimate navigation
            if (isLegitimateNavigation || isSubmitting) {
                return;
            }
            
            // Standard beforeunload message
            const message = 'If you leave this page, your test will be automatically submitted. Are you sure you want to continue?';
            e.preventDefault();
            e.returnValue = message;
            return message;
        });

        // Handle page visibility change (tab switching, minimizing, etc.)
        document.addEventListener('visibilitychange', function() {
            if (document.hidden && !isSubmitting && !isLegitimateNavigation) {
                // Log tab switching attempt (you can send this to server if needed)
                console.log('User attempted to switch tabs or minimize window');
            }
        });

        // Disable right-click context menu to prevent "Open in new tab" etc.
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });

        // Disable F12, Ctrl+Shift+I, Ctrl+U, etc.
        document.addEventListener('keydown', function(e) {
            // F12 - Developer Tools
            if (e.keyCode === 123) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+Shift+I - Developer Tools
            if (e.ctrlKey && e.shiftKey && e.keyCode === 73) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+U - View Source
            if (e.ctrlKey && e.keyCode === 85) {
                e.preventDefault();
                return false;
            }
            
            // Ctrl+Shift+C - Element Inspector
            if (e.ctrlKey && e.shiftKey && e.keyCode === 67) {
                e.preventDefault();
                return false;
            }
            
            // Alt+F4 - Close Window
            if (e.altKey && e.keyCode === 115) {
                e.preventDefault();
                return false;
            }
        });

        // Navigation warning modal handlers
        document.getElementById('stay-on-page').addEventListener('click', function() {
            $('#navigationWarningModal').modal('hide');
            navigationAttempted = false;
            // Push state again to maintain navigation prevention
            preventBackNavigation();
        });

        document.getElementById('leave-and-submit').addEventListener('click', function() {
            $('#navigationWarningModal').modal('hide');
            submitFormWithExhaustedTime();
        });

        // Mark form submission as legitimate
        if (formElement) {
            formElement.addEventListener('submit', function() {
                isLegitimateNavigation = true;
            });
        }

        // Background answer submission functionality
        document.querySelectorAll('.answer-option').forEach(function(radio) {
            radio.addEventListener('change', function() {
                const questionId = this.dataset.questionId;
                const testId = this.dataset.testId;
                const answer = this.value;

                const questionElement = this.closest('.question');

                if (!questionElement.querySelector('.save-indicator')) {
                    const indicator = document.createElement('div');
                    indicator.className = 'save-indicator';
                    indicator.style.cssText = 'position: absolute; right: 10px; display: inline-block; width: 10px; height: 10px; border-radius: 50%; background-color: #ccc; transition: background-color 0.5s;';
                    questionElement.style.position = 'relative';
                    questionElement.appendChild(indicator);
                }

                const indicator = questionElement.querySelector('.save-indicator');
                indicator.style.backgroundColor = '#ffcc00';

                fetch("{{ route('tests.saveAnswer') }}", {
                        method: 'POST',
                        headers: {
                            'X-CSRF-TOKEN': '{{ csrf_token() }}',
                            'Content-Type': 'application/json'
                        },
                        body: JSON.stringify({
                            test_id: testId,
                            question_id: questionId,
                            answer: answer
                        })
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'saved') {
                            console.log(`Answer for question ${questionId} saved.`);
                            indicator.style.backgroundColor = '#4CAF50';
                            setTimeout(() => {
                                indicator.style.backgroundColor = 'transparent';
                            }, 2000);
                        } else {
                            indicator.style.backgroundColor = '#f44336';
                        }
                    })
                    .catch(error => {
                        console.error('Error saving answer:', error);
                        indicator.style.backgroundColor = '#f44336';
                    });
            });
        });

        // Confirmation modal functionality
        document.getElementById('submit-btn').addEventListener('click', function() {
            // Generate submission summary
            const totalQuestions = document.querySelectorAll('.question').length;
            const answeredQuestions = document.querySelectorAll('input[type="radio"]:checked').length;
            const unansweredQuestions = totalQuestions - answeredQuestions;
            
            const summaryHtml = `
                <div class="alert alert-info">
                    <strong>Submission Summary:</strong><br>
                    Total Questions: ${totalQuestions}<br>
                    Answered: ${answeredQuestions}<br>
                    Unanswered: ${unansweredQuestions}
                </div>
                ${unansweredQuestions > 0 ? '<div class="alert alert-warning"><strong>Warning:</strong> You have ' + unansweredQuestions + ' unanswered question(s).</div>' : ''}
            `;
            
            document.getElementById('submission-summary').innerHTML = summaryHtml;
            
            // Show the modal
            $('#confirmationModal').modal('show');
        });

        // Confirm submission
        document.getElementById('confirm-submit').addEventListener('click', function() {
            $('#confirmationModal').modal('hide');
            submitFormWithExhaustedTime();
        });

        // Additional protection: Monitor for URL changes
        let currentUrl = window.location.href;
        setInterval(function() {
            if (window.location.href !== currentUrl && !isLegitimateNavigation && !isSubmitting) {
                currentUrl = window.location.href;
                submitFormWithExhaustedTime();
            }
        }, 1000);

        // Prevent modal from being closed by clicking outside or pressing ESC
        $('#navigationWarningModal').on('hide.bs.modal', function(e) {
            if (!e.target.classList.contains('btn')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
        
    </script>
</body>