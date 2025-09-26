<div class="main-sidebar sidebar-style-2 mb-5" style="padding-bottom: 7rem;">
  <aside id="sidebar-wrapper">
    <div class="sidebar-brand">
      <a href="{{ route('dashboard') }}">
        <img alt="image" src="{{ asset('images/testa_logo_lg.png') }}" class="header-logo" />
      </a>

    </div>
    <ul class="sidebar-menu">

      <li class="dropdown">
        <a href="{{ route('dashboard') }}" class="nav-link"><i data-feather="monitor"></i><span>Dashboard</span></a>
      </li>


      <li class="dropdown">
        <a href="{{ route('calendar') }}" class="nav-link">
          <i data-feather="calendar"></i><span>Calendar</span>
        </a>
      </li>



      @if(Auth::user()->user_type == 1 || Auth::user()->user_type == 2)
      <li class="menu-header">Admin</li>


      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown"><i data-feather="users"></i><span>Admin Management</span></a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('user.add') }}">Add Admin</a></li>
          <li><a class="nav-link" href="{{ route('users.index') }}">Manage Admin</a></li>
        </ul>
      </li>

      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown"><i data-feather="users"></i><span>Teachers Management</span></a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('teacher.add') }}">Add Teacher</a></li>
          <li><a class="nav-link" href="{{ route('teachers.index') }}">Manage Teachers</a></li>
        </ul>
      </li>


      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="home"></i><span>Sections/Arms</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('section.create') }}">Add Sections/Arms</a></li>
          <li><a class="nav-link" href="{{ route('section.index') }}">Manage Section/Arms</a></li>
        </ul>
      </li>

      <li class="dropdown" id="menu-class-management">
        <a id="link-class-management" href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="clipboard"></i><span>Class Management</span>
        </a>
        <ul class="dropdown-menu">
          <li id="item-add-class">
            <a class="nav-link" href="{{ route('schoolClass.add') }}">Add Class</a>
          </li>
          <li id="item-manage-classes">
            <a class="nav-link" href="{{ route('schoolClass.manage') }}">Manage Classes</a>
          </li>
        </ul>
      </li>

      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="file-plus"></i><span>Courses/Subjects</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('course.create') }}">Create Course/Subject</a></li>
          <li><a class="nav-link" href="{{ route('course.manage') }}">Manage Course/Subject</a></li>
        </ul>
      </li>

      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="check-square"></i><span>Tests Oversight</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('tests.view') }}">Approve Tests</a></li>
          <li><a class="nav-link" href="{{ route('tests.schedule') }}">Schedule Test</a></li>
        </ul>
      </li>

      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="message-circle"></i><span>Communication</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('announcements.create') }}">Send Announcement</a></li>
          <li><a class="nav-link" href="{{ route('announcements.index') }}">All Announcements</a></li>
        </ul>
      </li>


      <!-- <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="bar-chart-2"></i><span>Analytics</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="performance_overview.php">Performance Overview</a></li>
          <li><a class="nav-link" href="student_statistics.php">Student Statistics</a></li>
          <li><a class="nav-link" href="usage_metrics.php">Usage Metrics</a></li>
        </ul>
      </li> -->


      <!-- <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="file-text"></i><span>Reports</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="generate_reports.php">Generate Reports</a></li>
          <li><a class="nav-link" href="download_reports.php">Download Reports</a></li>
          <li><a class="nav-link" href="report_history.php">Report History</a></li>
        </ul>
      </li> -->

      @endif




      @if(in_array(Auth::user()->user_type, [1, 2, 3]))
      <li class="menu-header">Teachers</li>

      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="file-plus"></i><span>Test Management</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('tests.create') }}">Create Test</a></li>
          <li><a class="nav-link" href="{{ route('tests.index') }}">Manage Tests</a></li>
        </ul>
      </li>
      @endif


      @if(in_array(Auth::user()->user_type, [1, 2, 3]))
      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="help-circle"></i><span>Question Management</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('questions.index') }}">Manage Questions</a></li>
        </ul>
      </li>

      <!-- <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="eye"></i><span>Test Monitoring</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="live_monitoring.php">Live Monitoring</a></li>
          <li><a class="nav-link" href="test_activity_log.php">Activity Logs</a></li>
        </ul>
      </li> -->

      <!-- <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="edit-3"></i><span>Grading & Feedback</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="grade_submissions.php">Grade Submissions</a></li>
          <li><a class="nav-link" href="feedback.php">Give Feedback</a></li>
        </ul>
      </li> -->

      <!-- <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="file-text"></i><span>Reports</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="generate_reports.php">Generate Reports</a></li>
          <li><a class="nav-link" href="download_reports.php">Download Reports</a></li>
          <li><a class="nav-link" href="report_history.php">Report History</a></li>
        </ul>
      </li> -->

      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="user"></i><span>Student Management</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="{{ route('students.create') }}">Add Student</a></li>
          <li><a class="nav-link" href="{{ route('students.index') }}">Manage Students</a></li>
        </ul>
      </li>
      @endif





      @if(in_array(Auth::user()->user_type, [1, 2, 4]))
      <li class="menu-header">Students</li>

      <li>
        <a class="nav-link" href="{{ route('tests.available') }}">
          <i data-feather="check-circle"></i><span>Available Tests</span>
        </a>
      </li>

      <li>
        <a class="nav-link" href="{{ route('tests.start') }}">
          <i data-feather="edit"></i><span>Start Test</span>
        </a>
      </li>


      <li>
        <a class="nav-link" href="{{ route('tests.past') }}">
          <i data-feather="clock"></i><span>Previous Tests</span>
        </a>
      </li>

      <!-- <li>
        <a class="nav-link" href="{{ route('student.analytics')}}">
          <i data-feather="pie-chart"></i><span>Analytics</span>
        </a>
      </li> -->
      @endif
      <!-- 
      <li class="dropdown">
        <a href="#" class="menu-toggle nav-link has-dropdown">
          <i data-feather="help-circle"></i><span>Support & Help</span>
        </a>
        <ul class="dropdown-menu">
          <li><a class="nav-link" href="faq.php">FAQ</a></li>
          <li><a class="nav-link" href="contact_support.php">Contact Support</a></li>
        </ul>
      </li> -->

    </ul>
  </aside>
</div>