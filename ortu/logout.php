<?php
session_start();

// Hapus variabel sesi khusus orang tua
unset($_SESSION['parent_logged_in']);
unset($_SESSION['parent_siswa_id']);
unset($_SESSION['parent_siswa_nama']);
unset($_SESSION['parent_siswa_nisn']);
unset($_SESSION['parent_siswa_kelas']);

// Alihkan kembali ke halaman login portal orang tua
header("Location: login.php");
exit();
?>
