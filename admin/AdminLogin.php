<?php
// AdminLogin.php
// Login page for NOCTURNE Admin Panel.

require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Logout
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: AdminLogin.php?message=Logged+out+successfully");
    exit;
}

// Redirect if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header("Location: AdminDashboard.php");
    exit;
}

$error_message = '';

// Handle Login Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_input = trim($_POST['username_or_email_or_mobile'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($login_input) && !empty($password)) {
        try {
            // Find admin by username, email, or mobile
            $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ? OR email = ? OR mobile = ? LIMIT 1");
            $stmt->execute([
                $login_input,
                $login_input,
                $login_input
            ]);
            $admin = $stmt->fetch();

            if ($admin && password_verify($password, $admin['password'])) {
                // Set session variables
                $_SESSION['admin_logged_in'] = true;
                $_SESSION['admin_id'] = $admin['id'];
                $_SESSION['admin_name'] = $admin['name'];
                $_SESSION['admin_username'] = $admin['username'];
                $_SESSION['admin_email'] = $admin['email'];
                $_SESSION['admin_role'] = $admin['role'];

                header("Location: AdminDashboard.php");
                exit;
            } else {
                $error_message = "Invalid login credentials. Please try again.";
            }
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="NOCTURNE Admin Panel Ã¢â‚¬â€œ Secure Login">
  <title>Admin Login | NOCTURNE</title>
  <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
</head>
<body>
  <main class="login-main">
    <div class="login-card" style="max-width: 480px;">

      <div style="text-align:center; margin-bottom: 28px;">
        <div style="width:56px; height:56px; background:#111; border-radius:50%; display:inline-flex; align-items:center; justify-content:center; margin-bottom: 12px;">
          <i class="fas fa-user-shield" style="color:#fca311; font-size:1.4rem;"></i>
        </div>
        <h2 style="margin:0; font-size:1.5rem; letter-spacing:1px;">Admin Access</h2>
        <p style="color:#777; margin-top:6px; font-size:0.9rem;">Restricted to authorized personnel only.</p>
      </div>

      <?php if (!empty($error_message)): ?>
        <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; text-align: left; font-size: 0.9rem;">
          <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['message'])): ?>
        <div style="background-color: #e8f5e9; color: #2e7d32; border: 1.5px solid #2e7d32; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; text-align: left; font-size: 0.9rem;">
          <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($_GET['message']); ?>
        </div>
      <?php endif; ?>

      <form class="login-form" action="AdminLogin.php" method="post">
        <div class="input-group">
          <label for="admin-login-input"><i class="fas fa-user" style="margin-right:6px; color:#aaa;"></i>Email, Username, or Mobile</label>
          <input type="text" id="admin-login-input" name="username_or_email_or_mobile" placeholder="Enter username, email, or mobile" required value="<?php echo htmlspecialchars($login_input ?? ''); ?>" />
        </div>
        <div class="input-group">
          <label for="admin-pass"><i class="fas fa-lock" style="margin-right:6px; color:#aaa;"></i>Password</label>
          <input type="password" id="admin-pass" name="password" placeholder="Enter your password" required />
        </div>

        <div class="login-options">
          <span class="remember-me" style="visibility: hidden;">
            <input type="checkbox" id="remember"> Remember Me
          </span>
          <a href="#" class="forgot-password" onclick="alert('Please contact the head office or system administrator to reset your password.')">Forgot Password?</a>
        </div>

        <button type="submit" class="login-btn">
          <i class="fas fa-sign-in-alt" style="margin-right:8px;"></i>Sign In
        </button>
      </form>



  </main>
  <script>
      localStorage.setItem('admin_logged_in', '<?php echo (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) ? 'true' : 'false'; ?>');
  </script>
</body>

</html>
