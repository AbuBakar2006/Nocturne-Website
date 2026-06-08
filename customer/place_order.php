<?php
// customer/place_order.php
require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: Login.php");
    exit;
}

// 2. Verify cart is not empty
$cart_items = $_SESSION['cart'] ?? [];
if (count($cart_items) === 0) {
    header("Location: Cart.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: Checkout.php");
    exit;
}

// 3. Collect post fields
$first_name = trim($_POST['first_name'] ?? '');
$last_name = trim($_POST['last_name'] ?? '');
$street_address = trim($_POST['street_address'] ?? '');
$city = trim($_POST['city'] ?? '');
$zip_code = trim($_POST['zip_code'] ?? '');
$phone = trim($_POST['phone'] ?? '');
$payment_method = trim($_POST['payment_method'] ?? 'Cash on Delivery');

// Validation
if (empty($first_name) || empty($last_name) || empty($street_address) || empty($city) || empty($phone)) {
    $_SESSION['checkout_error'] = "All mandatory shipping fields are required.";
    header("Location: Checkout.php");
    exit;
}

// Build address string
$shipping_address = "{$first_name} {$last_name}\n{$street_address}\n{$city}";
if (!empty($zip_code)) {
    $shipping_address .= " - {$zip_code}";
}
$shipping_address .= "\nPhone: {$phone}";

// Build payment method representation
if ($payment_method === 'Credit Card') {
    $card_num = trim($_POST['card_number'] ?? '');
    $last_4 = substr(preg_replace('/\s+/', '', $card_num), -4);
    if (empty($last_4)) {
        $last_4 = "0000";
    }
    $payment_method_str = "Card ending in **** {$last_4}";
} else {
    $payment_method_str = "Cash on Delivery";
}

// Retrieve totals from session
$totals = $_SESSION['order_totals'] ?? [
    'grand_total' => 0
];
$grand_total = floatval($totals['grand_total']);

if ($grand_total <= 0) {
    header("Location: Cart.php");
    exit;
}

try {
    // 4. Start Transaction
    $pdo->beginTransaction();

    // Check inventory stock for all items
    foreach ($cart_items as $item) {
        $stmt = $pdo->prepare("SELECT name, inventory_qty FROM products WHERE id = ? FOR UPDATE");
        $stmt->execute([$item['product_id']]);
        $prod = $stmt->fetch();

        if (!$prod) {
            throw new Exception("Product not found in our catalog.");
        }

        if (intval($prod['inventory_qty']) < intval($item['quantity'])) {
            throw new Exception("Item '{$prod['name']}' has insufficient stock. (Available: {$prod['inventory_qty']})");
        }
    }

    // Insert Order
    $order_stmt = $pdo->prepare("INSERT INTO orders (customer_id, order_date, status, total_amount, shipping_address, payment_method) VALUES (?, CURDATE(), 'Pending', ?, ?, ?)");
    $order_stmt->execute([
        $_SESSION['customer_id'],
        $grand_total,
        $shipping_address,
        $payment_method_str
    ]);
    
    $order_id = $pdo->lastInsertId();

    // Insert Order Items and Deduct Inventory Stock
    $item_stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price, size) VALUES (?, ?, ?, ?, ?)");
    $stock_stmt = $pdo->prepare("UPDATE products SET inventory_qty = inventory_qty - ? WHERE id = ?");

    foreach ($cart_items as $item) {
        // Insert order item
        $item_stmt->execute([
            $order_id,
            $item['product_id'],
            $item['quantity'],
            $item['price'],
            $item['size']
        ]);

        // Deduct inventory stock
        $stock_stmt->execute([
            $item['quantity'],
            $item['product_id']
        ]);
    }

    // Commit Transaction
    $pdo->commit();

    // Clear cart and calculations
    unset($_SESSION['cart']);
    unset($_SESSION['order_totals']);
    unset($_SESSION['promo_code']);
    unset($_SESSION['promo_discount_percent']);

    // Redirect to dynamic Order Details with success message
    header("Location: OrderDetails.php?id={$order_id}&success=1");
    exit;

} catch (Exception $e) {
    // Rollback changes
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    // Set error message
    $_SESSION['checkout_error'] = "Order Placement Failed: " . $e->getMessage();
    header("Location: Cart.php"); // Redirect to cart to see errors
    exit;
}
