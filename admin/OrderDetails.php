<?php
require_once 'auth.php';
check_admin_login();
require_once 'db_connect.php';

$order_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($order_id <= 0) {
    header("Location: AdminOrders.php");
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT o.id, o.order_date, o.status, o.total_amount, o.shipping_address, o.payment_method,
                            DATE_FORMAT(o.order_date, '%b %d, %Y') AS formatted_date,
                            c.name AS customer_name, c.email AS customer_email, c.phone AS customer_phone, c.city AS customer_city
                            FROM orders o
                            JOIN customers c ON o.customer_id = c.id
                            WHERE o.id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();

    if (!$order) {
        die("<h3>Order #ORD-{$order_id} not found.</h3><p><a href='AdminOrders.php'>Back to Orders</a></p>");
    }

    $stmt = $pdo->prepare("SELECT oi.quantity, oi.price, (oi.quantity * oi.price) AS subtotal,
                            p.name AS product_name, p.image_path
                            FROM order_items oi
                            LEFT JOIN products p ON oi.product_id = p.id
                            WHERE oi.order_id = ?");
    $stmt->execute([$order_id]);
    $items = $stmt->fetchAll();

} catch (\PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Calculate totals
$subtotal = 0;
foreach ($items as $item) {
    $subtotal += (float)$item['subtotal'];
}
$shipping = 200.00; // Fixed shipping cost as per the design
$grand_total = $subtotal + $shipping;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NOCTURNE - Order Details (Admin)</title>
  <link href="../font-awesome/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="../style.css">
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
          <h1>Order Details</h1>
        </div>
        <a href="AdminOrders.php" style="color:#fca311; font-weight:700; text-decoration:none;">
          <i class="fas fa-arrow-left" style="margin-right:4px;"></i>Back to Orders
        </a>
      </header>

      <main class="admin-content">

        <!-- Order Header -->
        <div class="admin-table-container" style="padding:24px; margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
          <div>
            <h2 style="font-size:1.4rem; text-transform:uppercase; letter-spacing:1px; margin:0;">Order #ORD-<?php echo htmlspecialchars($order['id']); ?></h2>
            <p style="color:#777; margin-top:5px;">Placed on <?php echo htmlspecialchars($order['formatted_date']); ?></p>
          </div>
          <?php 
            $badge_class = 'status-pending';
            if ($order['status'] === 'Delivered') $badge_class = 'status-active';
            elseif ($order['status'] === 'Shipped') $badge_class = 'status-shipped';
            elseif ($order['status'] === 'Cancelled') $badge_class = 'status-cancelled';
          ?>
          <span class="status-badge <?php echo $badge_class; ?>" style="font-size:1rem;"><?php echo htmlspecialchars($order['status']); ?></span>
        </div>

        <!-- Info Cards -->
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px; margin-bottom:20px;">
          <div class="admin-table-container" style="padding:24px;">
            <h3 style="margin-bottom:12px; font-size:1rem; border-bottom: 2px solid #eee; padding-bottom: 8px;">Customer Info</h3>
            <p style="color:#111; margin-bottom: 6px;"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
            <p style="color:#555; margin-bottom: 6px;"><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
            <p style="color:#555; margin-bottom: 6px;"><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
            <p style="color:#555; margin-bottom: 6px;"><strong>Shipping Address:</strong><br><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            <p style="color:#555; margin-bottom: 6px;"><strong>City:</strong> <?php echo htmlspecialchars($order['customer_city']); ?></p>
          </div>
          <div class="admin-table-container" style="padding:24px;">
            <h3 style="margin-bottom:12px; font-size:1rem; border-bottom: 2px solid #eee; padding-bottom: 8px;">Payment Info</h3>
            <p style="margin-bottom: 8px;"><strong>Status:</strong> 
              <?php if ($order['status'] === 'Delivered'): ?>
                <span style="color:#2e7d32; font-weight:700;">Paid</span>
              <?php elseif ($order['status'] === 'Cancelled'): ?>
                <span style="color:#c62828; font-weight:700;">Refunded/Void</span>
              <?php else: ?>
                <span style="color:#ef6c00; font-weight:700;">Pending Payment</span>
              <?php endif; ?>
            </p>
            <p style="color:#555; margin-bottom: 12px;"><strong>Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
            <div style="margin-top:15px; border-top:1px solid #eee; padding-top:15px;">
              <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#666;">Subtotal:</span><span>Rs. <?php echo number_format($subtotal); ?></span></div>
              <div style="display:flex; justify-content:space-between; margin-bottom:6px;"><span style="color:#666;">Shipping:</span><span>Rs. <?php echo number_format($shipping); ?></span></div>
              <div style="display:flex; justify-content:space-between; font-weight:700; font-size:1.1rem; margin-top:8px; border-top:1px dashed #ddd; padding-top:8px;"><span>Total:</span><span>Rs. <?php echo number_format($order['total_amount']); ?></span></div>
            </div>
          </div>
        </div>

        <!-- Items Ordered -->
        <h3 style="margin-bottom:12px; font-size:1rem; text-transform: uppercase; letter-spacing: 0.5px;">Items Ordered</h3>
        <div class="admin-table-container">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Price</th>
                <th>Subtotal</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($items as $item): ?>
                <tr>
                  <td style="display:flex; align-items:center; gap:10px;">
                    <?php if (!empty($item['image_path'])): ?>
                      <img src="<?php echo htmlspecialchars($item['image_path']); ?>" style="width:40px; height:40px; border-radius:6px; object-fit:cover; border: 1px solid #ddd;" alt="<?php echo htmlspecialchars($item['product_name'] ?? 'Product'); ?>" />
                    <?php else: ?>
                      <div style="width:40px; height:40px; border-radius:6px; background:#eee; display:flex; align-items:center; justify-content:center;"><i class="fas fa-box" style="color:#aaa;"></i></div>
                    <?php endif; ?>
                    <?php echo htmlspecialchars($item['product_name'] ?? '[Deleted Product]'); ?>
                  </td>
                  <td><?php echo (int)$item['quantity']; ?></td>
                  <td>Rs. <?php echo number_format((float)$item['price']); ?></td>
                  <td>Rs. <?php echo number_format((float)$item['subtotal']); ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

      </main>
    </div>
  </div>

</body>
</html>
