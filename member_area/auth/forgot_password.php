<?php 
include '../../config.php'; 
include '../../functions.php'; 

// Cek sesi login
if (isset($_SESSION['access_token'])) { 
    header('Location: ../home/index.php'); 
    exit; 
} 

$message = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); 
    
    if (!$email) { 
        $message = alert_error('Format email tidak valid.'); 
    } else { 
        // URL Redirect (Pastikan BASE_URL didefinisikan di config.php, jika belum, ganti dengan URL manual)
        // Contoh: https://websiteanda.com/member-area/auth/reset_password.php
        $redirect_to = defined('BASE_URL') ? BASE_URL . "/auth/reset_password.php" : "http://localhost/member-area/auth/reset_password.php"; 

        $url = SUPABASE_URL . '/auth/v1/recover'; 
        $headers = [ 
            'Content-Type: application/json', 
            'apikey: ' . SUPABASE_KEY, 
        ]; 
        
        $data = [ 
            'email' => $email, 
            'gotrue_admin_api_key' => SUPABASE_SERVICE_KEY, 
            'redirect_to' => $redirect_to, 
        ]; 

        $ch = curl_init($url); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_POST, true); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); 
        
        $response = curl_exec($ch); 
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        $response_data = json_decode($response, true); 
        curl_close($ch); 

        if ($status_code >= 200 && $status_code < 300) { 
            $message = alert_success("Link reset telah dikirim ke <b>$email</b>.<br>Cek Inbox/Spam email Anda."); 
            // Kosongkan email agar form bersih
            $_POST['email'] = '';
        } else { 
            $error_detail = $response_data['msg'] ?? 'Terjadi kesalahan sistem.'; 
            $message = alert_error("Gagal mengirim link. ($error_detail)"); 
        } 
    } 
} 

// --- FUNGSI ALERT YANG DIPERBAIKI (DISMISSIBLE) ---
function alert_success($msg) { 
    return "
    <div class='alert alert-success alert-dismissible fade show' role='alert'>
        <div class='d-flex align-items-center'>
            <i class='bi bi-check-circle-fill fs-4 me-2'></i>
            <div>$msg</div>
        </div>
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>"; 
} 

function alert_error($msg) { 
    return "
    <div class='alert alert-danger alert-dismissible fade show' role='alert'>
        <div class='d-flex align-items-center'>
            <i class='bi bi-exclamation-triangle-fill fs-4 me-2'></i>
            <div>$msg</div>
        </div>
        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
    </div>"; 
} 
?> 

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Lupa Password | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    
    <style>
        /* Background gradient halus */
        body.login-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box { width: 400px; }
        @media (max-width: 576px) { .login-box { width: 90%; margin-top: 20px; } }
    </style>
</head>

<body class="login-page">
    
    <div class="login-box">
        <div class="login-logo mb-4 text-center">
            <a href="#" class="fw-bold text-dark text-decoration-none">
                <img src="../../assets/images/logo-mytahfidz.png" alt="Logo" style="height: 50px; margin-bottom: 10px;">
                <br>My Tahfidz Affiliate
            </a>
        </div>

        <div class="card card-outline card-warning shadow-lg">
            <div class="card-header text-center">
                <h4 class="card-title mb-0 fw-bold">Reset Password</h4>
            </div>
            
            <div class="card-body">
                <p class="login-box-msg text-muted small">
                    Lupa kata sandi Anda? Masukkan alamat email Anda, kami akan mengirimkan link untuk meresetnya.
                </p>

                <?= $message ?>

                <form action="" method="post">
                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-light border-end-0">
                                <span class="bi bi-envelope"></span>
                            </div>
                            <input type="email" name="email" class="form-control border-start-0 ps-0" 
                                   placeholder="Email Terdaftar" 
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-warning fw-bold">
                            <i class="bi bi-send me-1"></i> Kirim Link Reset
                        </button>
                    </div>
                </form>

                <div class="mt-4 text-center">
                    <p class="mb-0">Sudah ingat password?</p>
                    <a href="login.php" class="text-decoration-none fw-bold text-primary">
                        <i class="bi bi-arrow-left"></i> Kembali ke Login
                    </a>
                </div>
            </div>
        </div>
        
        <div class="text-center mt-3 text-muted small">
            &copy; <?= date('Y') ?> My Tahfidz. All rights reserved.
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
</body>
</html>