<?php
// customer/Login.php
// Login page for NOCTURNE Customers.

require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle Logout
if (isset($_GET['logout'])) {
    unset($_SESSION['customer_logged_in']);
    unset($_SESSION['customer_id']);
    unset($_SESSION['customer_name']);
    unset($_SESSION['customer_email']);
    header("Location: Login.php?message=Logged+out+successfully");
    exit;
}

// Redirect if already logged in as customer
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header("Location: Homepage.php");
    exit;
}

$error_message = '';
$success_message = '';

if (isset($_GET['message'])) {
    $success_message = $_GET['message'];
}

// Handle POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!empty($email) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM customers WHERE email = :email LIMIT 1");
            $stmt->execute(['email' => $email]);
            $customer = $stmt->fetch();

            if ($customer && password_verify($password, $customer['password'])) {
                // Set Customer Session variables
                $_SESSION['customer_logged_in'] = true;
                $_SESSION['customer_id'] = $customer['id'];
                $_SESSION['customer_name'] = $customer['name'];
                $_SESSION['customer_email'] = $customer['email'];

                header("Location: Homepage.php");
                exit;
            } else {
                $error_message = "Invalid email or password credentials.";
            }
        } catch (\PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    } else {
        $error_message = "Please fill in all fields.";
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NOCTURNE - Login</title>
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
        <h2>Welcome Back</h2>
        <p style="color:#777; margin-bottom:24px; font-size:0.95rem;">Please enter your details to sign in.</p>

        <?php if (!empty($error_message)): ?>
          <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; text-align: left; font-size: 0.9rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
          <div style="background-color: #e8f5e9; color: #2e7d32; border: 1.5px solid #2e7d32; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; text-align: left; font-size: 0.9rem;">
            <i class="fas fa-check-circle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($success_message); ?>
          </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
          <div style="text-align: center; margin-bottom: 20px;">
            <p>You are logged in as <strong><?php echo htmlspecialchars($_SESSION['customer_name']); ?></strong>.</p>
            <a href="Login.php?logout=true" class="login-btn" style="display:inline-block; text-decoration:none; margin-top:10px;">Sign Out</a>
          </div>
        <?php else: ?>
          <form action="Login.php" method="post" class="login-form">
            <div class="input-group">
              <label for="customer-email">Email</label>
              <input type="email" id="customer-email" name="email" placeholder="Enter your email" required />
            </div>
            <div class="input-group">
              <label for="customer-pass">Password</label>
              <input type="password" id="customer-pass" name="password" placeholder="Enter your password" required />
            </div>
            <div class="login-options">
              <label class="remember-me">
                <input type="checkbox" /> Remember me
              </label>
              <a href="ForgotPassword.php" class="forgot-password">Forgot Password?</a>
            </div>
            <button type="submit" class="login-btn">Sign In</button>
          </form>
        <?php endif; ?>

        <div class="login-footer">
          <p>Don't have an account? <a href="SignUp.php">Sign up here</a></p>
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
        <p>
          Subscribe to get special offers, free giveaways, and
          once-in-a-lifetime deals.
        </p>
        <div class="newsletter-form">
          <input type="email" placeholder="Enter your email" class="newsletter-input" />
          <button class="newsletter-btn">Subscribe</button>
        </div>
    </footer>
    <script>
        localStorage.setItem('customer_logged_in', '<?php echo (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) ? 'true' : 'false'; ?>');
    </script>
  </body>
</html>

