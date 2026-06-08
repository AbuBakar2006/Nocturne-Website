<?php
// customer/reorder.php
require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 1. Verify user is logged in
if (!isset($_SESSION['customer_logged_in']) || $_SESSION['customer_logged_in'] !== true) {
    header("Location: Login.php");
    exit;
}

$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($order_id > 0) {
    try {
        // 2. Verify that this order belongs to the logged-in customer
        $stmt = $pdo->prepare("SELECT customer_id FROM orders WHERE id = ? LIMIT 1");
        $stmt->execute([$order_id]);
        $owner_id = $stmt->fetchColumn();

        if ($owner_id && intval($owner_id) === intval($_SESSION['customer_id'])) {
            // 3. Fetch all items in the order
            $items_stmt = $pdo->prepare("
                SELECT oi.*, p.name, p.image_path, p.inventory_qty, p.price AS current_price
                FROM order_items oi
                JOIN products p ON oi.product_id = p.id
                WHERE oi.order_id = ?
            ");
            $items_stmt->execute([$order_id]);
            $order_items = $items_stmt->fetchAll();

            if (!isset($_SESSION['cart'])) {
                $_SESSION['cart'] = [];
            }

            // 4. Add items back to cart, respecting inventory stock limits
            foreach ($order_items as $item) {
                $product_id = $item['product_id'];
                $size = $item['size'];
                $qty = intval($item['quantity']);
                $max_inventory = intval($item['inventory_qty']);

                if ($max_inventory > 0 && $product_id > 0) {
                    $cart_key = $product_id . '_' . $size;
                    $existing_qty = isset($_SESSION['cart'][$cart_key]) ? $_SESSION['cart'][$cart_key]['quantity'] : 0;
                    $new_qty = $existing_qty + $qty;

                    // Bound new quantity by inventory
                    if ($new_qty > $max_inventory) {
                        $new_qty = $max_inventory;
                    }

                    $_SESSION['cart'][$cart_key] = [
                        'product_id' => $product_id,
                        'name' => $item['name'],
                        'price' => floatval($item['current_price']),
                        'image_path' => $item['image_path'],
                        'size' => $size,
                        'quantity' => $new_qty
                    ];
                }
            }
            header("Location: Cart.php?message=Items+from+past+order+have+been+added+to+your+cart.");
            exit;
        }
    } catch (Exception $e) {
        // Fallback
    }
}

header("Location: Orders.php");
exit;
