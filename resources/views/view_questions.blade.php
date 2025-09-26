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
                                <h4>Questions for Test: {{ $test->test_name }}</h4>
                            </div>

                            <div class="card-body">
                                <div class="question-paper">
                                    @php $questionNumber = 1; @endphp

                                    @foreach ($paginator as $question)
                                    @if ($question->not_question)
                                    <div class="instruction">
                                        <p class="instruction-text">{!! $question->question !!}</p>
                                    </div>
                                    @else
                                    <div class="question">
                                        <h5><strong>Q{{ $questionNumber }}:</strong> {!! $question->question !!}</h5>
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
                                        <hr class="my-4">
                                    </div>
                                    @php $questionNumber++; @endphp
                                    @endif
                                    @endforeach
                                </div>

                                <div class="text-center mt-4 pt-5">
                                    @php
                                    $labelCounter = 1;
                                    @endphp

                                    @foreach($questions as $index => $q)
                                    @php
                                    $isCurrent = $paginator->currentPage() === ($index + 1);
                                    $label = $q->not_question ? 'Text' : $labelCounter++;
                                    @endphp

                                    <a href="{{ request()->url() }}?page={{ $index + 1 }}"
                                        class="btn btn-sm {{ $isCurrent ? 'btn-primary' : 'btn-outline-primary' }} mx-1">
                                        {{ $label }}
                                    </a>
                                    @endforeach
                                </div>


                                <div class="text-center mt-4 pt-5">
                                    <a href="{{ route('questions.index') }}" class="btn btn-primary">Back to Manage Questions</a>
                                </div>
                            </div>

                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>

    @include('includes.edit_footer')