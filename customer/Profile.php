<?php
// customer/Profile.php
$page_title = 'My Profile';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: Login.php?message=Please+sign+in+to+access+your+profile.");
    exit;
}

// Prevent back-button caching for logged-in customer pages
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

require_once '../admin/db_connect.php';


$error_message = '';
$success_message = '';
$customer_id = $_SESSION['customer_id'];

// Handle Profile Update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $name = trim($_POST['name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');

    if (empty($name) || empty($phone) || empty($city)) {
        $error_message = "All fields are required.";
    } else {
        try {
            $stmt = $pdo->prepare("UPDATE customers SET name = ?, phone = ?, city = ? WHERE id = ?");
            $stmt->execute([$name, $phone, $city, $customer_id]);

            // Update session data
            $_SESSION['customer_name'] = $name;
            $success_message = "Your profile has been updated successfully.";
        } catch (\PDOException $e) {
            $error_message = "Database update error: " . $e->getMessage();
        }
    }
}

// Fetch current details & stats
try {
    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(joined_date, '%M %d, %Y') AS formatted_join FROM customers WHERE id = ? LIMIT 1");
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch();

    if (!$customer) {
        header("Location: Login.php?logout=true");
        exit;
    }

    // Count orders
    $orders_stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE customer_id = ?");
    $orders_stmt->execute([$customer_id]);
    $total_orders = $orders_stmt->fetchColumn();

} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

require_once 'header.php';
?>

    <main class="page-main">
      <div class="page-header" style="background-color:#111; color:#fff; padding:60px 20px; text-align:center;">
        <h1>Your Dashboard</h1>
        <p>Manage your account settings and view purchase stats.</p>
      </div>

      <div style="max-width:1100px; margin: 50px auto 80px; padding: 0 20px;" class="profile-container">
        
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

        <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px; align-items: stretch;" class="profile-layout-grid">
          
          <!-- Edit Details Form -->
          <div style="background:#fff; border: 1.5px solid #111; box-shadow: 4px 4px 0px #000; padding:35px; display:flex; flex-direction:column; justify-content:space-between;">
            <div>
              <h2 style="font-size:1.4rem; font-weight:bold; margin-bottom:20px; border-bottom:2px solid #111; padding-bottom:8px; color:#111;">Update Personal Information</h2>
              
              <form action="Profile.php" method="post" class="login-form" style="gap:20px;">
                <div class="input-group">
                  <label for="prof-email">Email Address (Read-Only)</label>
                  <input type="email" id="prof-email" value="<?php echo htmlspecialchars($customer['email']); ?>" disabled style="background:#eee; color:#666; cursor:not-allowed;" />
                </div>
                
                <div class="input-group">
                  <label for="prof-name">Full Name*</label>
                  <input type="text" id="prof-name" name="name" value="<?php echo htmlspecialchars($customer['name']); ?>" required />
                </div>

                <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;" class="form-row">
                  <div class="input-group">
                    <label for="prof-phone">Phone Number*</label>
                    <input type="text" id="prof-phone" name="phone" value="<?php echo htmlspecialchars($customer['phone']); ?>" required />
                  </div>
                  <div class="input-group">
                    <label for="prof-city">City*</label>
                    <input type="text" id="prof-city" name="city" value="<?php echo htmlspecialchars($customer['city']); ?>" required />
                  </div>
                </div>

                <button type="submit" name="update_profile" class="login-btn" style="margin-top:10px; border-radius:0;">Save Profile Changes</button>
              </form>
            </div>
          </div>

          <!-- Account Stats Card -->
          <div style="background:#fff; border:1.5px solid #111; box-shadow: 4px 4px 0px #000; padding:35px; display:flex; flex-direction:column; gap:20px; justify-content:space-between;">
            <div>
              <h2 style="font-size:1.4rem; font-weight:bold; border-bottom:2px solid #111; padding-bottom:8px; color:#111; margin-bottom:25px;">Account Stats</h2>
              
              <div style="margin-bottom:25px;">
                <span style="color:#777; font-size:0.85rem; display:block; text-transform:uppercase; font-weight:bold; letter-spacing:1px; margin-bottom:4px;">Joined Date</span>
                <span style="font-size:1.15rem; font-weight:bold; color:#111;"><?php echo htmlspecialchars($customer['formatted_join']); ?></span>
              </div>

              <div style="margin-bottom:25px;">
                <span style="color:#777; font-size:0.85rem; display:block; text-transform:uppercase; font-weight:bold; letter-spacing:1px; margin-bottom:4px;">Total Orders</span>
                <span style="font-size:1.15rem; font-weight:bold; color:#111;"><?php echo $total_orders; ?> orders placed</span>
              </div>
            </div>

            <div style="border-top:1px solid #eee; padding-top:20px; display:flex; flex-direction:column; gap:12px;">
              <a href="Orders.php" class="login-btn" style="text-decoration:none; display:block; text-align:center; padding:10px; font-size:0.95rem; border-radius:0;">
                <i class="fas fa-shopping-bag" style="margin-right:8px;"></i>View Order History
              </a>
              <a href="Login.php?logout=true" class="login-btn" style="text-decoration:none; display:block; text-align:center; padding:10px; font-size:0.95rem; border-radius:0; background:#fff; color:#111; border:1.5px solid #111; box-shadow:none;">
                <i class="fas fa-sign-out-alt" style="margin-right:8px;"></i>Sign Out
              </a>
            </div>
          </div>

        </div>
      </div>
    </main>

<?php require_once 'footer.php'; ?>
