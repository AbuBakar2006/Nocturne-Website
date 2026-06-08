<?php
// customer/cart_action.php
require_once '../admin/db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action === 'add') {
    $product_id = isset($_POST['product_id']) ? intval($_POST['product_id']) : 0;
    $size = trim($_POST['size'] ?? '');
    $qty = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

    if ($product_id > 0 && !empty($size)) {
        try {
            // Check product availability
            $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();

            if ($product) {
                $max_inventory = intval($product['inventory_qty']);
                $cart_key = $product_id . '_' . $size;

                // Determine new quantity
                $existing_qty = isset($_SESSION['cart'][$cart_key]) ? $_SESSION['cart'][$cart_key]['quantity'] : 0;
                $new_qty = $existing_qty + $qty;

                // Ensure it does not exceed inventory
                if ($new_qty > $max_inventory) {
                    $new_qty = $max_inventory;
                }

                if ($new_qty > 0) {
                    $_SESSION['cart'][$cart_key] = [
                        'product_id' => $product_id,
                        'name' => $product['name'],
                        'price' => floatval($product['price']),
                        'image_path' => $product['image_path'],
                        'size' => $size,
                        'quantity' => $new_qty
                    ];
                }
            }
        } catch (Exception $e) {
            // Silently fail or log in development
        }
    }
    header("Location: Cart.php");
    exit;

} elseif ($action === 'update') {
    $cart_key = trim($_POST['cart_key'] ?? '');
    $qty = isset($_POST['quantity']) ? max(1, intval($_POST['quantity'])) : 1;

    if (!empty($cart_key) && isset($_SESSION['cart'][$cart_key])) {
        try {
            $product_id = $_SESSION['cart'][$cart_key]['product_id'];
            $stmt = $pdo->prepare("SELECT inventory_qty FROM products WHERE id = ? LIMIT 1");
            $stmt->execute([$product_id]);
            $max_inventory = intval($stmt->fetchColumn());

            if ($qty > $max_inventory) {
                $qty = $max_inventory;
            }
            $_SESSION['cart'][$cart_key]['quantity'] = $qty;
        } catch (Exception $e) {
            $_SESSION['cart'][$cart_key]['quantity'] = $qty;
        }
    }
    header("Location: Cart.php");
    exit;

} elseif ($action === 'remove') {
    $cart_key = trim($_GET['cart_key'] ?? '');
    if (!empty($cart_key) && isset($_SESSION['cart'][$cart_key])) {
        unset($_SESSION['cart'][$cart_key]);
    }
    header("Location: Cart.php");
    exit;
}

// Redirect back if action is invalid
header("Location: Homepage.php");
exit;
