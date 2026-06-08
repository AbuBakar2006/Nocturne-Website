<?php
// AdminStaff.php
// Administrator accounts management page. Restricted to SuperAdmin.

require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

// Enforce SuperAdmin only access
if (!isset($_SESSION['admin_role']) || $_SESSION['admin_role'] !== 'SuperAdmin') {
    header("Location: AdminDashboard.php?error=Access+denied.+Restricted+to+Main+Administrator+only.");
    exit;
}

$success_message = '';
$error_message = '';

// 1. Handle Add Admin Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_admin') {
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'Admin';
    
    if (empty($name) || empty($username) || empty($email) || empty($mobile) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($role !== 'SuperAdmin' && $role !== 'Admin') {
        $error_message = "Invalid role selected.";
    } else {
        try {
            // Check for uniqueness of email, username, or mobile
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE username = ? OR email = ? OR mobile = ?");
            $stmt->execute([$username, $email, $mobile]);
            
            if ($stmt->fetchColumn() > 0) {
                $error_message = "The Username, Email, or Mobile is already registered to another administrator account.";
            } else {
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO admins (name, username, email, mobile, password, role) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$name, $username, $email, $mobile, $hashed_pass, $role]);
                $success_message = "Administrator account '{$name}' created successfully.";
            }
        } catch (\PDOException $e) {
            $error_message = "Failed to create account: " . $e->getMessage();
        }
    }
}

// 2. Handle Delete Admin Account
if (isset($_GET['delete'])) {
    $target_id = (int)$_GET['delete'];
    
    if ($target_id === (int)$_SESSION['admin_id']) {
        $error_message = "You cannot delete your own account while logged in.";
    } else {
        try {
            // Check that we aren't deleting the primary admin (ID 1) to prevent complete lockout
            if ($target_id === 1) {
                $error_message = "The primary Main Administrator account (#1) cannot be deleted.";
            } else {
                // Get admin name
                $stmt = $pdo->prepare("SELECT name FROM admins WHERE id = ?");
                $stmt->execute([$target_id]);
                $admin_name = $stmt->fetchColumn();
                
                if ($admin_name) {
                    $stmt = $pdo->prepare("DELETE FROM admins WHERE id = ?");
                    $stmt->execute([$target_id]);
                    $success_message = "Administrator account '{$admin_name}' deleted successfully.";
                } else {
                    $error_message = "Administrator account not found.";
                }
            }
        } catch (\PDOException $e) {
            $error_message = "Failed to delete account: " . $e->getMessage();
        }
    }
}

// 3. Handle Edit Admin Account
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_admin') {
    $target_id = (int)($_POST['admin_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    $role = $_POST['role'] ?? 'Admin';
    $password = $_POST['password'] ?? '';
    
    // Ensure primary admin role remains SuperAdmin
    if ($target_id === 1) {
        $role = 'SuperAdmin';
    }
    
    if ($target_id <= 0 || empty($name) || empty($username) || empty($email) || empty($mobile)) {
        $error_message = "All fields except password are required.";
    } elseif ($role !== 'SuperAdmin' && $role !== 'Admin') {
        $error_message = "Invalid role selected.";
    } else {
        try {
            // Check for uniqueness of email, username, or mobile (excluding target_id)
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM admins WHERE (username = ? OR email = ? OR mobile = ?) AND id != ?");
            $stmt->execute([$username, $email, $mobile, $target_id]);
            
            if ($stmt->fetchColumn() > 0) {
                $error_message = "The Username, Email, or Mobile is already registered to another administrator account.";
            } else {
                if (!empty($password)) {
                    if (strlen($password) < 6) {
                        $error_message = "Password must be at least 6 characters long.";
                    } else {
                        $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, mobile = ?, role = ?, password = ? WHERE id = ?");
                        $stmt->execute([$name, $username, $email, $mobile, $role, $hashed_pass, $target_id]);
                        $success_message = "Administrator account '{$name}' updated successfully.";
                    }
                } else {
                    $stmt = $pdo->prepare("UPDATE admins SET name = ?, username = ?, email = ?, mobile = ?, role = ? WHERE id = ?");
                    $stmt->execute([$name, $username, $email, $mobile, $role, $target_id]);
                    $success_message = "Administrator account '{$name}' updated successfully.";
                }
                
                // If editing self, update session details
                if (empty($error_message) && $target_id === (int)$_SESSION['admin_id']) {
                    $_SESSION['admin_name'] = $name;
                    $_SESSION['admin_username'] = $username;
                    $_SESSION['admin_email'] = $email;
                    $_SESSION['admin_role'] = $role;
                }
            }
        } catch (\PDOException $e) {
            $error_message = "Failed to update account: " . $e->getMessage();
        }
    }
}

// Fetch all admins
try {
    $admins = $pdo->query("SELECT *, DATE_FORMAT(created_at, '%d %b %Y') AS formatted_date FROM admins ORDER BY id ASC")->fetchAll();
    
    // Aggregates counts
    $total_admins = count($admins);
    $super_admins = $pdo->query("SELECT COUNT(*) FROM admins WHERE role = 'SuperAdmin'")->fetchColumn();
    $regular_admins = $total_admins - $super_admins;
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Manage administrators on NOCTURNE panel">
  <title>Admin Management | NOCTURNE</title>
  <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
  <style>
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 2000;
      left: 0;
      top: 0;
      width: 100%;
      height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.6);
      align-items: center;
      justify-content: center;
    }
    .modal.show {
      display: flex;
    }
    .modal-content {
      background-color: #fff;
      padding: 30px;
      border: 2px solid #111;
      box-shadow: 10px 10px 0px #000;
      width: 90%;
      max-width: 500px;
      position: relative;
    }
    .close-btn {
      position: absolute;
      top: 15px;
      right: 20px;
      font-size: 1.5rem;
      cursor: pointer;
      color: #111;
      font-weight: bold;
    }
    .close-btn:hover {
      color: #fca311;
    }
    .form-group {
      margin-bottom: 16px;
      text-align: left;
    }
    .form-group label {
      display: block;
      font-weight: 700;
      margin-bottom: 6px;
      font-size: 0.9rem;
    }
    .form-group input, .form-group select {
      width: 100%;
      padding: 10px;
      border: 1px solid #111;
      border-radius: 4px;
      font-family: inherit;
      font-size: 0.9rem;
      background: #fcfbf0;
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
          <h1>Admin Management</h1>
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

        <!-- METRICS -->
        <section class="admin-stats" style="grid-template-columns: repeat(3, 1fr); margin-bottom: 28px;">
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#fff8e6; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-user-shield" style="color:#fca311; font-size:1.1rem;"></i>
            </div>
            <h3>Total Administrators</h3>
            <div class="value"><?php echo $total_admins; ?></div>
            <div class="trend">System accounts</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#e8f4ff; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-crown" style="color:#3b82f6; font-size:1.1rem;"></i>
            </div>
            <h3>Main Admins</h3>
            <div class="value"><?php echo $super_admins; ?></div>
            <div class="trend">SuperAdmin access</div>
          </div>
          <div class="stat-card">
            <div style="width:42px; height:42px; background:#f0faf0; border-radius:10px; display:flex; align-items:center; justify-content:center; margin-bottom:12px;">
              <i class="fas fa-user" style="color:#10b981; font-size:1.1rem;"></i>
            </div>
            <h3>Staff Admins</h3>
            <div class="value"><?php echo $regular_admins; ?></div>
            <div class="trend">Regular access</div>
          </div>
        </section>

        <!-- ADD BUTTON -->
        <div style="display:flex; justify-content:flex-end; align-items:center; margin-bottom:20px;">
          <button onclick="openAddModal()" class="btn-add">
            <i class="fas fa-plus" style="margin-right:6px;"></i>Add Administrator
          </button>
        </div>

        <!-- ADMINS TABLE -->
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Username</th>
                <th>Email</th>
                <th>Mobile</th>
                <th>Role</th>
                <th>Created</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($admins as $ad): 
                $badge_class = ($ad['role'] === 'SuperAdmin') ? 'status-active' : 'status-shipped';
                $role_label = ($ad['role'] === 'SuperAdmin') ? 'Main Admin' : 'Staff Admin';
              ?>
                <tr>
                  <td>#<?php echo $ad['id']; ?></td>
                  <td><strong><?php echo htmlspecialchars($ad['name']); ?></strong></td>
                  <td><?php echo htmlspecialchars($ad['username']); ?></td>
                  <td><?php echo htmlspecialchars($ad['email']); ?></td>
                  <td><?php echo htmlspecialchars($ad['mobile']); ?></td>
                  <td><span class="status-badge <?php echo $badge_class; ?>"><?php echo $role_label; ?></span></td>
                  <td><?php echo htmlspecialchars($ad['formatted_date']); ?></td>
                  <td>
                    <div class="action-btns" style="display:flex; gap:8px; align-items:center;">
                      <button onclick='openEditModal(<?php echo json_encode($ad, JSON_HEX_APOS|JSON_HEX_QUOT); ?>)' class="btn-edit" style="background:none; border:none; cursor:pointer; font-family:inherit; color:#111; font-weight:700;"><i class="fas fa-edit"></i> Edit</button>
                      <?php if ($ad['id'] == 1): ?>
                        <span style="color:#aaa; font-size:0.8rem; font-weight:700;"><i class="fas fa-lock"></i> Protected</span>
                      <?php elseif ($ad['id'] == $_SESSION['admin_id']): ?>
                        <span style="color:#777; font-size:0.8rem; font-weight:700;"><i class="fas fa-user"></i> Active Self</span>
                      <?php else: ?>
                        <a href="AdminStaff.php?delete=<?php echo $ad['id']; ?>" onclick="return confirm('Are you sure you want to delete administrator \'<?php echo htmlspecialchars(addslashes($ad['name'])); ?>\'?')" class="btn-delete" style="text-decoration:none;"><i class="fas fa-trash"></i> Delete</a>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </main>
    </div>
  </div>

  <!-- ADD ADMIN MODAL -->
  <div id="addModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeAddModal()">&times;</span>
      <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom:10px;">Add Administrator</h2>
      <form method="post" action="AdminStaff.php">
        <input type="hidden" name="action" value="add_admin">
        
        <div class="form-group">
          <label>Full Name*</label>
          <input type="text" name="name" required placeholder="e.g. Bilal Khan">
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div class="form-group">
            <label>Username*</label>
            <input type="text" name="username" required placeholder="e.g. bilalkhan">
          </div>
          <div class="form-group">
            <label>Mobile Number*</label>
            <input type="text" name="mobile" required placeholder="e.g. 03215556666">
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div class="form-group">
            <label>Email Address*</label>
            <input type="email" name="email" required placeholder="e.g. bilal@nocturne.com">
          </div>
          <div class="form-group">
            <label>System Role*</label>
            <select name="role" required>
              <option value="Admin">Staff Admin (Standard permissions)</option>
              <option value="SuperAdmin">Main Admin (Full control + user management)</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label>Account Password*</label>
          <input type="password" name="password" required placeholder="Minimum 6 characters">
        </div>
        
        <div style="display:flex; justify-content: flex-end; gap:12px; margin-top:20px;">
          <button type="button" onclick="closeAddModal()" class="btn-add" style="background:#888;">Cancel</button>
          <button type="submit" class="btn-add">Save Account</button>
        </div>
      </form>
    </div>
  </div>

  <!-- EDIT ADMIN MODAL -->
  <div id="editModal" class="modal">
    <div class="modal-content">
      <span class="close-btn" onclick="closeEditModal()">&times;</span>
      <h2 style="margin-top:0; border-bottom: 2px solid #111; padding-bottom:10px;">Edit Administrator</h2>
      <form method="post" action="AdminStaff.php">
        <input type="hidden" name="action" value="edit_admin">
        <input type="hidden" name="admin_id" id="edit-id">
        
        <div class="form-group">
          <label>Full Name*</label>
          <input type="text" name="name" id="edit-name" required>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div class="form-group">
            <label>Username*</label>
            <input type="text" name="username" id="edit-username" required>
          </div>
          <div class="form-group">
            <label>Mobile Number*</label>
            <input type="text" name="mobile" id="edit-mobile" required>
          </div>
        </div>

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
          <div class="form-group">
            <label>Email Address*</label>
            <input type="email" name="email" id="edit-email" required>
          </div>
          <div class="form-group">
            <label>System Role*</label>
            <select name="role" id="edit-role" required>
              <option value="Admin">Staff Admin (Standard permissions)</option>
              <option value="SuperAdmin">Main Admin (Full control + user management)</option>
            </select>
          </div>
        </div>
        
        <div class="form-group">
          <label>Account Password (Leave blank to keep current)</label>
          <input type="password" name="password" placeholder="At least 6 characters">
        </div>
        
        <div style="display:flex; justify-content: flex-end; gap:12px; margin-top:20px;">
          <button type="button" onclick="closeEditModal()" class="btn-add" style="background:#888;">Cancel</button>
          <button type="submit" class="btn-add">Save Changes</button>
        </div>
      </form>
    </div>
  </div>

  <script>
    function openAddModal() {
        document.getElementById('addModal').classList.add('show');
    }
    function closeAddModal() {
        document.getElementById('addModal').classList.remove('show');
    }
    
    function openEditModal(ad) {
        document.getElementById('edit-id').value = ad.id;
        document.getElementById('edit-name').value = ad.name;
        document.getElementById('edit-username').value = ad.username;
        document.getElementById('edit-mobile').value = ad.mobile;
        document.getElementById('edit-email').value = ad.email;
        
        const roleSelect = document.getElementById('edit-role');
        roleSelect.value = ad.role;
        
        // Lock role change for Primary Admin (ID 1)
        if (ad.id == 1) {
            roleSelect.disabled = true;
        } else {
            roleSelect.disabled = false;
        }
        
        document.getElementById('editModal').classList.add('show');
    }
    
    function closeEditModal() {
        document.getElementById('editModal').classList.remove('show');
    }
    
    // Auto-enable disabled inputs right before form submission so POST parameters are sent
    const editForm = document.querySelector('#editModal form');
    if (editForm) {
        editForm.onsubmit = function() {
            document.getElementById('edit-role').disabled = false;
        };
    }
    
    window.onclick = function(event) {
        var addM = document.getElementById('addModal');
        var editM = document.getElementById('editModal');
        if (event.target == addM) {
            closeAddModal();
        }
        if (event.target == editM) {
            closeEditModal();
        }
    }
  </script>

</body>
</html>
