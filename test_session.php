<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 0;
}
$_SESSION['test_counter']++;

echo "<h3>PHP Session Test</h3>";
echo "Session ID: " . session_id() . "<br>";
echo "Counter: " . $_SESSION['test_counter'] . "<br>";
echo "Session Save Path: " . session_save_path() . "<br>";
echo "Is Writable: " . (is_writable(session_save_path()) ? "Yes" : "No") . "<br>";
?>
