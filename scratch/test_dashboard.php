<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Mock session
$_SESSION['parent_logged_in'] = true;
$_SESSION['parent_siswa_id'] = 1; // Assuming siswa with ID 1 exists in DB (Budi Santoso)

// Mock server environment
$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['REQUEST_METHOD'] = 'GET';

echo "=== STARTING TEST ===\n";
chdir(__DIR__ . '/../ortu');
require_once 'dashboard.php';
echo "\n=== TEST PASSED SUCCESSFULLY ===\n";
