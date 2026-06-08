<?php
// auth.php
// Starts the session and enforces authentication checks.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function check_admin_login() {
    if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
        header("Location: AdminLogin.php");
        exit;
    }
    // Prevent back-button caching for logged-in admin pages
    header("Cache-Control: no-cache, no-store, must-revalidate"); // HTTP 1.1.
    header("Pragma: no-cache"); // HTTP 1.0.
    header("Expires: 0"); // Proxies.
}

?>
