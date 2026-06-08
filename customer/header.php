<?php
// customer/header.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../admin/db_connect.php';

// Calculate cart count
$cart_count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += intval($item['quantity'] ?? 0);
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?php echo isset($page_title) ? htmlspecialchars($page_title) . " - Nocturne" : "NOCTURNE"; ?></title>
    <link rel="stylesheet" href="../style.css" />
    <link href="../font-awesome/css/all.min.css" rel="stylesheet">
    <style>
      /* Dropdown styling for user account */
      .user-dropdown {
          position: relative;
          display: inline-block;
      }
      .user-dropdown-content {
          display: none;
          position: absolute;
          right: 0;
          background-color: #000;
          min-width: 160px;
          box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.2);
          z-index: 1100;
          border: 1px solid #333;
          border-radius: 4px;
      }
      .user-dropdown:hover .user-dropdown-content {
          display: block;
      }
      .user-dropdown-content a {
          color: white;
          padding: 12px 16px;
          text-decoration: none;
          display: block;
          font-size: 0.95rem;
          text-align: left;
      }
      .user-dropdown-content a:hover {
          background-color: #222;
          color: #fca311;
      }
      .search-bar-wrapper {
          position: relative;
          display: inline-block;
          vertical-align: middle;
      }
      .search-bar-form {
          display: flex;
          align-items: center;
          border: 1px solid transparent;
          border-radius: 4px;
          padding: 2px;
          background: transparent;
          transition: all 0.3s ease;
      }
      .search-bar-form.expanded {
          background: #fff;
          border-color: #111;
      }
      .search-bar-input {
          width: 0;
          opacity: 0;
          padding: 0;
          border: none;
          outline: none;
          font-size: 0.9rem;
          transition: all 0.3s ease;
          background: transparent !important;
          color: #333;
      }
      .search-bar-form.expanded .search-bar-input {
          width: 160px;
          opacity: 1;
          padding: 4px 8px;
      }
      .search-bar-btn {
          background: none;
          border: none;
          cursor: pointer;
          color: #fff;
          font-size: 1.1rem;
          display: flex;
          align-items: center;
          justify-content: center;
          padding: 6px;
          transition: color 0.3s;
      }
      .search-bar-form.expanded .search-bar-btn {
          color: #111;
      }
      .cart-badge-container {
          position: relative;
          display: inline-block;
      }
      .cart-badge {
          position: absolute;
          top: -8px;
          right: -8px;
          background-color: #fca311;
          color: black;
          border-radius: 50%;
          padding: 2px 6px;
          font-size: 0.75rem;
          font-weight: bold;
      }
      /* Responsive header elements */
      @media (max-width: 768px) {
          header ul {
              flex-direction: column;
              padding: 15px 10px;
              gap: 15px;
          }
          header ul li {
              flex-wrap: wrap;
              justify-content: center !important;
              gap: 15px;
          }
          .search-bar-wrapper {
              margin-top: 5px;
          }
      }
    </style>
  </head>
  <body>
    <header>
      <ul>
        <li class="header-brand">
          <a href="Homepage.php" style="display:flex; align-items:center; gap:12px; text-decoration:none;">
            <img src="../images/nocturne_logo.png" class="header-logo" />
            <span class="header-brand-name">Nocturne</span>
          </a>
        </li>
        <li>
          <a href="Homepage.php">HOME</a>
          <a href="Products.php">PRODUCTS</a>
          <a href="Contact.php">CONTACT</a>
          <a href="AboutUs.php">ABOUT US</a>
        </li>

        <li>
          <!-- Expanding search bar in right header panel -->
          <div class="search-bar-wrapper">
            <form action="Products.php" method="get" class="search-bar-form" id="headerSearchForm">
              <input type="text" name="search" id="headerSearchInput" placeholder="Search products..." class="search-bar-input" value="<?php echo htmlspecialchars($search_query ?? ''); ?>" />
              <button type="button" id="headerSearchBtn" class="search-bar-btn" onclick="handleSearchClick(event)">
                <i class="fas fa-search"></i>
              </button>
            </form>
          </div>

          <?php if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
            <div class="user-dropdown">
              <a href="Profile.php" style="cursor:pointer; display:flex; align-items:center; gap:8px;">
                <img src="../images/user.svg" class="header-icon" style="display:inline-block;" />
                <span style="font-size:0.9rem; font-weight:600; text-transform:uppercase; color:#fff;"><?php echo htmlspecialchars(explode(' ', trim($_SESSION['customer_name'] ?? 'User'))[0]); ?></span>
              </a>
              <div class="user-dropdown-content">
                <a href="Profile.php"><i class="fas fa-user-circle" style="margin-right:8px;"></i>My Profile</a>
                <a href="Orders.php"><i class="fas fa-shopping-bag" style="margin-right:8px;"></i>My Orders</a>
                <a href="Login.php?logout=true"><i class="fas fa-sign-out-alt" style="margin-right:8px;"></i>Sign Out</a>
              </div>
            </div>
          <?php else: ?>
            <a href="Login.php">
              <img src="../images/user.svg" class="header-icon" />
            </a>
          <?php endif; ?>
          
          <a href="Cart.php" class="cart-badge-container">
            <img src="../images/shopping-cart.svg" class="header-icon" />
            <?php if ($cart_count > 0): ?>
              <span class="cart-badge"><?php echo $cart_count; ?></span>
            <?php endif; ?>
          </a>
        </li>
      </ul>
    </header>

    <script>
      function handleSearchClick(event) {
          const form = document.getElementById('headerSearchForm');
          const input = document.getElementById('headerSearchInput');
          
          if (!form.classList.contains('expanded')) {
              event.preventDefault();
              form.classList.add('expanded');
              input.focus();
          } else {
              if (input.value.trim() === '') {
                  event.preventDefault();
                  form.classList.remove('expanded');
              } else {
                  form.submit();
              }
          }
      }

      // Collapse search bar if clicked outside
      document.addEventListener('click', function(event) {
          const form = document.getElementById('headerSearchForm');
          const input = document.getElementById('headerSearchInput');
          const wrapper = document.querySelector('.search-bar-wrapper');
          if (form && form.classList.contains('expanded') && !wrapper.contains(event.target)) {
              if (input.value.trim() === '') {
                  form.classList.remove('expanded');
              }
          }
      });

      // Sync customer login status in localStorage
      <?php if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
          if (localStorage.getItem('customer_logged_in') !== 'true') {
              localStorage.setItem('customer_logged_in', 'true');
          }
      <?php else: ?>
          if (localStorage.getItem('customer_logged_in') === 'true') {
              localStorage.setItem('customer_logged_in', 'false');
          }
      <?php endif; ?>

      // Listen for logout in other tabs
      window.addEventListener('storage', function(event) {
          if (event.key === 'customer_logged_in' && event.newValue === 'false') {
              window.location.reload();
          }
      });

      // Listen for focus to check if logged out in another tab
      window.addEventListener('focus', function() {
          <?php if (isset($_SESSION['customer_logged_in']) && $_SESSION['customer_logged_in'] === true): ?>
              if (localStorage.getItem('customer_logged_in') === 'false') {
                  window.location.reload();
              }
          <?php endif; ?>
      });

      // Force reload on back-forward cache show (Alt + Left Arrow check)
      window.addEventListener('pageshow', function(event) {
          if (event.persisted) {
              window.location.reload();
          }
      });
    </script>

