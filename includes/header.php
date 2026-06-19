<?php
// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure login is verified unless on login page
if (!isset($no_auth_check) && !isset($_SESSION['user_id'])) {
    $prefix = isset($path_prefix) ? $path_prefix : '';
    header("Location: " . $prefix . "auth/login.php");
    exit();
}

$prefix = isset($path_prefix) ? $path_prefix : '';
?>
<!DOCTYPE html>
<html lang="id" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($page_title) ? $page_title . " - Master Data Sekolah" : "Master Data Sekolah"; ?></title>
    <!-- Google Fonts Plus Jakarta Sans & Outfit -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.2/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="<?php echo $prefix; ?>assets/css/style.css" rel="stylesheet">
</head>
<body>

<div class="d-flex">
    <!-- Sidebar navigation -->
    <?php include 'sidebar.php'; ?>

    <!-- Main Content wrapper -->
    <div class="main-wrapper flex-grow-1">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg top-nav px-3 py-2 rounded-3 mb-4 d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-3">
                <button class="btn btn-outline-secondary d-lg-none" id="sidebar-toggle" type="button">
                    <i class="bi bi-list"></i>
                </button>
                <h5 class="m-0 font-weight-bold d-none d-sm-block text-secondary">
                    <?php echo isset($page_title) ? $page_title : "Dashboard"; ?>
                </h5>
            </div>
            
            <div class="d-flex align-items-center gap-3">
                <!-- Live Clock -->
                <div class="d-none d-md-flex align-items-center text-secondary small fw-semibold me-2 gap-2" id="live-clock">
                    <i class="bi bi-calendar3 text-primary"></i>
                    <span id="clock-display" class="font-monospace">Memuat...</span>
                </div>

                <!-- Dark Mode Toggle Switch -->
                <div class="form-check form-switch m-0 d-flex align-items-center gap-2">
                    <i class="bi bi-sun-fill text-warning"></i>
                    <input class="form-check-input" type="checkbox" role="switch" id="theme-toggle">
                    <i class="bi bi-moon-stars-fill text-primary"></i>
                </div>
                
                <div class="vr mx-2 text-secondary"></div>
                
                <!-- User Profile Dropdown -->
                <div class="dropdown">
                    <button class="btn btn-link nav-link dropdown-toggle d-flex align-items-center gap-2 text-decoration-none" type="button" id="userMenuDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                        <div class="avatar-circle bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 32px; height: 32px; font-weight: 600; font-size: 14px;">
                            <?php 
                            $name = isset($_SESSION['nama_lengkap']) ? $_SESSION['nama_lengkap'] : 'User';
                            echo strtoupper(substr($name, 0, 2)); 
                            ?>
                        </div>
                        <span class="d-none d-md-inline text-secondary-emphasis">
                            <?php echo htmlspecialchars($name); ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0 mt-2" aria-labelledby="userMenuDropdown">
                        <li class="dropdown-header text-center">
                            <strong><?php echo htmlspecialchars($name); ?></strong>
                            <div class="text-muted small mt-1">
                                <span class="badge bg-info text-dark">
                                    <?php 
                                    $role_labels = [
                                        'super_admin' => 'Super Admin',
                                        'operator' => 'Operator',
                                        'guru' => 'Guru',
                                        'kepala_sekolah' => 'Kepala Sekolah'
                                    ];
                                    echo $role_labels[$_SESSION['role']] ?? $_SESSION['role']; 
                                    ?>
                                </span>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger d-flex align-items-center gap-2" href="<?php echo $prefix; ?>auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Keluar
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>

        <!-- Dynamic Success/Error Alerts -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="alert alert-success alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <?php 
                echo $_SESSION['success_message']; 
                unset($_SESSION['success_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm" role="alert">
                <i class="bi bi-exclamation-triangle-fill me-2"></i>
                <?php 
                echo $_SESSION['error_message']; 
                unset($_SESSION['error_message']);
                ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Global Session Timeout Counter Widget -->
        <div class="card border-0 shadow-sm mb-4 bg-light bg-opacity-75 overflow-hidden position-relative">
            <!-- Progress Bar showing session time left -->
            <div class="progress position-absolute top-0 start-0 w-100" style="height: 4px; border-radius: 0;">
                <div id="session-progress" class="progress-bar bg-success" role="progressbar" style="width: 100%; transition: width 1s linear;"></div>
            </div>
            <div class="card-body py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2">
                <div class="d-flex align-items-center gap-2">
                    <i class="bi bi-clock-history text-warning fs-5"></i>
                    <span class="text-muted small">Sesi Keamanan Aktif: kedaluwarsa jika tidak ada aktivitas dalam <strong id="session-countdown" class="font-monospace text-warning-emphasis">15:00</strong>.</span>
                </div>
                <div>
                    <button id="btn-renew-session" class="btn btn-xs btn-outline-warning fw-bold px-2 py-0.5 shadow-sm" style="font-size: 11px;">
                        <i class="bi bi-arrow-clockwise"></i> Perpanjang Sesi
                    </button>
                </div>
            </div>
        </div>

        <script>
        document.addEventListener("DOMContentLoaded", function() {
            const totalSeconds = 15 * 60; // 15 minutes = 900 seconds
            let secondsLeft = totalSeconds;
            const countdownEl = document.getElementById("session-countdown");
            const progressEl = document.getElementById("session-progress");
            const renewBtn = document.getElementById("btn-renew-session");
            let intervalId = null;
            let lastRenewTime = Date.now();
            const renewThrottleMs = 30000; // Throttle server pings to once every 30 seconds

            function updateTimer() {
                if (secondsLeft <= 0) {
                    clearInterval(intervalId);
                    alert("Sesi Anda telah berakhir karena tidak aktif. Silakan login kembali.");
                    window.location.href = "<?php echo $prefix; ?>auth/logout.php";
                    return;
                }

                secondsLeft--;

                // Format MM:SS
                const minutes = Math.floor(secondsLeft / 60);
                const seconds = secondsLeft % 60;
                countdownEl.textContent = 
                    String(minutes).padStart(2, '0') + ":" + 
                    String(seconds).padStart(2, '0');

                // Progress bar width
                const percentage = (secondsLeft / totalSeconds) * 100;
                progressEl.style.width = percentage + "%";

                // Progress bar color warnings
                if (percentage < 20) {
                    progressEl.className = "progress-bar bg-danger";
                } else if (percentage < 50) {
                    progressEl.className = "progress-bar bg-warning";
                } else {
                    progressEl.className = "progress-bar bg-success";
                }
            }

            function startTimer() {
                clearInterval(intervalId);
                secondsLeft = totalSeconds;
                progressEl.style.width = "100%";
                progressEl.className = "progress-bar bg-success";
                countdownEl.textContent = "15:00";
                intervalId = setInterval(updateTimer, 1000);
            }

            function silentRenewSession() {
                // Perform silent AJAX hit to current page to renew php session cookie
                fetch(window.location.href)
                    .then(response => {
                        if (!response.ok) {
                            console.error("Gagal memperbarui sesi latar belakang.");
                        }
                    })
                    .catch(error => {
                        console.error("Koneksi gagal saat pembaruan latar belakang:", error);
                    });
            }

            function handleActivity() {
                // Reset timer state locally
                secondsLeft = totalSeconds;
                progressEl.style.width = "100%";
                progressEl.className = "progress-bar bg-success";
                countdownEl.textContent = "15:00";

                // Send throttled session renewal to server
                const now = Date.now();
                if (now - lastRenewTime > renewThrottleMs) {
                    lastRenewTime = now;
                    silentRenewSession();
                }
            }

            // Manual renew session trigger
            renewBtn.addEventListener("click", function() {
                renewBtn.disabled = true;
                renewBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Memperpanjang...';

                fetch(window.location.href)
                    .then(response => {
                        if (response.ok) {
                            lastRenewTime = Date.now();
                            startTimer();
                        } else {
                            console.error("Gagal memperbarui sesi.");
                        }
                    })
                    .catch(error => {
                        console.error("Koneksi gagal:", error);
                    })
                    .finally(() => {
                        renewBtn.disabled = false;
                        renewBtn.innerHTML = '<i class="bi bi-arrow-clockwise"></i> Perpanjang Sesi';
                    });
            });

            // Activity event listeners
            const activityEvents = ['mousemove', 'mousedown', 'keypress', 'scroll', 'touchstart'];
            activityEvents.forEach(eventName => {
                document.addEventListener(eventName, handleActivity, { passive: true });
            });

            // Initial run
            startTimer();
        });
        </script>
