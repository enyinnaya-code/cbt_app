<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\SectionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\SchoolClassController;
use App\Http\Controllers\CourseController;
use App\Http\Controllers\TestController;
use App\Http\Controllers\QuestionController;
use App\Http\Controllers\StudentController;
use App\Http\Controllers\AnnouncementController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\SummernoteController;






Route::get('/', function () {
    return view('index');
})->name('login');

// Login routes
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

// Logout route
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');



Route::middleware(['auth'])->group(function () {
    Route::get('/calendar', function () {
        return view('calendar');
    })->name('calendar');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/add-section', [SectionController::class, 'create'])->name('section.create');
    Route::get('/manage-sections', [SectionController::class, 'index'])->name('section.index');
    Route::get('/add-section', [SectionController::class, 'create'])->name('section.create');
    Route::post('/add-section', [SectionController::class, 'store'])->name('section.store');
    Route::get('/add_section', [SectionController::class, 'showSections'])->name('add_section');

    // Update your route definition to accept an ID parameter
    Route::get('/edit_section/{id}', [SectionController::class, 'edit'])->name('edit_section');

    // Add this update route for form submission
    Route::put('/section/{id}', [SectionController::class, 'update'])->name('section.update');

    // For deleting a section (this should match exactly what you have)
    Route::delete('/delete_section/{id}', [SectionController::class, 'destroy'])->name('delete_section');

    Route::get('/add-user', [UserController::class, 'create'])->name('user.add');
    Route::post('/add-user', [UserController::class, 'store'])->name('user.store');

    Route::get('/add-teacher', [UserController::class, 'create'])->name('teacher.add');
    Route::post('/add-teacher', [UserController::class, 'store'])->name('teacher.store');

    Route::get('/manage-user', [UserController::class, 'manageUsers'])->name('users.index');
    Route::get('/edit-user/{id}', [UserController::class, 'edit'])->name('users.edit');
    Route::patch('users/toggle-active/{id}', [UserController::class, 'toggleActive'])->name('users.toggleActive');

    Route::get('/reset-password/{encryptedId}', [UserController::class, 'resetPassword'])->name('users.reset');
    
    Route::delete('/delete-user/{id}', [UserController::class, 'destroy'])->name('users.destroy');
    Route::put('/edit-user/{id}', [UserController::class, 'update'])->name('users.update');


    Route::get('/add-teacher', [UserController::class, 'createTeacher'])->name('teacher.add');
    Route::post('/add-teacher', [UserController::class, 'storeTeacher'])->name('teacher.store');

    Route::get('/manage-teacher', [UserController::class, 'manageTeacher'])->name('teachers.index');
    // Route::get('/get-classes-by-sections', [UserController::class, 'getClassesBySections'])->name('sections.classes');

    Route::get('/edit-teacher/{id}', [UserController::class, 'editTeacher'])->name('teachers.edit');
    Route::patch('teachers/toggle-active/{id}', [UserController::class, 'toggleActiveTeacher'])->name('teachers.toggleActive');

    Route::get('/reset-password/{id}', [UserController::class, 'resetPasswordTeacher'])->name('teachers.reset');
    Route::delete('/delete-teacher/{id}', [UserController::class, 'destroyTeacher'])->name('teachers.destroy');
    Route::put('/edit-teacher/{id}', [UserController::class, 'updateTeacher'])->name('teachers.update');




    Route::get('/add-school-class', [SchoolClassController::class, 'create'])->name('schoolClass.add');
    Route::get('/manage-school-classes', [SchoolClassController::class, 'index'])->name('schoolClass.manage');
    Route::post('/store-school-class', [SchoolClassController::class, 'store'])->name('schoolClass.store');

    Route::get('/edit-school-class/{id}', [SchoolClassController::class, 'edit'])->name('schoolClass.edit');
    Route::delete('/delete-school-class/{id}', [SchoolClassController::class, 'destroy'])->name('schoolClass.delete');

    Route::put('/update-school-class/{id}', [SchoolClassController::class, 'update'])->name('schoolClass.update');

    Route::get('/courses/add', [CourseController::class, 'create'])->name('course.create');
    Route::post('/courses/add', [CourseController::class, 'store'])->name('course.store');
    Route::get('/courses/manage', [CourseController::class, 'index'])->name('course.manage');

    Route::get('/courses/edit/{id}', [CourseController::class, 'edit'])->name('course.edit');

    // Update after editing
    Route::put('/courses/update/{id}', [CourseController::class, 'update'])->name('course.update');

    // Delete
    Route::delete('/courses/delete/{id}', [CourseController::class, 'destroy'])->name('course.delete');


    Route::get('/tests/create', [TestController::class, 'create'])->name('tests.create');
    Route::post('/tests', [TestController::class, 'store'])->name('tests.store');
    Route::get('/tests', [TestController::class, 'index'])->name('tests.index');
    Route::get('/tests/{id}/edit', [TestController::class, 'edit'])->name('tests.edit');
    Route::put('/tests/{id}', [TestController::class, 'update'])->name('tests.update');
    Route::delete('/tests/{id}', [TestController::class, 'destroy'])->name('tests.destroy');
    Route::get('/sections/{id}/classes', [TestController::class, 'getClassesBySection'])->name('sections.classes');
    Route::post('/tests/store', [TestController::class, 'store'])->name('tests.store');
    Route::get('/tests/{id}', [TestController::class, 'show'])->name('tests.show');


    Route::get('/get-subjects-by-section/{sectionId}', [UserController::class, 'getSubjectsBySection']);


    Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
    Route::get('/tests/{test}/set-questions', [QuestionController::class, 'setQuestions'])->name('tests.setQuestions');
    Route::post('/questions/store', [QuestionController::class, 'store'])->name('questions.store');
    Route::get('/tests/{test}/questions', [QuestionController::class, 'setQuestions'])->name('questions.set');
    Route::get('/questions/view/{test}', [QuestionController::class, 'viewQuestions'])->name('questions.view');
    Route::post('/tests/{test}/submit-for-approval', [QuestionController::class, 'submitForApproval'])->name('tests.submitForApproval');



    Route::get('/view-tests', [TestController::class, 'viewTests'])->name('tests.view');
    Route::get('/tests/{test}/approve', [TestController::class, 'approveTest'])->name('tests.approve');
    Route::get('/tests/{test}/check', [TestController::class, 'editCheck'])->name('tests.check');
    Route::delete('/tests/{test}', [TestController::class, 'deleteTest'])->name('tests.delete');
    Route::post('/tests/{test}/comment', [TestController::class, 'submitComment'])->name('tests.comment');



    Route::get('/students/create', [StudentController::class, 'create'])->name('students.create');
    Route::get('/students', [StudentController::class, 'index'])->name('students.index');

    Route::get('/get-classes/{section_id}', [StudentController::class, 'getClasses']);

    

    Route::post('/students', [StudentController::class, 'store'])->name('students.store');
    Route::get('/students/{student}/edit', [StudentController::class, 'edit'])->name('students.edit');
    Route::put('/students/{student}', [StudentController::class, 'update'])->name('students.update');
    Route::delete('/students/{student}', [StudentController::class, 'destroy'])->name('students.destroy');

    Route::patch('/students/{student}/suspend', [StudentController::class, 'suspend'])->name('students.suspend');
    Route::patch('/students/{student}/activate', [StudentController::class, 'activate'])->name('students.activate');
    Route::patch('/students/{student}/reset-password', [StudentController::class, 'resetPassword'])->name('students.reset_password');

    Route::get('/available-tests', [TestController::class, 'available'])->name('tests.available');
    Route::get('/past-tests', [TestController::class, 'past'])->name('tests.past');
    Route::get('/past-tests/view/{testId}', [TestController::class, 'viewPast'])->name('tests.viewPast');

    Route::get('/student/analytics', [TestController::class, 'studentAnalytics'])->name('student.analytics');


    // Route::get('/take-test/{test}', [TestController::class, 'takeTest'])->name('tests.take');

    Route::post('/tests/{id}/submit', [TestController::class, 'submitTest'])->name('tests.submit');

    Route::post('/tests/save-answer', [TestController::class, 'saveAnswer'])->name('tests.saveAnswer');


    Route::get('/schedule-test', [TestController::class, 'schedule'])->name('tests.schedule');
    Route::post('/schedule-test/{id}', [TestController::class, 'saveSchedule'])->name('tests.saveSchedule');
    Route::post('/tests/{id}/cancel-schedule', [TestController::class, 'cancelSchedule'])->name('tests.cancelSchedule');



    Route::get('/calendar/events', [TestController::class, 'calendarEvents'])->name('calendar.events');


    Route::get('/start-test', [TestController::class, 'startTest'])->name('tests.start');
    Route::get('/my-tests', [TestController::class, 'viewTests'])->name('tests.student');

    Route::get('/test/{id}/take', [TestController::class, 'takeTest'])->name('tests.take');


    Route::post('/tests/{id}/force-stop', [TestController::class, 'forceStop'])->name('tests.forceStop');


    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
    Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

    Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
    Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
    Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');

    Route::post('/announcements/mark-all-read', [AnnouncementController::class, 'markAllAsRead'])
        ->name('announcements.markAllRead');

    Route::post('/announcements/{id}/mark-read', [AnnouncementController::class, 'markAsRead'])->name('announcements.markAsRead');


    Route::post('/summernote/upload-image', [SummernoteController::class, 'uploadImage'])->name('summernote.image.upload');




    // Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
    // Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');

    // Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
    // Route::put('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    // Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
});
