<?php
// AdminProfile.php
// Admin Profile and Settings management page.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

$success_message = '';
$error_message = '';

$admin_id = $_SESSION['admin_id'];

// 1. Handle Details Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    
    if (empty($name) || empty($username) || empty($email) || empty($mobile)) {
        $error_message = "All detail fields are required.";
    } else {
        try {
            // Check uniqueness (except current admin)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE (username = ? OR email = ? OR mobile = ?) AND id != ?");
            $stmt->execute([$username, $email, $mobile, $admin_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error_message = "The Username, Email, or Mobile is already in use by another administrator.";
            } else {
                $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, mobile = ? WHERE id = ?");
                $stmt->execute([$name, $username, $email, $mobile, $admin_id]);
                
                // Update Session variables
                $_SESSION['admin_name'] = $name;
                $_SESSION['admin_username'] = $username;
                $_SESSION['admin_email'] = $email;
                
                $success_message = "Profile details updated successfully.";
            }
        } catch (\PDOException $e) {
            $error_message = "Failed to update profile: " . $e->getMessage();
        }
    }
}

// 2. Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_password') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "New password and confirmation do not match.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } else {
        try {
            // Verify current password
            $stmt = $pdo->prepare("SELECT password FROM admins WHERE id = ?");
            $stmt->execute([$admin_id]);
            $db_pass = $stmt->fetchColumn();
            
            if ($db_pass && password_verify($current_password, $db_pass)) {
                // Update password
                $new_hashed = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE admins SET password = ? WHERE id = ?");
                $stmt->execute([$new_hashed, $admin_id]);
                $success_message = "Password updated successfully.";
            } else {
                $error_message = "Incorrect current password.";
            }
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Fetch current admin information from database to keep details fresh
try {
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute([$admin_id]);
    $admin_info = $stmt->fetch();
} catch (\PDOException $e) {
    die("Database load error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage admin settings on NOCTURNE panel">
  <title>Admin Profile | NOCTURNE</title>
  <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    .profile-grid {
      display: grid;
      grid-template-columns: 1.2fr 0.8fr;
      gap: 24px;
    }
    .profile-card {
      background: #fff;
      padding: 30px;
      border: 1.5px solid #111;
      box-shadow: 8px 8px 0px #000;
    }
    .profile-form-group {
      margin-bottom: 20px;
      text-align: left;
    }
    .profile-form-group label {
      display: block;
      font-weight: 700;
      margin-bottom: 8px;
      font-size: 0.9rem;
      color: #333;
    }
    .profile-form-group input {
      width: 100%;
      padding: 12px;
      border: 1.5px solid #111;
      border-radius: 4px;
      font-family: inherit;
      background: #fcfbf0;
      font-size: 0.95rem;
    }
    @media (max-width: 900px) {
      .profile-grid {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>
<body class="admin-body">

  <div class="admin-wrapper">
    <!-- SIDEBAR -->
    <?php include 'sidebar.php'; ?>

    <!-- MAIN -->
    <div class="main">

      <!-- TOPBAR -->
      <header class="admin-header">
        <div style="display:flex; align-items:center; gap:14px;">
          <h1>Profile Settings</h1>
        </div>
      </header>

      <main class="admin-content">

        <?php if (!empty($success_message)): ?>
          <div style="background-color: #e8f5e9; color: #2e7d32; border: 1.5px solid #2e7d32; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
          <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; font-size: 0.9rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <div class="profile-grid">
          
          <!-- Edit Account Details Card -->
          <div class="profile-card">
            <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom: 12px; font-size: 1.3rem; letter-spacing: 0.5px; text-transform: uppercase;">
              <i class="fas fa-user-edit" style="margin-right: 8px;"></i>Account Details
            </h2>
            <form method="post" action="AdminProfile.php">
              <input type="hidden" name="action" value="update_profile">
              
              <div class="profile-form-group">
                <label>Full Name*</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($admin_info['name']); ?>" required>
              </div>

              <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                <div class="profile-form-group">
                  <label>Username*</label>
                  <input type="text" name="username" value="<?php echo htmlspecialchars($admin_info['username']); ?>" required>
                </div>
                <div class="profile-form-group">
                  <label>Mobile Number*</label>
                  <input type="text" name="mobile" value="<?php echo htmlspecialchars($admin_info['mobile']); ?>" required placeholder="e.g. 03001234567">
                </div>
              </div>

              <div class="profile-form-group">
                <label>Email Address*</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($admin_info['email']); ?>" required>
              </div>
              
              <button type="submit" class="btn-add" style="margin-top:10px;">
                <i class="fas fa-save" style="margin-right:8px;"></i>Update Account
              </button>
            </form>
          </div>

          <!-- Change Password Card -->
          <div class="profile-card">
            <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom: 12px; font-size: 1.3rem; letter-spacing: 0.5px; text-transform: uppercase;">
              <i class="fas fa-key" style="margin-right: 8px;"></i>Change Password
            </h2>
            <form method="post" action="AdminProfile.php">
              <input type="hidden" name="action" value="change_password">
              
              <div class="profile-form-group">
                <label>Current Password*</label>
                <input type="password" name="current_password" required placeholder="Enter current password">
              </div>

              <div class="profile-form-group">
                <label>New Password*</label>
                <input type="password" name="new_password" required placeholder="At least 6 characters">
              </div>

              <div class="profile-form-group">
                <label>Confirm New Password*</label>
                <input type="password" name="confirm_password" required placeholder="Retype new password">
              </div>
              
              <button type="submit" class="btn-add" style="margin-top:10px; background:#111;">
                <i class="fas fa-lock" style="margin-right:8px;"></i>Change Password
              </button>
            </form>
          </div>

        </div>

      </main>
    </div>
  </div>

</body>
</html>
