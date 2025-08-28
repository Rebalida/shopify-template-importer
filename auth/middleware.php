<?php
function checkAuth($requiredRole = null) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }

    // Check role if required
    if ($requiredRole && $_SESSION['role'] !== $requiredRole) {
        header("Location: ../dashboard/?error=unauthorized");
        exit();
    }

    // Check session timeout (30 minutes)
    if (time() - $_SESSION['last_activity'] > 1800) {
        session_destroy();
        header("Location: ../auth/login.php?msg=timeout");
        exit();
    }

    $_SESSION['last_activity'] = time();
}