<?php
require_once 'connect.php';
require_once ROOT_PATH . '/includes/options.php';
$pageTitle = 'Register';

$selectedUserType = $_POST['user_type'] ?? 'Student';
$selectedDepartment = $_POST['college_department'] ?? 'DEPT-CCS';
$selectedCourse = $_POST['course'] ?? 'BS Information Technology';
$selectedInstructorDepartment = $_POST['instructor_department'] ?? 'DEPT-CCS';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $userType = $_POST['user_type'] ?? 'Student';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    $departments = college_departments();
    $courses = course_options();

    if ($password !== $confirmPassword) {
        set_flash('danger', 'Passwords do not match.');
    } elseif (!in_array($userType, ['Student', 'Instructor'], true)) {
        set_flash('danger', 'Public registration only allows Student or Instructor accounts.');
    } elseif ($userType === 'Student' && (!isset($departments[$selectedDepartment]) || !in_array($selectedCourse, $courses[$selectedDepartment] ?? [], true))) {
        set_flash('danger', 'Please select a valid department and course combination.');
    } elseif ($userType === 'Instructor' && !isset($departments[$selectedInstructorDepartment])) {
        set_flash('danger', 'Please select a valid instructor department.');
    } else {
        try {
            $connection->begin_transaction();
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $connection->prepare('INSERT INTO `User` (UserID, FirstName, LastName, UserType, Email, PasswordHash) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->bind_param('ssssss', $userId, $firstName, $lastName, $userType, $email, $hash);
            $stmt->execute();

            if ($userType === 'Student') {
                $status = $_POST['enrollment_status'] ?? 'Officially Enrolled';
                $hasLiability = 0;
                $stmt = $connection->prepare('INSERT INTO Student (UserID, Course, EnrollmentStatus, HasLiability) VALUES (?, ?, ?, ?)');
                $stmt->bind_param('sssi', $userId, $selectedCourse, $status, $hasLiability);
                $stmt->execute();
            } else {
                $stmt = $connection->prepare('INSERT INTO Instructor (UserID, Department) VALUES (?, ?)');
                $stmt->bind_param('ss', $userId, $selectedInstructorDepartment);
                $stmt->execute();
            }

            $connection->commit();
            set_flash('success', 'Account registered successfully. You may now login.');
            redirect('login.php');
        } catch (mysqli_sql_exception $e) {
            $connection->rollback();
            set_flash('danger', 'Registration failed. User ID or email may already exist.');
        }
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<section class="auth-wrap">
    <form class="auth-card auth-card-wide" method="post">
        <h1>Create Account</h1>
        <p style="margin-bottom: 30px;">Register as a student or instructor. Admin accounts are created only by an existing admin.</p>
        <div class="form-grid">
            <div>
                <label for="user_id">User ID</label>
                <input id="user_id" name="user_id" type="text" placeholder="22-1234-567" value="<?= h($_POST['user_id'] ?? '') ?>" required>
            </div>
            <div>
                <label for="user_type">User Type</label>
                <select id="user_type" name="user_type" data-user-type>
                    <option value="Student" <?= $selectedUserType === 'Student' ? 'selected' : '' ?>>Student</option>
                    <option value="Instructor" <?= $selectedUserType === 'Instructor' ? 'selected' : '' ?>>Instructor</option>
                </select>
            </div>
            <div>
                <label for="first_name">First Name</label>
                <input id="first_name" name="first_name" type="text" value="<?= h($_POST['first_name'] ?? '') ?>" required>
            </div>
            <div>
                <label for="last_name">Last Name</label>
                <input id="last_name" name="last_name" type="text" value="<?= h($_POST['last_name'] ?? '') ?>" required>
            </div>
            <div class="form-full">
                <label for="email">Email Address</label>
                <input id="email" name="email" type="email" value="<?= h($_POST['email'] ?? '') ?>" required>
            </div>

            <div data-student-fields>
                <label for="college_department">College Department</label>
                <select id="college_department" name="college_department" data-department-select>
                    <?php render_department_options($selectedDepartment); ?>
                </select>
                <div class="help-text">Choose your college department first to load the matching courses.</div>
            </div>
            <div data-student-fields class="form-full">
                <label for="course">Course</label>
                <select id="course" name="course" data-course-select data-selected-course="<?= h($selectedCourse) ?>">
                    <?php render_course_options($selectedDepartment, $selectedCourse); ?>
                </select>
            </div>
            <div data-student-fields class="form-full">
                <label for="enrollment_status">Enrollment Status</label>
                <select id="enrollment_status" name="enrollment_status">
                    <option value="Officially Enrolled" <?= ($_POST['enrollment_status'] ?? '') === 'Officially Enrolled' ? 'selected' : '' ?>>Officially Enrolled</option>
                    <option value="Inactive" <?= ($_POST['enrollment_status'] ?? '') === 'Inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>

            <div data-instructor-fields class="form-full" style="display:none">
                <label for="instructor_department">Instructor Department</label>
                <select id="instructor_department" name="instructor_department">
                    <?php render_department_options($selectedInstructorDepartment); ?>
                </select>
            </div>

            <div>
                <label for="password">Password</label>
                <div class="input-wrap">
                    <input id="password" name="password" type="password" required>
                    <button type="button" class="pw-toggle" aria-label="Toggle password visibility">
                        <!-- Eye open (shown when password is hidden) -->
                        <svg class="pw-icon pw-icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <!-- Eye off (shown when password is visible) -->
                        <svg class="pw-icon pw-icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                            <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                            <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
                            <path d="m2 2 20 20"/>
                        </svg>
                    </button>
                </div>
            </div>
            <div>
                <label for="confirm_password">Confirm Password</label>
                <div class="input-wrap">
                    <input id="confirm_password" name="confirm_password" type="password" required>
                    <button type="button" class="pw-toggle" aria-label="Toggle confirm password visibility">
                        <!-- Eye open (shown when password is hidden) -->
                        <svg class="pw-icon pw-icon-show" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                        <!-- Eye off (shown when password is visible) -->
                        <svg class="pw-icon pw-icon-hide" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                            <path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49"/>
                            <path d="M14.084 14.158a3 3 0 0 1-4.242-4.242"/>
                            <path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143"/>
                            <path d="m2 2 20 20"/>
                        </svg>
                    </button>
                </div>
            </div>
        </div>
        <div class="form-actions">
            <a class="btn btn-outline" href="<?= url('login.php') ?>">Back to Login</a>
            <button class="btn btn-primary" type="submit">Register Account</button>
        </div>
    </form>
</section>
<script>
(function () {
    // Handle all password toggles on the page
    const toggles = document.querySelectorAll('.pw-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function () {
            // Find the input field in the same wrapper
            const input = toggle.parentElement.querySelector('input');
            const iconShow = toggle.querySelector('.pw-icon-show');
            const iconHide = toggle.querySelector('.pw-icon-hide');

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            iconShow.style.display = isHidden ? 'none' : '';
            iconHide.style.display = isHidden ? '' : 'none';
            input.focus();
        });
    });
})();
</script>
<?php require_once ROOT_PATH . '/includes/footer.php'; ?>