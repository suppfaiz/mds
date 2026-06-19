<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset parent portal session variables
unset($_SESSION['pmb_parent_id']);
unset($_SESSION['pmb_parent_nama']);
unset($_SESSION['pmb_parent_email']);
unset($_SESSION['pmb_parent_no_hp']);

// Redirect to parent login
session_start();
$_SESSION['success_message'] = 'Anda berhasil keluar dari sistem.';
header("Location: login.php");
exit();
?>
