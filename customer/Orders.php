<?php
// customer/Orders.php
$page_title = 'My Orders';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: Login.php?message=Please+sign+in+to+view+your+orders.");
    exit;
}

// Prevent back-button caching for logged-in customer pages
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.

require_once '../admin/db_connect.php';


try {
    // 2. Fetch customer orders
    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(order_date, '%b %d, %Y') AS formatted_date FROM orders WHERE customer_id = ? ORDER BY id DESC");
    $stmt->execute([$_SESSION['customer_id']]);
    $orders = $stmt->fetchAll();
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Inline badge class generator
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Pending': return 'background-color:#ffe699; color:#856404; border:1px solid #ffe699;';
        case 'Processing': return 'background-color:#cce5ff; color:#004085; border:1px solid #cce5ff;';
        case 'Shipped': return 'background-color:#d4edda; color:#155724; border:1px solid #d4edda;';
        case 'Delivered': return 'background-color:#d4edda; color:#155724; border:1px solid #d4edda; font-weight:bold;';
        case 'Cancelled': return 'background-color:#f8d7da; color:#721c24; border:1px solid #f8d7da;';
        default: return 'background-color:#eee; color:#333;';
    }
}

require_once 'header.php';
?>

    <main class="page-main">
      <div class="page-header" style="background-color:#111; color:#fff; padding:60px 20px; text-align:center;">
        <h1>Your Orders</h1>
        <p>View your past purchases and their status.</p>
      </div>

      <div class="orders-container" style="max-width:1100px; margin: 60px auto; padding: 0 20px;">
        <?php if (count($orders) > 0): ?>
          <table class="orders-table" style="width:100%; border-collapse:collapse; background:#fff; border: 1.5px solid #111; box-shadow: 6px 6px 0px #000; text-align:left;">
            <thead>
              <tr style="border-bottom: 2px solid #111; background:#fcfbf0; font-weight:bold;">
                <th style="padding:15px;">Order ID</th>
                <th style="padding:15px;">Date</th>
                <th style="padding:15px;">Status</th>
                <th style="padding:15px;">Total Amount</th>
                <th style="padding:15px; text-align:center;">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($orders as $order): ?>
                <tr style="border-bottom:1px solid #eee;">
                  <td style="padding:15px;"><strong>#ORD-<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                  <td style="padding:15px;"><?php echo htmlspecialchars($order['formatted_date']); ?></td>
                  <td style="padding:15px;">
                    <span style="padding:5px 12px; border-radius:4px; font-size:0.85rem; display:inline-block; <?php echo getStatusBadgeClass($order['status']); ?>">
                      <?php echo htmlspecialchars($order['status']); ?>
                    </span>
                  </td>
                  <td style="padding:15px; font-weight:bold;">Rs. <?php echo number_format($order['total_amount']); ?></td>
                  <td style="padding:15px; text-align:center; display:flex; justify-content:center; gap:10px; align-items:center;">
                    <a href="OrderDetails.php?id=<?php echo $order['id']; ?>" class="login-btn" style="text-decoration:none; font-size:0.82rem; padding:8px 14px; margin:0; border-radius:0;">View Details</a>
                    <a href="reorder.php?id=<?php echo $order['id']; ?>" class="login-btn" style="text-decoration:none; font-size:0.82rem; padding:8px 14px; margin:0; border-radius:0; background:#fca311; color:#000; border:1px solid #fca311;">Reorder</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div style="text-align:center; padding:60px 20px; border:1.5px dashed #ccc; background:#fff;">
            <i class="fas fa-shopping-bag" style="font-size:3rem; color:#aaa; margin-bottom:15px; display:block;"></i>
            <h3 style="color:#555;">No orders found</h3>
            <p style="color:#777; margin-bottom:20px;">You haven't placed any orders yet.</p>
            <a href="Products.php" class="login-btn" style="text-decoration:none; display:inline-block; padding:10px 25px; border-radius:0;">Browse Catalog</a>
          </div>
        <?php endif; ?>
      </div>
    </main>

<?php require_once 'footer.php'; ?>
