<?php
// customer/Cart.php
$page_title = 'Shopping Cart';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$promo_error = '';
$promo_success = '';

// Handle Promo Code Entry
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_promo'])) {
    $entered_code = strtoupper(trim($_POST['promo_code'] ?? ''));
    if ($entered_code === 'FLASHSALE') {
        $_SESSION['promo_code'] = 'FLASHSALE';
        $_SESSION['promo_discount_percent'] = 30;
        $promo_success = "Promo code FLASHSALE applied! 30% discount has been subtracted from your subtotal.";
    } elseif ($entered_code === 'WELCOME10') {
        $_SESSION['promo_code'] = 'WELCOME10';
        $_SESSION['promo_discount_percent'] = 30;
        $promo_success = "Promo code WELCOME10 applied! 30% discount has been subtracted from your subtotal.";
    } else {
        $promo_error = "Invalid promotional code.";
    }
}

// Handle Remove Promo Code
if (isset($_GET['remove_promo'])) {
    unset($_SESSION['promo_code']);
    unset($_SESSION['promo_discount_percent']);
    header("Location: Cart.php");
    exit;
}

require_once 'header.php';

// Prepare cart calculations
$cart_items = $_SESSION['cart'] ?? [];
$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += floatval($item['price']) * intval($item['quantity']);
}

// Proactively update active session promo codes to 30%
if (isset($_SESSION['promo_code']) && ($_SESSION['promo_code'] === 'FLASHSALE' || $_SESSION['promo_code'] === 'WELCOME10')) {
    $_SESSION['promo_discount_percent'] = 30;
}

$discount_percent = $_SESSION['promo_discount_percent'] ?? 0;
$discount_amount = $subtotal * ($discount_percent / 100);
$discounted_subtotal = $subtotal - $discount_amount;

// Shipping charge: Rs. 300, free shipping for orders above Rs. 5000 after discount
$shipping = (count($cart_items) > 0) ? (($discounted_subtotal >= 5000) ? 0 : 300) : 0;

// Tax: 5%
$tax = $discounted_subtotal * 0.05;

$grand_total = $discounted_subtotal + $shipping + $tax;

// Store calculations in session for Checkout page
$_SESSION['order_totals'] = [
    'subtotal' => $subtotal,
    'discount_percent' => $discount_percent,
    'discount_amount' => $discount_amount,
    'shipping' => $shipping,
    'tax' => $tax,
    'grand_total' => $grand_total
];
?>

    <main class="page-main">
      <div class="page-header" style="background-color:#111; color:#fff; padding:60px 20px; text-align:center;">
        <h1>Shopping Cart</h1>
        <p>Review your items before checkout.</p>
      </div>

      <div class="cart-container" style="max-width:1200px; margin: 40px auto; padding: 0 20px; display: block;">
        <?php if (count($cart_items) > 0): ?>
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px; align-items: start;" class="cart-layout-grid">
            
            <!-- Cart Items List -->
            <div class="cart-items" style="display:flex; flex-direction:column; gap:20px;">
              <?php foreach ($cart_items as $key => $item): 
                $item_subtotal = floatval($item['price']) * intval($item['quantity']);
              ?>
                <div class="cart-item" style="display:flex; align-items:center; justify-content:space-between; gap:20px; background:#fff; border: 1.5px solid #111; box-shadow: 4px 4px 0px #000; padding:20px;">
                  <img src="<?php echo htmlspecialchars($item['image_path']); ?>" alt="<?php echo htmlspecialchars($item['name']); ?>" style="width:80px; height:100px; object-fit:cover; border:1px solid #ddd;" />
                  
                  <div class="cart-item-details" style="flex-grow:1; margin-left:10px;">
                    <h3 style="font-size:1.15rem; font-weight:bold; margin-bottom:5px; color:#111;"><?php echo htmlspecialchars($item['name']); ?></h3>
                    <p style="color:#666; font-size:0.9rem; margin-bottom:2px;">Size: <strong><?php echo htmlspecialchars($item['size']); ?></strong></p>
                    <p style="color:#888; font-size:0.9rem;">Unit Price: Rs. <?php echo number_format($item['price']); ?></p>
                  </div>

                  <div class="cart-item-price" style="font-weight:bold; font-size:1.05rem; color:#111;">
                    Rs. <?php echo number_format($item_subtotal); ?>
                  </div>

                  <!-- Quantity Update Form -->
                  <div class="cart-item-qty">
                    <form action="cart_action.php" method="post" style="display:inline-block;">
                      <input type="hidden" name="action" value="update" />
                      <input type="hidden" name="cart_key" value="<?php echo $key; ?>" />
                      <div style="display:flex; align-items:center; border:1px solid #111;">
                        <input type="number" name="quantity" value="<?php echo $item['quantity']; ?>" min="1" onchange="this.form.submit()" style="width: 55px; border:none; text-align:center; padding:8px; font-weight:bold; outline:none; background:#fcfbf0;" />
                      </div>
                    </form>
                  </div>

                  <a href="cart_action.php?action=remove&cart_key=<?php echo urlencode($key); ?>" class="action-link" style="color:coral; font-weight:bold; font-size:0.9rem; text-decoration:underline;">Remove</a>
                </div>
              <?php endforeach; ?>

              <!-- Promo Code Area -->
              <div style="background:#fff; border:1.5px solid #111; box-shadow:4px 4px 0px #000; padding:20px; margin-top:10px;">
                <h3 style="font-size:1.1rem; font-weight:bold; margin-bottom:10px;">Promotional Code</h3>
                <?php if (!empty($promo_success)): ?>
                  <div style="background-color:#e8f5e9; color:#2e7d32; border: 1.5px solid #2e7d32; padding:10px; font-size:0.85rem; margin-bottom:15px; font-weight:bold;">
                    <i class="fas fa-check-circle" style="margin-right:5px;"></i><?php echo $promo_success; ?>
                  </div>
                <?php endif; ?>
                <?php if (!empty($promo_error)): ?>
                  <div style="background-color:#ffebee; color:#c62828; border: 1.5px solid #c62828; padding:10px; font-size:0.85rem; margin-bottom:15px; font-weight:bold;">
                    <i class="fas fa-exclamation-triangle" style="margin-right:5px;"></i><?php echo $promo_error; ?>
                  </div>
                <?php endif; ?>

                <?php if ($discount_percent > 0): ?>
                  <div style="display:flex; justify-content:space-between; align-items:center;">
                    <span style="font-size:0.95rem; color:#444;">Code Applied: <strong style="color:#2e7d32;"><?php echo htmlspecialchars($_SESSION['promo_code']); ?></strong> (<?php echo $discount_percent; ?>% discount)</span>
                    <a href="Cart.php?remove_promo=1" style="color:coral; font-size:0.88rem; font-weight:bold; text-decoration:underline;">Remove Promo Code</a>
                  </div>
                <?php else: ?>
                  <form action="Cart.php" method="post" style="display:flex; gap:10px; max-width:400px;">
                    <input type="text" name="promo_code" placeholder="Enter coupon code (e.g. FLASHSALE)" required style="flex-grow:1; padding:10px; border:1px solid #ccc; font-size:0.9rem; background:#fcfbf0; outline:none;" />
                    <button type="submit" name="apply_promo" class="login-btn" style="padding:10px 18px; font-size:0.9rem; margin:0; border-radius:0;">Apply</button>
                  </form>
                <?php endif; ?>
              </div>
            </div>

            <!-- Order Summary Sidebar -->
            <div class="cart-summary" style="background:#fff; border: 1.5px solid #111; box-shadow: 6px 6px 0px #000; padding:25px; display:flex; flex-direction:column; gap:15px;">
              <h2 style="font-size:1.5rem; font-weight:bold; margin-bottom:10px; border-bottom:2px solid #111; padding-bottom:10px; color:#111;">Order Summary</h2>
              
              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.95rem;">
                <span>Subtotal</span>
                <span>Rs. <?php echo number_format($subtotal); ?></span>
              </div>
              
              <?php if ($discount_amount > 0): ?>
                <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.95rem; color:#2e7d32; font-weight:bold;">
                  <span>Discount (<?php echo $discount_percent; ?>%)</span>
                  <span>- Rs. <?php echo number_format($discount_amount); ?></span>
                </div>
              <?php endif; ?>

              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.95rem;">
                <span>Shipping</span>
                <span><?php echo ($shipping == 0) ? '<span style="color:#2e7d32; font-weight:bold;">FREE</span>' : 'Rs. ' . number_format($shipping); ?></span>
              </div>

              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.95rem;">
                <span>Tax (5%)</span>
                <span>Rs. <?php echo number_format($tax); ?></span>
              </div>

              <div class="summary-row total" style="display:flex; justify-content:space-between; font-size:1.3rem; font-weight:bold; border-top:1.5px solid #111; padding-top:15px; margin-top:5px; color:#111;">
                <span>Total</span>
                <span>Rs. <?php echo number_format($grand_total); ?></span>
              </div>

              <?php if ($shipping > 0): ?>
                <span style="font-size:0.78rem; color:#666; text-align:center; font-style:italic;">Add <strong>Rs. <?php echo number_format(5000 - $discounted_subtotal); ?></strong> more to get Free Shipping!</span>
              <?php endif; ?>

              <button
                class="checkout-btn"
                onclick="window.location.href = 'Checkout.php'"
                style="width:100%; border:1.5px solid #111; box-shadow: 4px 4px 0px #000; background:#111; color:#fff; font-weight:bold; font-size:1.05rem; padding:14px; cursor:pointer; text-transform:uppercase; margin-top:10px; transition: all 0.2s;"
              >
                Proceed to Checkout
              </button>
            </div>

          </div>
        <?php else: ?>
          <div style="text-align:center; padding:80px 20px; border:1.5px dashed #ccc; background:#fff; margin-bottom:60px;">
            <i class="fas fa-shopping-basket" style="font-size:4rem; color:#aaa; margin-bottom:20px;"></i>
            <h2 style="font-size:1.8rem; font-weight:bold; margin-bottom:10px; color:#333;">Your Cart is Empty</h2>
            <p style="color:#777; margin-bottom:25px;">You have no items in your shopping cart yet.</p>
            <a href="Products.php" class="login-btn" style="text-decoration:none; display:inline-block; padding:12px 30px; border-radius:0;">Start Shopping</a>
          </div>
        <?php endif; ?>
      </div>
    </main>

<?php require_once 'footer.php'; ?>
