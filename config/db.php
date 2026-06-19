<?php
if (session_status() === PHP_SESSION_NONE) {
    // Hardening session cookies
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
}

// Generate CSRF token if not set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Global CSRF helper function
if (!function_exists('csrf_field')) {
    function csrf_field() {
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8') . '">';
    }
}

// Polyfill php version compatibility
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) {
        return substr($haystack, 0, strlen($needle)) === $needle;
    }
}
if (!function_exists('str_ends_with')) {
    function str_ends_with($haystack, $needle) {
        $length = strlen($needle);
        if (!$length) {
            return true;
        }
        return substr($haystack, -$length) === $needle;
    }
}

// Lightweight Dotenv Parser
function loadEnv($path) {
    if (!file_exists($path)) {
        return;
    }
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (str_starts_with(trim($line), '#')) {
            continue;
        }
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);
            // Remove surrounding quotes
            if (preg_match('/^["\'](.*)["\']$/', $value, $matches)) {
                $value = $matches[1];
            }
            if (!array_key_exists($name, $_SERVER) && !array_key_exists($name, $_ENV)) {
                putenv(sprintf('%s=%s', $name, $value));
                $_ENV[$name] = $value;
                $_SERVER[$name] = $value;
            }
        }
    }
}

// Load .env from root directory
loadEnv(realpath(__DIR__ . '/../.env'));

// Set Database Credentials
$host = $_ENV['DB_HOST'] ?? 'localhost';
$dbname = $_ENV['DB_NAME'] ?? 'master_data_sekolah';
$username = $_ENV['DB_USER'] ?? 'root';
$password = $_ENV['DB_PASS'] ?? '';
$app_key = $_ENV['APP_KEY'] ?? 'default_secret_key_123456789012';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Auto-migration: Ensure TOTP columns exist in the active users table
    try {
        $check_stmt = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'totp_secret'");
        if (!$check_stmt->fetch()) {
            $pdo->exec("ALTER TABLE `users` ADD COLUMN `totp_secret` VARCHAR(32) DEFAULT NULL, ADD COLUMN `totp_enabled` TINYINT(1) DEFAULT 0");
        }
    } catch (PDOException $e) {
        // Ignore if database/table is not initialized yet
    }
} catch (PDOException $e) {
    die("Koneksi database gagal: " . $e->getMessage());
}

// Obfuscasi ID URL Helpers
function encryptId($id) {
    global $app_key;
    $iv = substr(hash('sha256', $app_key), 0, 16);
    $encrypted = openssl_encrypt((string)$id, 'AES-128-CBC', $app_key, OPENSSL_RAW_DATA, $iv);
    return bin2hex($encrypted);
}

function decryptId($encryptedHex) {
    if (empty($encryptedHex) || !ctype_xdigit($encryptedHex)) {
        return null;
    }
    global $app_key;
    $iv = substr(hash('sha256', $app_key), 0, 16);
    $decrypted = openssl_decrypt(hex2bin($encryptedHex), 'AES-128-CBC', $app_key, OPENSSL_RAW_DATA, $iv);
    return $decrypted !== false ? (int)$decrypted : null;
}

// Interseptor Dekripsi URL Global secara Transparan
foreach ($_GET as $key => $value) {
    if (($key === 'id' || str_ends_with($key, '_id')) && !is_numeric($value)) {
        $decrypted = decryptId($value);
        if ($decrypted !== null) {
            $_GET[$key] = $decrypted;
            $_REQUEST[$key] = $decrypted;
        }
    }
}
?>
