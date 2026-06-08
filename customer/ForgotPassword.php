<?php
// customer/ForgotPassword.php
// Password recovery page for NOCTURNE Customers.

require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header("Location: Homepage.php");
    exit;
}

$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($email) || empty($phone) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif (strlen($new_password) < 6) {
        $error_message = "New password must be at least 6 characters long.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // Verify if customer exists with matching email & phone
            $stmt = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND phone = ? LIMIT 1");
            $stmt->execute([$email, $phone]);
            $customer = $stmt->fetch();

            if ($customer) {
                // Update password
                $hashed_pass = password_hash($new_password, PASSWORD_DEFAULT);
                $update_stmt = $pdo->prepare("UPDATE customers SET password = ? WHERE id = ?");
                $update_stmt->execute([$hashed_pass, $customer['id']]);

                header("Location: Login.php?message=Password+successfully+reset.+You+can+now+login.");
                exit;
            } else {
                $error_message = "No account found matching this email and phone number.";
            }
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NOCTURNE - Recover Password</title>
    <link rel="stylesheet" href="../style.css" />
    <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  </head>
  <body>
    <header>
      <ul>
        <li class="header-brand">
          <img src="../images/nocturne_logo.png" class="header-logo" />
          <span class="header-brand-name">Nocturne</span>
        </li>
        <li>
          <a href="Homepage.php">HOME</a>
          <a href="Products.php">PRODUCTS</a>
          <a href="Contact.php">CONTACT</a>
          <a href="AboutUs.php">ABOUT US</a>
        </li>
        <li>
          <a href="Login.php">
            <img src="../images/user.svg" class="header-icon" />
          </a>
          <a href="Cart.php">
            <img src="../images/shopping-cart.svg" class="header-icon" />
          </a>
        </li>
      </ul>
    </header>

    <main class="login-main">
      <div class="login-card" style="max-width: 500px;">
        <h2>Reset Password</h2>
        <p style="color:#777; margin-bottom:24px; font-size:0.95rem;">Enter your email, phone, and a new password below.</p>

        <?php if (!empty($error_message)): ?>
          <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; text-align: left; font-size: 0.9rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <form action="ForgotPassword.php" method="post" class="login-form">
          <div class="input-group">
            <label for="recovery-email">Email Address*</label>
            <input type="email" id="recovery-email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" />
          </div>
          <div class="input-group">
            <label for="recovery-phone">Phone Number*</label>
            <input type="text" id="recovery-phone" name="phone" placeholder="Enter phone number (e.g. 0300-1234567)" required value="<?php echo htmlspecialchars($phone ?? ''); ?>" />
          </div>
          <div class="input-group">
            <label for="new-pass">New Password*</label>
            <input type="password" id="new-pass" name="new_password" placeholder="Min 6 characters" required />
          </div>
          <div class="input-group">
            <label for="confirm-new-pass">Confirm New Password*</label>
            <input type="password" id="confirm-new-pass" name="confirm_password" placeholder="Re-type new password" required />
          </div>
          <button type="submit" class="login-btn">Update Password</button>
        </form>

        <div class="login-footer">
          <p>Remembered your password? <a href="Login.php">Sign in here</a></p>
        </div>
      </div>
    </main>

    <br class="Line" />
    <footer class="site-footer">
      <div class="footer-column">
        <h3>Quick Links</h3>
        <a href="Homepage.php">Home</a>
        <a href="Products.php">Products</a>
        <a href="AboutUs.php">About Us</a>
        <a href="Contact.php">Contact</a>
      </div>
      <div class="footer-column">
        <h3>Customer Service</h3>
        <a href="FutureUpdate.php">Terms &amp; Services</a>
        <a href="FutureUpdate.php">Delivery / Exchange / Return Policy</a>
        <a href="FutureUpdate.php">Privacy Policy</a>
      </div>
      <div class="footer-column">
        <h3>Newsletter</h3>
        <p>Subscribe to get special offers, free giveaways, and once-in-a-lifetime deals.</p>
        <div class="newsletter-form">
          <input type="email" placeholder="Enter your email" class="newsletter-input" />
          <button class="newsletter-btn">Subscribe</button>
        </div>
      </div>
    </footer>
  </body>
</html>
