<div class="p-3">


    <div class="row px-2" style="gap: 1rem;">
        <div class="form-group col-md-8">
            <label for="question_text">Question/Text</label>
            <textarea class="summernote" name="question_text" required rows="4"></textarea>

            <div class="form-check mt-2">
                <input class="form-check-input" type="checkbox" name="is_instruction" id="is_instruction">
                <label class="form-check-label" for="is_instruction">
                    <em>Check this box if the text is not a question (e.g., comprehension passage, instruction)</em>
                </label>
            </div>
        </div>

        <div class="col-md-3">
            <label class="form-label">Text Input Configuration</label>
            <div class="form-group mb-3">
                <select class="form-control" id="text_input_type" name="text_input_type" required>
                    <option value="">Select Text Input Type</option>
                    <option value="short">Short Answer</option>
                    <option value="long">Long Answer/Essay</option>
                    <option value="numeric">Numeric Answer</option>
                </select>
            </div>

            <!-- Text Input Configuration Container -->
            <div id="text-input-config-container">
                <div class="form-group mt-2" id="word-limit-container" style="display:none;">
                    <label>Word Limit</label>
                    <input type="number" class="form-control" name="word_limit" min="1" placeholder="Maximum words allowed">
                </div>

                <div class="form-group mt-2" id="numeric-config-container" style="display:none;">
                    <label>Numeric Answer Configuration</label>
                    <select class="form-control" name="numeric_type">
                        <option value="integer">Integer</option>
                        <option value="decimal">Decimal</option>
                        <option value="range">Range</option>
                    </select>

                    <div id="numeric-range-container" class="mt-2" style="display:none;">
                        <div class="input-group">
                            <input type="number" class="form-control" name="numeric_min" placeholder="Minimum Value">
                            <input type="number" class="form-control" name="numeric_max" placeholder="Maximum Value">
                        </div>
                    </div>
                </div>

                <div class="form-group mt-2">
                    <label>Sample/Expected Answer (Optional)</label>
                    <textarea class="form-control" name="expected_answer" rows="3" placeholder="Enter sample answer or key points"></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Marking Section -->
    <div class="row col-md-6">
        <div class="form-group col-md-12">
            <label for="mark">Mark</label>
            <input type="number" class="form-control" name="mark" required min="1" placeholder="e.g 2">
        </div>
    </div>

    <div class="col-md-6 mt-5 pt-5 px-0 mx-0">
        <button type="submit" class="btn btn-success">Save Question</button>
    </div>

    <!-- Question Navigator -->
    <div class="mt-4">
        <strong>Jump to Question:</strong>
        <div class="btn-group">
            <button type="button" class="btn btn-outline-secondary btn-sm">1</button>
            <button type="button" class="btn btn-outline-secondary btn-sm">2</button>
            <button type="button" class="btn btn-outline-secondary btn-sm">3</button>
            <button type="button" class="btn btn-outline-secondary btn-sm">...</button>
            <button type="button" class="btn btn-outline-secondary btn-sm">19</button>
            <button type="button" class="btn btn-outline-secondary btn-sm">20</button>
        </div>
    </div>

</div>