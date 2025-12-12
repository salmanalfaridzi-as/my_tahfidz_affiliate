<?php
// config.php
define('BASE_URL', 'http://localhost/my_tahfidz_affiliate'); 

// --- Kredensial Supabase ---
define('SUPABASE_URL', 'https://zhslxxhwqxyaxkidgttn.supabase.co'); 
define('SUPABASE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inpoc2x4eGh3cXh5YXhraWRndHRuIiwicm9sZSI6ImFub24iLCJpYXQiOjE3NTc5MTU4NDAsImV4cCI6MjA3MzQ5MTg0MH0.97Nr6g4nUB_JpT8_X6wcX0mvxriyc-KRSnXzGUATgYs'); // Ganti dengan Anon Key Anda
define('SUPABASE_SERVICE_KEY', 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6Inpoc2x4eGh3cXh5YXhraWRndHRuIiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTc1NzkxNTg0MCwiZXhwIjoyMDczNDkxODQwfQ.ok51rssFs9DFaVqfpTgihaHqJFx2t8sMhG45NBNeboE'); // Ganti dengan Anon Key Anda
define('SUPABASE_AUTH_URL', SUPABASE_URL . '/auth/v1');
define('SUPABASE_DB_URL', SUPABASE_URL . '/rest/v1');

// --- Kredensial Xendit ---
define('XENDIT_SECRET_KEY', 'xnd_development_...'); // Ganti dengan Secret Key Anda

// --- Konfigurasi Aplikasi ---
define('REFERRAL_COOKIE_NAME', 'aff_ref_code');
define('REFERRAL_COOKIE_EXPIRY', time() + (86400 * 30)); // 30 hari

session_start();
?>