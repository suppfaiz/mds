<?php
/**
 * Root Public Entry Point
 * 
 * Orang umum yang buka domain.com/ akan diarahkan ke PMB.
 * Admin masuk lewat URL tersembunyi di Nginx (lihat nginx_admin_secret.conf).
 */
header("Location: pmb/daftar.php", true, 302);
exit();
