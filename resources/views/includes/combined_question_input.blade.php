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
             <label class="form-label">Question Type</label>
             <div class="form-group mb-3">
                 <select class="form-control" id="question_input_type" name="question_input_type" required>
                     <option value="">Select Question Input Type</option>
                     <option value="multiple_choice">Multiple Choice</option>
                     <option value="free_text">Free Text Answer</option>
                 </select>
             </div>

             <!-- Multiple Choice Options Container -->
             <div id="multiple-choice-container" style="display:none;">
                 <label class="form-label">Options</label>
                 <div id="options-container">
                     <!-- Default: Option A -->
                     <div class="form-group mb-2 option-row" data-option="A">
                         <label>Option A</label>
                         <div class="input-group">
                             <input type="text" class="form-control" name="options[A]">
                             <div class="input-group-append">
                                 <button type="button" class="btn btn-sm btn-primary m-1 add-option">+</button>
                             </div>
                         </div>
                     </div>
                 </div>
             </div>

             <!-- Free Text Answer Container -->
             <div id="free-text-container" style="display:none;">
                 <label class="form-label">Free Text Answer Configuration</label>
                 <div class="form-group">
                     <select class="form-control" name="free_text_type">
                         <option value="short">Short Answer</option>
                         <option value="long">Long Answer</option>
                     </select>
                 </div>
                 <div class="form-group mt-2">
                     <label>Sample/Expected Answer (Optional)</label>
                     <textarea class="form-control" name="expected_answer" rows="3" placeholder="Enter sample answer or key points"></textarea>
                 </div>
             </div>
         </div>
     </div>

     <!-- Correct Option/Marking Section -->
     <div class="row col-md-6">
         <div class="form-group col-md-6" id="correct-option-container" style="display:none;">
             <label for="correct_option">Correct Option</label>
             <select class="form-control" name="correct_option" id="correct_option">
                 <option value="A">A</option>
             </select>
         </div>

         <div class="form-group col-md-6">
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