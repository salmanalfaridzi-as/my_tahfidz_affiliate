<?php 
include '../../config.php'; 
include '../../functions.php'; 

$message = null; 
$error_message = null; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    // Sanitasi Input
    $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING);

    if ($email && $password) { 
        // ===============================================================
        // 🚩 1. CEK PROFIL DULU 
        // ===============================================================
        $profile_res = supabase_fetch("/user_profile?email=eq.$email&select=user_id,role");
        $existing_profile = $profile_res['data'][0] ?? null;

        $auth_user_id = null;
        $process_status = 'failed';
        $is_new_registration = false;

        // ===============================================================
        // 🚩 2. LOGIC PERCABANGAN
        // ===============================================================

        // ---------------------------------------------------------------
        // SKENARIO A: USER SUDAH ADA
        // ---------------------------------------------------------------
        if ($existing_profile) { 
            if ($existing_profile['role'] === 'affiliator') {
                $error_message = "Akun ini sudah aktif sebagai Afiliasi. Silakan login.";
            } else {
                // Verifikasi password via Auth Login
                $auth_response = supabase_auth_request('/token?grant_type=password', [
                    'email' => $email, 
                    'password' => $password
                ]);

                if ($auth_response['status'] !== 200) {
                    $error_message = "Email sudah terdaftar, namun password salah. Gunakan password akun Anda.";
                } else {
                    $auth_user_id = $existing_profile['user_id'];
                    // Upgrade role
                    $update_role = supabase_fetch("/user_profile?user_id=eq.$auth_user_id", "PATCH", ['role' => 'affiliator']);
                    
                    if ($update_role['status'] == 204) {
                        $process_status = 'success';
                    } else {
                        $error_message = "Gagal memperbarui role user.";
                    }
                }
            }
        } 
        // ---------------------------------------------------------------
        // SKENARIO B: USER BARU (BELUM ADA DI USER_PROFILE)
        // ---------------------------------------------------------------
        else { 
            $is_eligible = false;
            $payment_source = 'none'; // 'doku' atau 'scalev'
            $code_data = null;
            $trx_data = null;

            // 1. Cek Transaksi DOKU (Menggantikan Xendit)
            // Syarat: login_status = unused DAN (status = SUCCESS atau PENDING)
            // Note: Menggunakan kolom 'doku_invoice_number' asumsinya kolom di DB belum di-rename
            $trx_check = supabase_fetch("/transactions?customer_email=eq.$email&login_status=eq.unused&or=(status.eq.SUCCESS,status.eq.PENDING)&select=id,product_id,doku_invoice_number,status,doku_request_id");
            $trx_candidates = $trx_check['data'] ?? [];

            foreach ($trx_candidates as $trx) {
                // CASE 1: Sudah SUKSES (DOKU = SUCCESS)
                if ($trx['status'] === 'SUCCESS') {
                    $trx_data = $trx;
                    break;
                }
                
                // CASE 2: Masih PENDING -> Cek status real-time ke DOKU
                if ($trx['status'] === 'PENDING') {
                    if (!empty($trx['doku_invoice_number'])) {
                        // Panggil Fungsi Cek Status DOKU (passing Invoice ID)
                        check_and_update_doku_order_full($trx['doku_invoice_number'], $trx['doku_request_id']);
                        
                        // Cek Ulang Status di Database setelah update function dijalankan
                        $recheck = supabase_fetch("/transactions?id=eq." . $trx['id'] . "&select=id,product_id,doku_invoice_number,status");
                        $updated_trx = $recheck['data'][0] ?? null;

                        // Jika status berubah jadi SUCCESS setelah dicek
                        if ($updated_trx && $updated_trx['status'] === 'SUCCESS') {
                            $trx_data = $updated_trx;
                            break; // Valid, keluar loop
                        }
                    }
                }
            }

            if ($trx_data) {
                $payment_source = 'doku';
                $is_eligible = true;
            } else {
                // 2. Cek Purchase Codes (Scalev - Gateway Lama)
                // Scalev logic: status code 'unused' (biasanya status pembayarannya PAID di sistem scalev, tapi di sini kita cek status kode)
                $code_check = supabase_fetch("/purchase_codes?email=eq.$email&status=eq.unused&select=id,product_id,order_id");
                $code_data = $code_check['data'][0] ?? null;
                
                if ($code_data) {
                    $payment_source = 'scalev';
                    $is_eligible = true;
                }
            }

            if (!$is_eligible) {
                $error_message = "Email belum terdaftar pembelian yang BERHASIL (SUCCESS) atau pembayaran masih pending.";
            } elseif (strlen($password) < 6) {
                $error_message = "Password minimal 6 karakter.";
            } else {
                // --- PROSES REGISTER ---
                $signup_response = supabase_auth_request('/signup', [
                    'email' => $email,
                    'password' => $password
                ]);

                if ($signup_response['status'] !== 200) {
                    $err_msg = $signup_response['data']['msg'] ?? 'Gagal mendaftarkan akun.';
                    $error_message = "Registrasi Gagal: $err_msg";
                } else {
                    $auth_user_id = $signup_response['data']['id'] ?? ($signup_response['data']['user']['id'] ?? null);

                    if (!$auth_user_id) {
                        $error_message = "Gagal memproses ID user.";
                    } else {
                        // Insert User Profile
                        $profile_data = [
                            'user_id' => $auth_user_id,
                            'email' => $email,
                            'name' => $name ?: explode('@', $email)[0],
                            'role' => 'affiliator',
                            'is_premium' => true,
                            'status' => 'active'
                        ];

                        // Simpan info pembelian
                        if ($payment_source === 'scalev' && $code_data) {
                            $profile_data['jenis_package'] = $code_data['product_id'];
                            $profile_data['order_id'] = $code_data['order_id'];
                        } elseif ($payment_source === 'doku' && $trx_data) {
                            $profile_data['product_purchased'] = $trx_data['product_id'];
                            $profile_data['order_id'] = $trx_data['doku_invoice_number']; // ID Invoice Doku
                        }

                        $insert_profile = supabase_fetch("/user_profile", "POST", $profile_data);

                        if (isset($insert_profile['error'])) {
                            $error_message = "Gagal membuat profil user.";
                        } else {
                            $process_status = 'success';
                            $is_new_registration = true;
                        }
                    }
                }
            }
        }

        // ===============================================================
        // 🚩 3. FINALIZE 
        // ===============================================================
        if ($process_status === 'success' && $auth_user_id) {
            // 3.A. Cek/Buat Affiliate Details
            $check_aff = supabase_fetch("/affiliate_details?user_id=eq.$auth_user_id&select=id");
            $aff_exists = $check_aff['data'][0] ?? null;
            $aff_success = false;

            if (!$aff_exists) {
                $referral_code = substr(strtoupper(md5(uniqid(rand(), true))), 0, 6);
                $aff_insert = supabase_fetch("/affiliate_details", "POST", [
                    'user_id' => $auth_user_id,
                    'referral_code' => $referral_code,
                    'wallet_balance' => 0
                ]);

                if ($aff_insert['status'] >= 200 && $aff_insert['status'] < 300) {
                    $aff_success = true;
                } else {
                    $error_message = "Gagal membuat dompet afiliasi.";
                }
            } else {
                $aff_success = true;
            }

            // 3.B. Update Status Pembelian jadi USED (HANYA JIKA USER BARU)
            if ($aff_success) {
                if ($is_new_registration) {
                    // Update Transactions (DOKU) -> Syarat status SUCCESS
                    supabase_fetch("/transactions?customer_email=eq.$email&status=eq.SUCCESS", "PATCH", ['login_status' => 'used']);

                    // Update Purchase Codes (Scalev) -> Syarat status unused
                    supabase_fetch("/purchase_codes?email=eq.$email", "PATCH", ['status' => 'used']);
                }
                
                $message = "<b>Aktivasi Berhasil!</b> Akun Afiliasi Anda telah aktif.";
                echo "<script>setTimeout(function(){ window.location.href = 'login.php'; }, 2000);</script>";
            }
        }
    } else {
        $error_message = "Mohon lengkapi email dan password.";
    }
} 
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Aktivasi Afiliasi | My Tahfidz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    
    <style>
        body.register-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .register-box { width: 420px; }
        @media (max-width: 576px) { .register-box { width: 90%; } }
    </style>
</head>

<body class="register-page">

    <div class="register-box">
        <div class="register-logo mb-4 text-center">
            <a href="#" class="fw-bold text-dark text-decoration-none">
                <img src="../../assets/images/logo-mytahfidz.png" alt="Logo" style="height: 50px; margin-bottom: 10px;">
                <br>Aktivasi <b>Affiliate</b>
            </a>
        </div>

        <div class="card card-outline card-success shadow-lg">
            <div class="card-header text-center">
                <h4 class="card-title mb-0 fw-bold">Daftar / Aktivasi Akun</h4>
            </div>
            
            <div class="card-body register-card-body">
                <p class="login-box-msg text-muted small">
                    Masukkan email yang digunakan saat pembelian paket untuk mengaktifkan fitur afiliasi.
                </p>

                <?php if ($error_message): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-circle-fill me-2"></i> <?= $error_message ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle-fill me-2"></i> <?= $message ?>
                        <br><small>Mengalihkan ke halaman login...</small>
                    </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-light border-end-0"><span class="bi bi-person"></span></div>
                            <input type="text" name="name" class="form-control border-start-0 ps-0" placeholder="Nama (Opsional)">
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-light border-end-0"><span class="bi bi-envelope"></span></div>
                            <input type="email" name="email" class="form-control border-start-0 ps-0" placeholder="Email Pembelian" required value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
                        </div>
                        <div class="form-text text-muted" style="font-size: 0.75rem;">
                            *Gunakan email yang terdaftar saat membeli paket.
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-light border-end-0"><span class="bi bi-lock-fill"></span></div>
                            <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="Password (Min. 6 Karakter)" required>
                        </div>
                    </div>

                    <div class="d-grid gap-2 mt-4">
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="bi bi-rocket-takeoff me-2"></i> Aktifkan Sekarang
                        </button>
                    </div>
                </form>

                <hr>
                
                <p class="text-center mb-0">
                    Sudah aktif? <a href="login.php" class="fw-bold text-success">Login disini</a>
                </p>
            </div>
        </div>
        <div class="text-center mt-3 text-muted small">&copy; <?= date('Y') ?> My Tahfidz</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
</body>
</html>