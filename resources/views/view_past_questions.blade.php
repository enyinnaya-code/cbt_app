@include('includes.head')

<style>
    .instruction {
        background-color: #f4f6f8;
        padding: 15px;
        border-left: 4px solid #007bff;
        border-radius: 5px;
        font-style: italic;
        margin-bottom: 20px;
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
                                <div class="p-4">
                                    <h6>Questions for Test: {{ $test->test_name }}</h6>
                                </div>
                            </div>

                            <div class="card-body">
                                <div class="question-paper">
                                    @php
                                    $questionNumber = 1;
                                    @endphp

                                    @foreach($test->questions as $question)
                                    @if($question->not_question == 1)
                                    <div class="instruction">
                                        {!! $question->question !!}
                                    </div>
                                    @else
                                    @php
                                    $studentAnswer = $studentAnswers[$question->id] ?? null;
                                    $isCorrect = $studentAnswer === $question->answer;
                                    $mark = $question->mark ?? 1;
                                    @endphp

                                    <div class="question">
                                        <h5>
                                            <strong>Q{{ $questionNumber }}:</strong> {!! $question->question !!}
                                            <span class="ml-2 text-dark" style="font-size: 14px;">[{{ $mark }} Mark{{ $mark > 1 ? 's' : '' }}]</span>

                                            @if($studentAnswer !== null)
                                            @if($isCorrect)
                                            <span class="text-success ml-2"><i class="fas fa-check-circle"></i> Correct</span>
                                            @else
                                            <span class="text-danger ml-2"><i class="fas fa-times-circle"></i> Incorrect</span>
                                            @endif
                                            @endif
                                        </h5>

                                        <div class="options">
                                            <strong>Options:</strong>
                                            <ul class="list-unstyled">
                                                @foreach(json_decode($question->options, true) as $key => $option)
                                                <li>
                                                    <strong>{{ $key }}:</strong> {!! nl2br(e($option)) !!}
                                                    @if($studentAnswer === $key)
                                                    @if($isCorrect)
                                                    <span class="text-success ml-2"><i class="fas fa-check"></i></span>
                                                    @else
                                                    <span class="text-danger ml-2"><i class="fas fa-times"></i></span>
                                                    @endif
                                                    @endif
                                                </li>
                                                @endforeach
                                            </ul>
                                        </div>

                                        <div class="correct-option">
                                            <strong>Correct Answer:</strong> {!! nl2br(e($question->answer)) !!}
                                        </div>
                                        <hr class="my-4">
                                    </div>

                                    @php $questionNumber++; @endphp
                                    @endif
                                    @endforeach

                                    @if($test->questions->isEmpty())
                                    <p class="text-center">No questions available for this test.</p>
                                    @endif
                                </div>

                                <div class="text-center mt-4">
                                    <a href="{{ route('tests.past') }}" class="btn btn-primary">Back to Past Tests</a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('includes.edit_footer')