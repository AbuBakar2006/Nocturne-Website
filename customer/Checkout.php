<?php
// customer/Checkout.php
$page_title = 'Checkout';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: Login.php?message=Please+sign+in+to+proceed+to+checkout.");
    exit;
}

// Prevent back-button caching for logged-in customer pages
header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
header("Pragma: no-cache"); // HTTP 1.0.
header("Expires: 0"); // Proxies.


// 2. Verify cart is not empty
$cart_items = $_SESSION['cart'] ?? [];
if (count($cart_items) === 0) {
    header("Location: Cart.php");
    exit;
}

require_once '../admin/db_connect.php';

// 3. Fetch logged in customer info to pre-fill form
try {
    $cust_stmt = $pdo->prepare("SELECT * FROM customers WHERE id = ? LIMIT 1");
    $cust_stmt->execute([$_SESSION['customer_id']]);
    $customer_profile = $cust_stmt->fetch();
} catch (Exception $e) {
    $customer_profile = null;
}

// Map pre-filled variables
$full_name = $customer_profile['name'] ?? $_SESSION['customer_name'] ?? '';
$name_parts = explode(' ', trim($full_name));
$first_name = $name_parts[0] ?? '';
$last_name = (count($name_parts) > 1) ? implode(' ', array_slice($name_parts, 1)) : '';
$phone = $customer_profile['phone'] ?? '';
$city = $customer_profile['city'] ?? '';

// Retrieve totals from session
$totals = $_SESSION['order_totals'] ?? [
    'subtotal' => 0,
    'discount_percent' => 0,
    'discount_amount' => 0,
    'shipping' => 0,
    'tax' => 0,
    'grand_total' => 0
];

require_once 'header.php';
?>

    <main class="page-main checkout-page">
      <div class="page-header" style="background-color:#111; color:#fff; padding:60px 20px; text-align:center;">
        <h1>Checkout</h1>
        <p>Complete your purchase securely.</p>
      </div>

      <div class="checkout-container" style="max-width:1200px; margin: 40px auto 80px; padding: 0 20px; display: block;">
        <form action="place_order.php" method="post" id="checkout-form" onsubmit="return validateCheckout();">
          <div style="display:grid; grid-template-columns: 1fr 1fr; gap:40px; align-items: start;" class="checkout-layout-grid">
            
            <!-- Forms Container -->
            <div class="checkout-forms" style="display:flex; flex-direction:column; gap:35px;">
              
              <!-- Shipping Address -->
              <div class="checkout-section" style="background:#fff; border:1.5px solid #111; box-shadow:4px 4px 0px #000; padding:30px;">
                <h2 style="font-size:1.4rem; font-weight:bold; margin-bottom:20px; color:#111; border-bottom:2px solid #111; padding-bottom:8px;">Shipping Address</h2>
                
                <div class="checkout-form" style="display:flex; flex-direction:column; gap:16px;">
                  <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="input-group">
                      <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem;">First Name*</label>
                      <input type="text" name="first_name" placeholder="John" value="<?php echo htmlspecialchars($first_name); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                    </div>
                    <div class="input-group">
                      <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem;">Last Name*</label>
                      <input type="text" name="last_name" placeholder="Doe" value="<?php echo htmlspecialchars($last_name); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                    </div>
                  </div>
                  
                  <div class="input-group">
                    <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem;">Street Address*</label>
                    <input type="text" name="street_address" placeholder="House No / Street / Block / Area" required style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                  </div>
                  
                  <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                    <div class="input-group">
                      <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem;">City*</label>
                      <input type="text" name="city" placeholder="e.g. Lahore, Karachi" value="<?php echo htmlspecialchars($city); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                    </div>
                    <div class="input-group">
                      <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem;">ZIP Code (Optional)</label>
                      <input type="text" name="zip_code" placeholder="e.g. 54000" style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                    </div>
                  </div>

                  <div class="input-group">
                    <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.9rem;">Contact Mobile Phone Number*</label>
                    <input type="text" name="phone" placeholder="e.g. 0300-1234567" value="<?php echo htmlspecialchars($phone); ?>" required style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                  </div>
                </div>
              </div>

              <!-- Payment Method -->
              <div class="checkout-section" style="background:#fff; border:1.5px solid #111; box-shadow:4px 4px 0px #000; padding:30px;">
                <h2 style="font-size:1.4rem; font-weight:bold; margin-bottom:20px; color:#111; border-bottom:2px solid #111; padding-bottom:8px;">Payment Method</h2>
                
                <div class="checkout-form" style="display:flex; flex-direction:column; gap:20px;">
                  <!-- COD Select Option -->
                  <label class="remember-me" style="font-size:1.05rem; font-weight:bold; display:flex; align-items:center; gap:10px; border:1px solid #111; padding:12px; background:#fcfbf0; cursor:pointer;">
                    <input type="radio" name="payment_method" value="Cash on Delivery" checked onclick="toggleCardFields(false)" style="accent-color:#000; scale:1.2;" />
                    <span>Cash on Delivery (COD)</span>
                  </label>

                  <!-- Card Select Option -->
                  <label class="remember-me" style="font-size:1.05rem; font-weight:bold; display:flex; align-items:center; gap:10px; border:1px solid #111; padding:12px; background:#fcfbf0; cursor:pointer;">
                    <input type="radio" name="payment_method" value="Credit Card" onclick="toggleCardFields(true)" style="accent-color:#000; scale:1.2;" />
                    <span>Credit / Debit Card</span>
                  </label>

                  <!-- Card Details Form (Initially Hidden) -->
                  <div id="card-details-fields" style="display:none; flex-direction:column; gap:15px; border-left:3px solid #111; padding-left:20px; margin-top:5px; transition: all 0.3s;">
                    <div class="input-group">
                      <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.85rem;">Name on Card</label>
                      <input type="text" id="card-name" name="card_name" placeholder="John Doe" style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                    </div>
                    <div class="input-group">
                      <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.85rem;">Card Number</label>
                      <input type="text" id="card-num" name="card_number" placeholder="1111 2222 3333 4444" style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                    </div>
                    <div class="form-row" style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
                      <div class="input-group">
                        <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.85rem;">Expiry Date</label>
                        <input type="text" id="card-exp" name="card_expiry" placeholder="MM/YY" style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                      </div>
                      <div class="input-group">
                        <label style="font-weight:bold; display:block; margin-bottom:6px; font-size:0.85rem;">CVV Code</label>
                        <input type="password" id="card-cvv" name="card_cvv" placeholder="123" maxlength="4" style="width:100%; padding:10px; border:1px solid #ccc; font-size:0.95rem; background:#fcfbf0;" />
                      </div>
                    </div>
                  </div>
                </div>
              </div>

            </div>

            <!-- Side Summary Panel -->
            <div class="order-summary-panel" style="background:#fff; border: 1.5px solid #111; box-shadow: 6px 6px 0px #000; padding:25px; display:flex; flex-direction:column; gap:15px;">
              <h2 style="font-size:1.5rem; font-weight:bold; margin-bottom:10px; border-bottom:2px solid #111; padding-bottom:10px; color:#111;">Review Order</h2>
              
              <!-- Cart Items List Summary -->
              <div style="max-height: 220px; overflow-y:auto; display:flex; flex-direction:column; gap:10px; padding-bottom:10px; border-bottom:1px solid #eee;">
                <?php foreach ($cart_items as $item): 
                  $item_total = floatval($item['price']) * intval($item['quantity']);
                ?>
                  <div style="display:flex; justify-content:space-between; align-items:center; font-size:0.88rem;">
                    <div>
                      <strong style="color:#111;"><?php echo htmlspecialchars($item['name']); ?></strong> 
                      <span style="color:#666;">(Size: <?php echo htmlspecialchars($item['size']); ?>)</span>
                      <div style="color:#888; font-size:0.8rem;">Qty: <?php echo $item['quantity']; ?> x Rs. <?php echo number_format($item['price']); ?></div>
                    </div>
                    <span style="font-weight:bold;">Rs. <?php echo number_format($item_total); ?></span>
                  </div>
                <?php endforeach; ?>
              </div>

              <!-- Cost breakdown -->
              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.92rem;">
                <span>Subtotal</span>
                <span>Rs. <?php echo number_format($totals['subtotal']); ?></span>
              </div>
              
              <?php if ($totals['discount_amount'] > 0): ?>
                <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.92rem; color:#2e7d32; font-weight:bold;">
                  <span>Discount (<?php echo $totals['discount_percent']; ?>%)</span>
                  <span>- Rs. <?php echo number_format($totals['discount_amount']); ?></span>
                </div>
              <?php endif; ?>

              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.92rem;">
                <span>Shipping</span>
                <span><?php echo ($totals['shipping'] == 0) ? '<span style="color:#2e7d32; font-weight:bold;">FREE</span>' : 'Rs. ' . number_format($totals['shipping']); ?></span>
              </div>

              <div class="summary-row" style="display:flex; justify-content:space-between; font-size:0.92rem;">
                <span>Tax (5%)</span>
                <span>Rs. <?php echo number_format($totals['tax']); ?></span>
              </div>

              <div class="summary-divider" style="border-top:1.5px solid #111; margin-top:5px; padding-top:10px;"></div>

              <div class="summary-row total" style="display:flex; justify-content:space-between; font-size:1.3rem; font-weight:bold; color:#111;">
                <span>Total</span>
                <span>Rs. <?php echo number_format($totals['grand_total']); ?></span>
              </div>

              <button type="submit" class="checkout-btn" style="width:100%; border:1.5px solid #111; box-shadow: 4px 4px 0px #000; background:#111; color:#fff; font-weight:bold; font-size:1.05rem; padding:14px; cursor:pointer; text-transform:uppercase; margin-top:15px; display:block; text-align:center;">
                Complete Purchase
              </button>
            </div>
            
          </div>
        </form>
      </div>
    </main>

    <script>
      function toggleCardFields(show) {
          const cardFields = document.getElementById('card-details-fields');
          if (show) {
              cardFields.style.display = 'flex';
              document.getElementById('card-name').setAttribute('required', 'required');
              document.getElementById('card-num').setAttribute('required', 'required');
              document.getElementById('card-exp').setAttribute('required', 'required');
              document.getElementById('card-cvv').setAttribute('required', 'required');
          } else {
              cardFields.style.display = 'none';
              document.getElementById('card-name').removeAttribute('required');
              document.getElementById('card-num').removeAttribute('required');
              document.getElementById('card-exp').removeAttribute('required');
              document.getElementById('card-cvv').removeAttribute('required');
          }
      }

      function validateCheckout() {
          const paymentMethod = document.querySelector('input[name="payment_method"]:checked').value;
          
          if (paymentMethod === 'Credit Card') {
              const cardNum = document.getElementById('card-num').value.replace(/\s+/g, '');
              const cardCvv = document.getElementById('card-cvv').value;
              const cardExp = document.getElementById('card-exp').value;
              
              if (cardNum.length < 12 || isNaN(cardNum)) {
                  alert('Please enter a valid credit card number.');
                  return false;
              }
              if (cardCvv.length < 3 || isNaN(cardCvv)) {
                  alert('Please enter a valid 3 or 4 digit CVV code.');
                  return false;
              }
              if (!cardExp.includes('/')) {
                  alert('Please enter expiration date in MM/YY format.');
                  return false;
              }
          }
          return true;
      }
    </script>

<?php require_once 'footer.php'; ?>
