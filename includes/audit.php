<?php
if (!function_exists('logActivity')) {
    function logActivity($pdo, $aktivitas, $detail) {
        $userId = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        $username = isset($_SESSION['username']) ? $_SESSION['username'] : 'Guest';
        
        // Detect IP
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO audit_log (user_id, username, aktivitas, detail, ip_address) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$userId, $username, $aktivitas, $detail, $ip]);
        } catch (PDOException $e) {
            error_log("Gagal mencatat audit log: " . $e->getMessage());
        }
    }
}
?>
