<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
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
use App\Http\Controllers\Student;
use App\Http\Controllers\Console;
use App\Http\Controllers\Api\V1\HandoffController;
use App\Http\Controllers\Site;






// The landing page is open to everyone: a signed-in student can come back to it and go back to their account from it.
Route::get('/', [Site\HomeController::class, 'index'])->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
});

// Public pages: news, scholarships, blog, events and pricing. Open to everyone, signed in or not.
Route::get('/news', [Site\PostController::class, 'index'])->defaults('page', 'news')->name('news');
Route::get('/scholarships', [Site\PostController::class, 'index'])->defaults('page', 'scholarships')->name('scholarships');
Route::get('/blog', [Site\PostController::class, 'index'])->defaults('page', 'blog')->name('blog');
Route::get('/articles/{slug}', [Site\PostController::class, 'show'])->name('articles.show');
Route::get('/events', [Site\EventController::class, 'index'])->name('events');
Route::get('/pricing', [Site\PricingController::class, 'index'])->name('pricing');
// Paystack sends the student back here; it checks with Paystack itself, so no sign-in is needed to land on it.
Route::get('/checkout/callback', [Student\CheckoutController::class, 'callback'])->name('checkout.callback');
Route::get('/download', [Site\HomeController::class, 'download'])->name('download');

// The app opens the website already signed in (single use, signed, five minutes).
Route::get('/app-link/{user}', [HandoffController::class, 'open'])->middleware(['signed', 'throttle:20,1'])->name('app.handoff');

// Login routes
Route::post('/login', [LoginController::class, 'login'])->name('login.submit');

// Public student sign-up
Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'showForm'])->name('register');
    Route::post('/register', [RegisterController::class, 'register'])->middleware('throttle:10,1')->name('register.submit');
});

// Logout route
Route::post('/logout', [LoginController::class, 'logout'])->name('logout');



Route::middleware(['auth'])->group(function () {
    Route::get('/calendar', function () {
        return view('calendar');
    })->name('calendar');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Console for examiners and admins
    Route::middleware('role:admin,examiner')->prefix('console')->name('console.')->group(function () {
        Route::get('papers', [Console\PaperController::class, 'index'])->name('papers.index');
        Route::get('papers/create', [Console\PaperController::class, 'create'])->name('papers.create');
        Route::post('papers', [Console\PaperController::class, 'store'])->name('papers.store');
        Route::get('papers/{paper}', [Console\PaperController::class, 'show'])->name('papers.show');
        Route::put('papers/{paper}', [Console\PaperController::class, 'update'])->name('papers.update');
        Route::delete('papers/{paper}', [Console\PaperController::class, 'destroy'])->name('papers.destroy');

        Route::get('papers/{paper}/questions/create', [Console\QuestionController::class, 'create'])->name('questions.create');
        Route::post('papers/{paper}/questions', [Console\QuestionController::class, 'store'])->name('questions.store');
        Route::get('questions/{question}/edit', [Console\QuestionController::class, 'edit'])->name('questions.edit');
        Route::put('questions/{question}', [Console\QuestionController::class, 'update'])->name('questions.update');
        Route::delete('questions/{question}', [Console\QuestionController::class, 'destroy'])->name('questions.destroy');

        Route::get('import/sample', [Console\ImportController::class, 'sample'])->name('import.sample');
        Route::get('papers/{paper}/import', [Console\ImportController::class, 'create'])->name('import.create');
        Route::post('papers/{paper}/import', [Console\ImportController::class, 'store'])->name('import.store');

        Route::get('topics', [Console\TopicController::class, 'index'])->name('topics.index');
        Route::post('topics', [Console\TopicController::class, 'store'])->name('topics.store');
        Route::put('topics/{topic}', [Console\TopicController::class, 'update'])->name('topics.update');
        Route::delete('topics/{topic}', [Console\TopicController::class, 'destroy'])->name('topics.destroy');

        Route::get('packs', [Console\PackController::class, 'index'])->name('packs.index');

        // Admin only: publishing changes what students see; the rest change accounts or move old data.
        Route::middleware('role:admin')->group(function () {
            Route::post('papers/{paper}/publish', [Console\PaperController::class, 'publish'])->name('papers.publish');
            Route::post('papers/{paper}/unpublish', [Console\PaperController::class, 'unpublish'])->name('papers.unpublish');
            Route::post('packs/rebuild', [Console\PackController::class, 'rebuild'])->name('packs.rebuild');

            Route::get('payments', [Console\PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{order}', [Console\PaymentController::class, 'show'])->name('payments.show');
            Route::get('payments/{order}/proof', [Console\PaymentController::class, 'proof'])->name('payments.proof');
            Route::post('payments/{order}/approve', [Console\PaymentController::class, 'approve'])->name('payments.approve');
            Route::post('payments/{order}/reject', [Console\PaymentController::class, 'reject'])->name('payments.reject');

            Route::get('exams', [Console\ExamController::class, 'index'])->name('exams.index');
            Route::post('exams', [Console\ExamController::class, 'store'])->name('exams.store');
            Route::get('exams/{exam}', [Console\ExamController::class, 'edit'])->name('exams.edit');
            Route::put('exams/{exam}', [Console\ExamController::class, 'update'])->name('exams.update');
            Route::post('exams/{exam}/subjects', [Console\ExamController::class, 'attach'])->name('exams.attach');
            Route::delete('exams/{exam}/subjects/{subject}', [Console\ExamController::class, 'detach'])->name('exams.detach');
            Route::post('subjects', [Console\ExamController::class, 'storeSubject'])->name('subjects.store');

            Route::resource('posts', Console\PostController::class)->except('show');
            Route::resource('events', Console\EventController::class)->except('show');
            Route::get('settings', [Console\SettingsController::class, 'edit'])->name('settings.edit');
            Route::put('settings', [Console\SettingsController::class, 'update'])->name('settings.update');

            Route::get('legacy', [Console\LegacyController::class, 'index'])->name('legacy.index');
            Route::post('legacy/{test}', [Console\LegacyController::class, 'store'])->name('legacy.store');

            Route::get('users', [Console\UserController::class, 'index'])->name('users.index');
            Route::post('users', [Console\UserController::class, 'store'])->name('users.store');
            Route::patch('users/{user}/role', [Console\UserController::class, 'role'])->name('users.role');
            Route::patch('users/{user}/toggle', [Console\UserController::class, 'toggle'])->name('users.toggle');
        });
    });

    // Unlocking subjects and my purchases
    Route::get('/checkout', [Student\CheckoutController::class, 'show'])->name('checkout.show');
    Route::post('/checkout', [Student\CheckoutController::class, 'store'])->middleware('throttle:10,1')->name('checkout.store');
    Route::get('/orders', [Student\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{reference}', [Student\OrderController::class, 'show'])->name('orders.show');
    Route::post('/orders/{reference}/paid', [Student\OrderController::class, 'submitProof'])->middleware('throttle:10,1')->name('orders.proof');
    Route::post('/orders/{reference}/cancel', [Student\OrderController::class, 'cancel'])->name('orders.cancel');

    // Student area
    Route::prefix('practice')->name('practice.')->group(function () {
        Route::get('/', [Student\PracticeController::class, 'index'])->name('index');
        Route::get('/session', [Student\PracticeController::class, 'session'])->name('session');
        Route::post('/attempts', [Student\PracticeController::class, 'attempts'])->middleware('throttle:120,1')->name('attempts');
    });
    Route::prefix('mock')->name('mock.')->group(function () {
        Route::get('/', [Student\MockController::class, 'index'])->name('index');
        Route::post('/', [Student\MockController::class, 'start'])->middleware('throttle:20,1')->name('start');
        Route::get('/{run}', [Student\MockController::class, 'show'])->name('show');
        Route::post('/{run}/save', [Student\MockController::class, 'save'])->middleware('throttle:120,1')->name('save');
        Route::post('/{run}/submit', [Student\MockController::class, 'submit'])->middleware('throttle:20,1')->name('submit');
        Route::get('/{run}/result', [Student\MockController::class, 'result'])->name('result');
        Route::get('/{run}/review', [Student\MockController::class, 'review'])->name('review');
    });
    Route::get('/saved', [Student\SavedController::class, 'index'])->name('saved');
    Route::post('/saved/toggle', [Student\SavedController::class, 'toggle'])->middleware('throttle:120,1')->name('saved.toggle');
    Route::get('/progress', Student\ProgressController::class)->name('progress');
    Route::get('/profile', [Student\ProfileController::class, 'edit'])->name('profile');
    Route::put('/profile', [Student\ProfileController::class, 'update'])->name('profile.update');
    Route::put('/profile/password', [Student\ProfileController::class, 'password'])->name('profile.password');

    Route::middleware('role:admin')->group(function () {
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
        Route::get('/get-classes-by-sections', [UserController::class, 'getClassesBySections'])->name('sections.classes');

        Route::get('/edit-teacher/{id}', [UserController::class, 'editTeacher'])->name('teachers.edit');
        Route::patch('teachers/toggle-active/{id}', [UserController::class, 'toggleActiveTeacher'])->name('teachers.toggleActive');

        Route::get('/reset-password/{id}', [UserController::class, 'resetPasswordTeacher'])->name('teachers.reset');
        Route::delete('/delete-teacher/{id}', [UserController::class, 'destroyTeacher'])->name('teachers.destroy');
        Route::put('/edit-teacher/{id}', [UserController::class, 'updateTeacher'])->name('teachers.update');
    });

    Route::middleware('role:admin')->group(function () {
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
    });

    Route::middleware('role:admin,examiner')->group(function () {
        Route::get('/tests/create', [TestController::class, 'create'])->name('tests.create');
        // Route::post('/tests', [TestController::class, 'store'])->name('tests.store');
        Route::get('/tests', [TestController::class, 'index'])->name('tests.index');
        Route::get('/tests/{id}/edit', [TestController::class, 'edit'])->name('tests.edit');
        Route::put('/tests/{id}', [TestController::class, 'update'])->name('tests.update');
        Route::delete('/tests/{id}', [TestController::class, 'destroy'])->name('tests.destroy');
        Route::get('/sections/{id}/classes', [TestController::class, 'getClassesBySection'])->name('sections.classes');
        Route::post('/tests/store', [TestController::class, 'store'])->name('tests.store');
        Route::get('/tests/{id}', [TestController::class, 'show'])->name('tests.show');
    });

    Route::middleware('role:admin')->group(function () {
        Route::get('/get-subjects-by-section/{sectionId}', [UserController::class, 'getSubjectsBySection']);
    });

    Route::middleware('role:admin,examiner')->group(function () {
        Route::get('/questions', [QuestionController::class, 'index'])->name('questions.index');
        Route::get('/tests/{test}/set-questions', [QuestionController::class, 'setQuestions'])->name('tests.setQuestions');
        Route::post('/questions/store', [QuestionController::class, 'store'])->name('questions.store');
        Route::get('/tests/{test}/questions', [QuestionController::class, 'setQuestions'])->name('questions.set');
        Route::get('/questions/view/{test}', [QuestionController::class, 'viewQuestions'])->name('questions.view');
        Route::post('/tests/{test}/submit-for-approval', [QuestionController::class, 'submitForApproval'])->name('tests.submitForApproval');
    });

    Route::get('/view-tests', [TestController::class, 'viewTests'])->name('tests.view');
    Route::middleware('role:admin')->group(function () {
        Route::get('/tests/{test}/approve', [TestController::class, 'approveTest'])->name('tests.approve');
        Route::get('/tests/{test}/check', [TestController::class, 'editCheck'])->name('tests.check');
        Route::delete('/tests/{test}', [TestController::class, 'deleteTest'])->name('tests.delete');
        Route::post('/tests/{test}/comment', [TestController::class, 'submitComment'])->name('tests.comment');
    });



    Route::middleware('role:admin,examiner')->group(function () {
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
    });

    Route::get('/available-tests', [TestController::class, 'available'])->name('tests.available');
    Route::get('/past-tests', [TestController::class, 'past'])->name('tests.past');
    Route::get('/past-tests/view/{testId}', [TestController::class, 'viewPast'])->name('tests.viewPast');

    Route::get('/student/analytics', [TestController::class, 'studentAnalytics'])->name('student.analytics');


    // Route::get('/take-test/{test}', [TestController::class, 'takeTest'])->name('tests.take');

    Route::post('/tests/{id}/submit', [TestController::class, 'submitTest'])->name('tests.submit');

    Route::post('/tests/save-answer', [TestController::class, 'saveAnswer'])->name('tests.saveAnswer');


    Route::middleware('role:admin')->group(function () {
        Route::get('/schedule-test', [TestController::class, 'schedule'])->name('tests.schedule');
        Route::post('/schedule-test/{id}', [TestController::class, 'saveSchedule'])->name('tests.saveSchedule');
        Route::post('/tests/{id}/cancel-schedule', [TestController::class, 'cancelSchedule'])->name('tests.cancelSchedule');
    });



    Route::get('/calendar/events', [TestController::class, 'calendarEvents'])->name('calendar.events');


    Route::get('/start-test', [TestController::class, 'startTest'])->name('tests.start');
    Route::get('/my-tests', [TestController::class, 'viewTests'])->name('tests.student');

    Route::get('/test/{id}/take', [TestController::class, 'takeTest'])->name('tests.take');


    Route::middleware('role:admin')->group(function () {
        Route::post('/tests/{id}/force-stop', [TestController::class, 'forceStop'])->name('tests.forceStop');
    });


    Route::get('/announcements', [AnnouncementController::class, 'index'])->name('announcements.index');
    Route::middleware('role:admin')->group(function () {
        Route::get('/announcements/create', [AnnouncementController::class, 'create'])->name('announcements.create');
        Route::post('/announcements', [AnnouncementController::class, 'store'])->name('announcements.store');

        Route::get('/announcements/{announcement}/edit', [AnnouncementController::class, 'edit'])->name('announcements.edit');
        Route::put('/announcements/{announcement}', [AnnouncementController::class, 'update'])->name('announcements.update');
        Route::delete('/announcements/{announcement}', [AnnouncementController::class, 'destroy'])->name('announcements.destroy');
    });

    Route::post('/announcements/mark-all-read', [AnnouncementController::class, 'markAllAsRead'])
        ->name('announcements.markAllRead');

    Route::post('/announcements/{id}/mark-read', [AnnouncementController::class, 'markAsRead'])->name('announcements.markAsRead');


    Route::middleware('role:admin,examiner')->group(function () {
        Route::post('/summernote/upload-image', [SummernoteController::class, 'uploadImage'])->name('summernote.image.upload');
    });




    // Route::get('/questions/create', [QuestionController::class, 'create'])->name('questions.create');
    // Route::post('/questions', [QuestionController::class, 'store'])->name('questions.store');

    // Route::get('/questions/{question}/edit', [QuestionController::class, 'edit'])->name('questions.edit');
    // Route::put('/questions/{question}', [QuestionController::class, 'update'])->name('questions.update');
    // Route::delete('/questions/{question}', [QuestionController::class, 'destroy'])->name('questions.destroy');
});
