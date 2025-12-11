<?php
// pages/logout.php
include '../../config.php'; // untuk session_start()

// Hapus semua data session
session_unset();
session_destroy();

// Redirect ke halaman login
header('Location: ../auth/login.php');
exit;
?>