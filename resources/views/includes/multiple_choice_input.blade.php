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
            <textarea class="summernote" name="question_text" required rows="4">{{ old('question_text', $currentQuestion->question ?? '') }}</textarea>

          
            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_instruction" id="is_instruction"
                    {{ old('is_instruction', $currentQuestion->not_question ?? false) ? 'checked' : '' }}>
                <label class="form-check-label" for="is_instruction">
                    <em>Check this box if the text is not a question (e.g., comprehension passage, instruction)</em>
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
                        <input type="text" class="form-control" name="options[{{ $key }}]" value="{{ $value }}">
                        <div class="input-group-append">
                            @if ($loop->last && $loop->count < 5)
                                <button type="button" class="btn btn-sm btn-primary m-1 add-option">+</button>
                                @endif
                                @if ($loop->count > 1)
                                <button type="button" class="btn btn-sm btn-danger m-1 remove-option">-</button>
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
                            <button type="button" class="btn btn-sm btn-primary m-1 add-option">+</button>
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
                <option value="{{ $key }}" {{ (old('correct_option', $currentQuestion->answer ?? '') == $key) ? 'selected' : '' }}>{{ $key }}</option>
                @endforeach
            </select>
        </div>

        <div class="form-group col-md-6">
            <label for="mark">Mark</label>
            <input type="number" class="form-control" name="mark" min="1" value="{{ old('mark', $currentQuestion->mark ?? '') }}" placeholder="Type number here">
        </div>
    </div>

    <div class="row justify-content-start px-3" style="gap: 2rem;">
        <div class=" mt-5 pt-5 px-0 mx-0 ">
            <button type="submit" class="btn btn-success px-5">Save</button>
        </div>
        <div class=" mt-5 pt-5 px-0 mx-0">
            <a href="{{ route('questions.index') }}" class="btn btn-primary">Back to Manage Questions</a>
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