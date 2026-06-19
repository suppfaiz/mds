<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
}

// Check if user is logged in (specifically for files in the root folder, e.g., index.php)
function checkLoginRoot() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: auth/login.php");
        exit();
    }
}

// Restrict access based on roles
function checkRole($allowedRoles, $isRoot = false) {
    if (!isset($_SESSION['role'])) {
        if ($isRoot) {
            header("Location: auth/login.php");
        } else {
            header("Location: ../auth/login.php");
        }
        exit();
    }
    
    if (!in_array($_SESSION['role'], $allowedRoles)) {
        $_SESSION['error_message'] = "Anda tidak memiliki hak akses ke halaman tersebut.";
        if ($isRoot) {
            header("Location: dashboard_core.php");
        } else {
            header("Location: ../dashboard_core.php");
        }
        exit();
    }
}

// Check if user has permission (returns boolean, useful for conditional buttons/UI)
function hasPermission($allowedRoles) {
    if (!isset($_SESSION['role'])) {
        return false;
    }
    return in_array($_SESSION['role'], $allowedRoles);
}
?>
