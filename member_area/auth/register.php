<?php 
// pages/register.php
include '../../config.php';
include '../../functions.php';

// Inisialisasi pesan
$message = null;
$error_message = null;

// ---- 1. Filter Input ----
$email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $email && $password) {

    // ===================================================
    // 🚩 LANGKAH 1 — CEK user_profile BERDASARKAN EMAIL
    // ===================================================
    $profile_res = supabase_fetch(
        "/user_profile?email=eq.$email&select=user_id,role",
        'GET'
    );

    $profile = $profile_res['data'][0] ?? null;
    if (!$profile) {
        $error_message = "Akun tidak ditemukan. Anda harus mendaftar di aplikasi utama terlebih dahulu.";
    } elseif ($profile['role'] === 'affiliator') {
        $error_message = "Email ini sudah menjadi Afiliasi. Silakan login.";
    } else {

        // ===================================================
        // 🚩 LANGKAH 2 — SIGN-IN Supabase Auth (bukan signup)
        // ===================================================
        $auth_response = supabase_auth_request(
            '/token?grant_type=password',
            [
                'email' => $email,
                'password' => $password
            ]
        );

        if ($auth_response['status'] !== 200) {
            $error_message = "Email atau password salah.";
        } else {

            // Berhasil login
            $auth_user_id = $auth_response['data']['user']['id'] ?? null;

            if (!$auth_user_id) {
                $error_message = "Gagal mendapatkan ID pengguna. Silakan coba lagi.";
            } else {

                // ===================================================
                // 🚩 LANGKAH 3 — UPDATE user_profile (set affiliator)
                // ===================================================
                $update_profile = supabase_fetch(
                    "/user_profile?email=eq.$email",
                    "PATCH",
                    [
                        'user_id' => $auth_user_id,   // Ikat Auth ID
                        'role' => 'affiliator'
                    ]
                );

                if ($update_profile['status'] < 200 || $update_profile['status'] >= 300) {
                    $error_message = "Gagal mengupdate profil. (Error: " . $update_profile['data']['message'] . ")";
                } else {

                    // ===================================================
                    // 🚩 LANGKAH 4 — INSERT affiliate_details
                    // ===================================================
                    $referral_code = substr(strtoupper(md5(uniqid(rand(), true))), 0, 6);

                    $aff_insert = supabase_fetch(
                        "/affiliate_details",
                        "POST",
                        [
                            'user_id' => $auth_user_id,
                            'referral_code' => $referral_code,
                            'wallet_balance' => 0
                        ]
                    );

                    if ($aff_insert['status'] >= 200 && $aff_insert['status'] < 300) {

                        // Semua sukses
                        $message = "Akun Afiliasi berhasil diaktifkan! Silakan login.";

                        // Redirect otomatis
                        header("Refresh: 4; URL=login.php");

                    } else {
                        $error_message = "Gagal membuat detail afiliasi. (Error: " . $aff_insert['data']['message'] . ")";
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Register | My Tahfidz Affiliate</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css">
</head>

<body class="register-page bg-body-secondary">
<div class="register-box">
    <div class="register-logo"><b>My Tahfidz</b> Affiliate</div>

    <div class="card">
        <div class="card-body register-card-body">
            <p class="register-box-msg">Aktifkan Akun Afiliasi Anda</p>

            <?php if ($error_message): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error_message); ?></div>
            <?php endif; ?>

            <?php if ($message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <form method="post">
                <div class="input-group mb-3">
                    <input type="email" name="email" class="form-control" placeholder="Email" required value="<?php echo htmlspecialchars($email ?? ''); ?>">
                    <div class="input-group-text"><span class="bi bi-envelope"></span></div>
                </div>

                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password" required>
                    <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
                </div>

                <div class="d-grid gap-2">
                    <button class="btn btn-primary" type="submit">Aktifkan Afiliasi</button>
                </div>
            </form>

            <p class="text-center mt-3">
                <a href="login.php">Saya sudah punya akun</a>
            </p>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/overlaysscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
<script src="../../js/adminlte/adminlte.js"></script>

</body>
</html>
