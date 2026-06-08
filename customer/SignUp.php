<?php
// customer/SignUp.php
// SignUp page for NOCTURNE Customers.

require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if already logged in as customer
if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true) {
    header("Location: Homepage.php");
    exit;
}

$error_message = '';

// Handle Registration POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $city = trim($_POST['city'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($name) || empty($email) || empty($phone) || empty($city) || empty($password)) {
        $error_message = "All fields are required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = "Invalid email format.";
    } elseif (strlen($password) < 6) {
        $error_message = "Password must be at least 6 characters long.";
    } elseif ($password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } else {
        try {
            // Check if email already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM customers WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "An account with this email address already exists.";
            } else {
                // Insert new customer record
                $hashed_pass = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO customers (name, email, phone, city, password, joined_date) VALUES (?, ?, ?, ?, ?, CURDATE())");
                $stmt->execute([$name, $email, $phone, $city, $hashed_pass]);
                
                header("Location: Login.php?message=Account+created+successfully!+Please+log+in.");
                exit;
            }
        } catch (\PDOException $e) {
            $error_message = "Registration failed: " . $e->getMessage();
        }
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>NOCTURNE - Sign Up</title>
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
        <h2>Create an Account</h2>
        <p style="color:#777; margin-bottom:24px; font-size:0.95rem;">Please enter your details to sign up.</p>

        <?php if (!empty($error_message)): ?>
          <div style="background-color: #ffebee; color: #c62828; border: 1.5px solid #c62828; padding: 12px; margin-bottom: 20px; font-weight: bold; border-radius: 4px; text-align: left; font-size: 0.9rem;">
            <i class="fas fa-exclamation-triangle" style="margin-right: 8px;"></i><?php echo htmlspecialchars($error_message); ?>
          </div>
        <?php endif; ?>

        <form action="SignUp.php" method="post" class="login-form">
          <div class="input-group">
            <label for="reg-name">Full Name*</label>
            <input type="text" id="reg-name" name="name" placeholder="Enter your full name" required value="<?php echo htmlspecialchars($name ?? ''); ?>" />
          </div>
          <div class="input-group">
            <label for="reg-email">Email Address*</label>
            <input type="email" id="reg-email" name="email" placeholder="Enter your email" required value="<?php echo htmlspecialchars($email ?? ''); ?>" />
          </div>
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
            <div class="input-group">
              <label for="reg-phone">Phone Number*</label>
              <input type="text" id="reg-phone" name="phone" placeholder="e.g. 0300-1234567" required value="<?php echo htmlspecialchars($phone ?? ''); ?>" />
            </div>
            <div class="input-group">
              <label for="reg-city">City*</label>
              <input type="text" id="reg-city" name="city" placeholder="e.g. Lahore" required value="<?php echo htmlspecialchars($city ?? ''); ?>" />
            </div>
          </div>
          <div class="input-group">
            <label for="reg-password">Password*</label>
            <input type="password" id="reg-password" name="password" placeholder="Create a password (min 6 chars)" required />
          </div>
          <div class="input-group">
            <label for="confirm-password">Confirm Password*</label>
            <input type="password" id="confirm-password" name="confirm_password" placeholder="Confirm your password" required />
          </div>
          <button type="submit" class="login-btn">Sign Up</button>
        </form>
        <div class="login-footer">
          <p>Already have an account? <a href="Login.php">Sign in here</a></p>
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
          <input
            type="email"
            placeholder="Enter your email"
            class="newsletter-input"
          />
          <button class="newsletter-btn">Subscribe</button>
        </div>
      </div>
    </footer>
  </body>
</html>
