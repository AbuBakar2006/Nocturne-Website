<?php
// sidebar.php
// Reusable admin sidebar with dynamic active states and permission checks.
$current_page = basename($_SERVER['PHP_SELF']);
?>
<style>
.admin-sidebar ul li a {
    padding: 16px 20px !important;
}
@media (max-height: 720px) {
    .admin-sidebar ul li a {
        padding: 12px 18px !important;
    }
}
</style>

<aside class="admin-sidebar">

  <div class="sidebar-logo">
    <img src="../images/nocturne_logo.png" alt="Logo">
    <h2 style="margin-top: 15px; font-size: 1.2rem; letter-spacing: 2px;padding-bottom:50px">ADMIN PANEL</h2>
  </div>
  <ul>
    <li class="<?php echo ($current_page === 'AdminDashboard.php') ? 'active' : ''; ?>">
      <a href="AdminDashboard.php"><i class="fas fa-th-large"></i><span>DASHBOARD</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminOrders.php' || $current_page === 'OrderDetails.php') ? 'active' : ''; ?>">
      <a href="AdminOrders.php"><i class="fas fa-shopping-bag"></i><span>ORDERS</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminCategories.php') ? 'active' : ''; ?>">
      <a href="AdminCategories.php"><i class="fas fa-tags"></i><span>CATEGORIES</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminProducts.php') ? 'active' : ''; ?>">
      <a href="AdminProducts.php"><i class="fas fa-box-open"></i><span>PRODUCTS</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminCustomers.php') ? 'active' : ''; ?>">
      <a href="AdminCustomers.php"><i class="fas fa-users"></i><span>CUSTOMERS</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminAnalytics.php') ? 'active' : ''; ?>">
      <a href="AdminAnalytics.php"><i class="fas fa-chart-line"></i><span>ANALYTICS</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminReviews.php') ? 'active' : ''; ?>">
      <a href="AdminReviews.php"><i class="fas fa-star"></i><span>REVIEWS</span></a>
    </li>
    <li class="<?php echo ($current_page === 'AdminProfile.php') ? 'active' : ''; ?>">
      <a href="AdminProfile.php"><i class="fas fa-user-cog"></i><span>PROFILE</span></a>
    </li>
    
    <!-- Conditional SuperAdmin Management Link -->
    <?php if (isset($_SESSION['admin_role']) && $_SESSION['admin_role'] === 'SuperAdmin'): ?>
    <li class="<?php echo ($current_page === 'AdminStaff.php') ? 'active' : ''; ?>">
      <a href="AdminStaff.php"><i class="fas fa-user-shield"></i><span>ADMINS</span></a>
    </li>
    <?php endif; ?>

    <li style="margin-top: 15px; border-top: 1px solid #222;">
      <a href="../customer/Homepage.php" target="_blank"><i class="fas fa-store"></i><span>VIEW STORE</span></a>
    </li>
    <li>
      <a href="AdminLogin.php?logout=true"><i class="fas fa-sign-out-alt"></i><span>LOGOUT</span></a>
    </li>
  </ul>
</aside>

<script>
  // Sync admin login status in localStorage
  <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
      if (localStorage.getItem('admin_logged_in') !== 'true') {
          localStorage.setItem('admin_logged_in', 'true');
      }
  <?php else: ?>
      if (localStorage.getItem('admin_logged_in') === 'true') {
          localStorage.setItem('admin_logged_in', 'false');
      }
  <?php endif; ?>

  // Listen for logout in other tabs
  window.addEventListener('storage', function(event) {
      if (event.key === 'admin_logged_in' && event.newValue === 'false') {
          window.location.reload();
      }
  });

  // Listen for focus to check if logged out in another tab
  window.addEventListener('focus', function() {
      <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
          if (localStorage.getItem('admin_logged_in') === 'false') {
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

