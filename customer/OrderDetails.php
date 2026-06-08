<?php
// customer/OrderDetails.php
$page_title = 'Order Details';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: Login.php");
    exit;
}

// Prevent back-button caching for logged-in customer pages
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.


$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($order_id <= 0) {
    header("Location: Orders.php");
    exit;
}

require_once '../admin/db_connect.php';

try {
    // 2. Fetch order details, verifying ownership
    $stmt = $pdo->prepare("SELECT *, DATE_FORMAT(order_date, '%b %d, %Y') AS formatted_date FROM orders WHERE id = ? AND customer_id = ? LIMIT 1");
    $stmt->execute([$order_id, $_SESSION['customer_id']]);
    $order = $stmt->fetch();

    if (!$order) {
        header("Location: Orders.php");
        exit;
    }

    // 3. Fetch order items
    $items_stmt = $pdo->prepare("
        SELECT oi.*, p.name, p.image_path
        FROM order_items oi
        LEFT JOIN products p ON oi.product_id = p.id
        WHERE oi.order_id = ?
    ");
    $items_stmt->execute([$order_id]);
    $order_items = $items_stmt->fetchAll();
} catch (\PDOException $e) {
    die("Database query error: " . $e->getMessage());
}

// Calculate totals from items list
$subtotal = 0;
foreach ($order_items as $item) {
    $subtotal += floatval($item['price']) * intval($item['quantity']);
}

// Estimate shipping & tax for display matching database total
$total_paid = floatval($order['total_amount']);
// We back-calculate the discount and tax based on standard rules
$shipping_cost = ($subtotal >= 5000) ? 0 : 300;
// If the database total is less than subtotal + shipping, there was a promo discount
$difference = ($subtotal + $shipping_cost) - $total_paid;
// Let's make it look clean:
$tax_rate = 0.05;
$calculated_tax = ($total_paid - $shipping_cost) / (1 + $tax_rate) * $tax_rate;
if ($calculated_tax < 0) $calculated_tax = 0;

$estimated_delivery = date('M d, Y', strtotime($order['order_date'] . ' + 5 days'));

// Tracking steps mapping
$status_steps = [
    'Pending' => 1,
    'Processing' => 2,
    'Shipped' => 3,
    'Delivered' => 4,
    'Cancelled' => 0
];
$current_step = $status_steps[$order['status']] ?? 1;

require_once 'header.php';
?>

    <main class="page-main">
      <div class="page-header" style="background-color:#111; color:#fff; padding:60px 20px; text-align:center;">
        <h1>Order Details</h1>
        <p>
          <a href="Orders.php" class="action-link" style="color: #bbb; text-decoration:none; font-weight:bold;"
            >&larr; Back to Orders</a
          >
        </p>
      </div>

      <div class="cart-container" style="display: flex; flex-direction: column; max-width: 1100px; margin: 40px auto 80px; padding: 0 20px;">
        
        <?php if (isset($_GET['success'])): ?>
          <div style="background-color:#e8f5e9; color:#2e7d32; border: 1.5px solid #2e7d32; padding:15px; font-weight:bold; margin-bottom:30px; font-size:0.95rem;">
            <i class="fas fa-check-circle" style="margin-right:8px;"></i>Thank you! Your order has been placed successfully.
          </div>
        <?php endif; ?>

        <div class="order-detail-header" style="display:flex; justify-content:space-between; align-items:center; border-bottom:2px solid #111; padding-bottom:15px; margin-bottom:30px;">
          <div>
            <h2 style="font-size:1.8rem; font-weight:bold;">Order #ORD-<?php echo str_pad($order['id'], 6, '0', STR_PAD_LEFT); ?></h2>
            <p style="color: #777; margin-top: 5px">Placed on <?php echo htmlspecialchars($order['formatted_date']); ?></p>
          </div>
          <div>
            <span style="font-size: 1rem; padding:6px 16px; border-radius:4px; display:inline-block; font-weight:bold;
              <?php
                if ($order['status'] === 'Pending') echo 'background:#ffe699; color:#856404;';
                elseif ($order['status'] === 'Processing') echo 'background:#cce5ff; color:#004085;';
                elseif ($order['status'] === 'Shipped') echo 'background:#d4edda; color:#155724;';
                elseif ($order['status'] === 'Delivered') echo 'background:#d4edda; color:#155724; font-weight:bold;';
                else echo 'background:#f8d7da; color:#721c24;';
              ?>
            ">
              <?php echo htmlspecialchars($order['status']); ?>
            </span>
          </div>
        </div>

        <!-- Single Unified Order Details Box -->
        <div style="background:#fff; border:1.5px solid #111; box-shadow:4px 4px 0px #000; padding:35px; display:flex; flex-direction:column; gap:35px;">
          
          <!-- Section 1: Shipment Tracking Step Progress Bar -->
          <?php if ($order['status'] !== 'Cancelled'): ?>
            <div>
              <h3 style="font-size:1.2rem; font-weight:bold; margin-bottom:25px; color:#111;">Shipment Tracking Status</h3>
              <div style="display:flex; justify-content:space-between; align-items:center; position:relative; margin-bottom:10px;" class="tracking-steps-container">
                
                <!-- Background progress line -->
                <div style="position:absolute; top:20px; left:5%; right:5%; height:4px; background:#ddd; z-index:1;"></div>
                <!-- Highlighted progress line -->
                <div style="position:absolute; top:20px; left:5%; width:<?php echo (($current_step - 1) / 3) * 90; ?>%; height:4px; background:#111; z-index:2; transition: width 0.4s ease;"></div>

                <!-- Step 1: Placed -->
                <div style="text-align:center; z-index:3; width:20%;">
                  <div style="width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:bold; font-size:1.1rem;
                    <?php echo ($current_step >= 1) ? 'background:#111; color:#fff; border:2px solid #111;' : 'background:#fff; color:#999; border:2px solid #ddd;'; ?>">
                    <i class="fas fa-file-invoice"></i>
                  </div>
                  <span style="font-size:0.85rem; font-weight:bold; color:<?php echo ($current_step >= 1) ? '#111' : '#888'; ?>;">Ordered</span>
                </div>

                <!-- Step 2: Processing -->
                <div style="text-align:center; z-index:3; width:20%;">
                  <div style="width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:bold; font-size:1.1rem;
                    <?php echo ($current_step >= 2) ? 'background:#111; color:#fff; border:2px solid #111;' : 'background:#fff; color:#999; border:2px solid #ddd;'; ?>">
                    <i class="fas fa-cog fa-spin" style="<?php echo ($current_step == 2) ? '' : 'animation:none;'; ?>"></i>
                  </div>
                  <span style="font-size:0.85rem; font-weight:bold; color:<?php echo ($current_step >= 2) ? '#111' : '#888'; ?>;">Prepared</span>
                </div>

                <!-- Step 3: Shipped -->
                <div style="text-align:center; z-index:3; width:20%;">
                  <div style="width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:bold; font-size:1.1rem;
                    <?php echo ($current_step >= 3) ? 'background:#111; color:#fff; border:2px solid #111;' : 'background:#fff; color:#999; border:2px solid #ddd;'; ?>">
                    <i class="fas fa-truck"></i>
                  </div>
                  <span style="font-size:0.85rem; font-weight:bold; color:<?php echo ($current_step >= 3) ? '#111' : '#888'; ?>;">In Transit</span>
                </div>

                <!-- Step 4: Delivered -->
                <div style="text-align:center; z-index:3; width:20%;">
                  <div style="width:44px; height:44px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 10px; font-weight:bold; font-size:1.1rem;
                    <?php echo ($current_step >= 4) ? 'background:#2e7d32; color:#fff; border:2px solid #2e7d32;' : 'background:#fff; color:#999; border:2px solid #ddd;'; ?>">
                    <i class="fas fa-home"></i>
                  </div>
                  <span style="font-size:0.85rem; font-weight:bold; color:<?php echo ($current_step >= 4) ? '#2e7d32' : '#888'; ?>;">Delivered</span>
                </div>

              </div>
              
              <div style="margin-top:25px; border-top:1px solid #eee; padding-top:15px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:10px;">
                <span style="font-size:0.92rem; color:#444;">Estimated Delivery Date: <strong><?php echo htmlspecialchars($estimated_delivery); ?></strong></span>
                <span style="font-size:0.92rem; color:#666;">Need Help? <a href="Contact.php" style="color:#111; font-weight:bold; text-decoration:underline;">Contact Customer Support</a></span>
              </div>
            </div>
            <hr style="border:0; border-top:1.5px solid #111; margin-top:30px; margin-bottom:0;" />
          <?php else: ?>
            <div>
              <div style="background-color:#f8d7da; color:#721c24; border: 1.5px solid #721c24; padding:15px; font-weight:bold; font-size:0.95rem;">
                <i class="fas fa-ban" style="margin-right:8px;"></i>This order has been Cancelled. If you have any questions, please contact support.
              </div>
              <hr style="border:0; border-top:1.5px solid #111; margin-top:30px; margin-bottom:0;" />
            </div>
          <?php endif; ?>
 
          <!-- Shipping Information -->
          <div class="info-card" style="display:flex; flex-direction:column; gap:10px;">
            <h3 style="font-size:1.15rem; font-weight:bold; border-bottom:1.5px solid #111; padding-bottom:8px; margin-bottom:5px; color:#111;">Shipping Information</h3>
            <div style="font-size:0.95rem; color:#444; line-height:1.6; white-space:pre-line;">
              <?php echo htmlspecialchars($order['shipping_address']); ?>
            </div>
          </div>
 
          <hr style="border:0; border-top:1.5px solid #111; margin:0;" />
 
          <!-- Payment Summary -->
          <div class="info-card" style="display:flex; flex-direction:column; gap:12px;">
            <h3 style="font-size:1.15rem; font-weight:bold; border-bottom:1.5px solid #111; padding-bottom:8px; margin-bottom:5px; color:#111;">Payment Summary</h3>
            <p style="font-size:0.95rem;">Payment Method: <strong><?php echo htmlspecialchars($order['payment_method']); ?></strong></p>
            
            <div style="border-top:1px dashed #ddd; padding-top:12px; display:flex; flex-direction:column; gap:8px;">
              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.9rem; color:#555;">
                <span>Subtotal:</span><span>Rs. <?php echo number_format($subtotal); ?></span>
              </div>
              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.9rem; color:#555;">
                <span>Shipping:</span><span><?php echo ($shipping_cost == 0) ? 'FREE' : 'Rs. ' . number_format($shipping_cost); ?></span>
              </div>
              <?php if ($difference > 0): ?>
                <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.9rem; color:#2e7d32; font-weight:bold;">
                  <span>Promo/Adjustments:</span><span>- Rs. <?php echo number_format($difference); ?></span>
                </div>
              <?php endif; ?>
              <div class="summary-row total" style="display:flex; justify-content:space-between; font-size:1.15rem; font-weight:bold; border-top:1px solid #111; padding-top:10px; color:#111; margin-bottom:0;">
                <span>Total Amount paid:</span><span>Rs. <?php echo number_format($total_paid); ?></span>
              </div>
            </div>
          </div>

          <hr style="border:0; border-top:1.5px solid #111; margin:0;" />

          <!-- Ordered Items Grid -->
          <div class="cart-items">
            <h3 style="font-size:1.3rem; font-weight:bold; border-bottom:2px solid #111; padding-bottom:8px; margin-bottom:20px; color:#111;">Items Ordered</h3>
            <div style="display:flex; flex-direction:column; gap:20px;">
              <?php foreach ($order_items as $item): ?>
                <div class="cart-item" style="display:flex; align-items:center; justify-content:space-between; gap:20px; border-bottom:1px solid #eee; padding-bottom:15px;">
                  <img src="<?php echo htmlspecialchars($item['image_path'] ?? '../images/Designs/Half Sleeve/6_1f55ee26-900e-4ff3-90e9-547809ca2fc6.webp'); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width:70px; height:90px; object-fit:cover; border:1px solid #ddd;" />
                  <div class="cart-item-details" style="flex-grow:1; margin-left:10px;">
                    <h3 style="font-size:1.1rem; font-weight:bold; color:#111;"><?php echo htmlspecialchars($item['name'] ?? 'Product Deleted'); ?></h3>
                    <p style="color:#666; font-size:0.88rem; margin-top:2px;">Size: <strong><?php echo htmlspecialchars($item['size']); ?></strong></p>
                    <p style="color:#888; font-size:0.88rem;">Qty: <?php echo $item['quantity']; ?> x Rs. <?php echo number_format($item['price']); ?></p>
                  </div>
                  <div class="cart-item-price" style="font-weight:bold; font-size:1.1rem; color:#111;">
                    Rs. <?php echo number_format(floatval($item['price']) * intval($item['quantity'])); ?>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>

        </div> 
      </div>
    </main>

<?php require_once 'footer.php'; ?>
