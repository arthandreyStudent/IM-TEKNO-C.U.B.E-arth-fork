<?php
require_once 'connect.php';
if (!empty($_SESSION['user_id'])) {
    redirect('dashboard.php');
}
$pageTitle = 'Login';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = trim($_POST['user_id'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $connection->prepare('SELECT UserID, FirstName, LastName, UserType, PasswordHash FROM `User` WHERE UserID = ? LIMIT 1');
    $stmt->bind_param('s', $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if (!$user || !password_verify($password, $user['PasswordHash'])) {
        set_flash('danger', 'Invalid User ID or password.');
    } else {
        $_SESSION['user_id'] = $user['UserID'];
        $_SESSION['user_type'] = $user['UserType'];
        set_flash('success', 'Welcome back, ' . $user['FirstName'] . '!');
        redirect('dashboard.php');
    }
}

require_once ROOT_PATH . '/includes/header.php';
?>
<section class="auth-wrap">
    <form class="auth-card" method="post">
        <h1>Welcome Back</h1>
        <p style="margin-bottom: 30px;">Login using your CIT-U User ID.</p>
        <label for="user_id">User ID</label>
        <input id="user_id" name="user_id" type="text" placeholder="ADMIN-001"
        style="margin-bottom: 12px;" required>
        <label for="password">Password</label>
        <div class="input-wrap">
            <input id="password" name="password" type="password" placeholder="Password" required>
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
        <div class="form-actions form-actions-center">
            <button class="btn btn-gold" type="submit">Login</button>
        </div>
        <p style="margin-top:18px">Default admin: <strong>ADMIN-001</strong> / <strong>admin123</strong></p>
    </form>
</section>

<script>
(function () {
    // Handle all password toggles on the page
    const toggles = document.querySelectorAll('.pw-toggle');
    
    toggles.forEach(toggle => {
        toggle.addEventListener('click', function (e) {
            e.preventDefault();
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