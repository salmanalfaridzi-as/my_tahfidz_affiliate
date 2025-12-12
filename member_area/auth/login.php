<?php 
// pages/login.php
include '../../config.php'; 
include '../../functions.php'; 

// Cek jika pengguna sudah login, alihkan ke dashboard
if (isset($_SESSION['access_token'])) { 
    header('Location: ../home/index.php'); // Pastikan path redirect benar
    exit; 
} 

$message = ''; 
$email_input = ''; 

if ($_SERVER['REQUEST_METHOD'] === 'POST') { 
    $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL); 
    $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS); 
    $email_input = $email; 

    if ($email && $password) { 
        $auth_data = [ 'email' => $email, 'password' => $password ]; 
        
        // 1. Login ke Supabase Auth
        $response = supabase_auth_request('/token?grant_type=password', $auth_data); 

        if ($response['status'] == 200) { 
            // Ambil data sementara
            $access_token = $response['data']['access_token'];
            $user_id = $response['data']['user']['id'];

            // 2. CEK ROLE DI TABLE USER_PROFILE 
            // Kita harus melakukan request manual ke database menggunakan token yang baru didapat
            $url = SUPABASE_URL . "/rest/v1/user_profile?user_id=eq." . $user_id . "&select=role";
            
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'apikey: ' . SUPABASE_KEY,
                'Authorization: Bearer ' . $access_token, // Gunakan token user yg baru login
                'Content-Type: application/json'
            ]);
            
            $profile_response = curl_exec($ch);
            $profile_data = json_decode($profile_response, true);
            curl_close($ch);

            // Cek apakah data profile ada dan role-nya 'affiliator'
            if (!empty($profile_data) && isset($profile_data[0]['role']) && $profile_data[0]['role'] === 'affiliator') {
                
                // --- LOGIN SUKSES & ROLE VALID ---
                $_SESSION['access_token'] = $access_token; 
                $_SESSION['refresh_token'] = $response['data']['refresh_token']; 
                $_SESSION['user_id'] = $user_id; 
                $_SESSION['user_email'] = $response['data']['user']['email']; 

                // Redirect ke Dashboard
                header('Location: ../home/index.php'); // Sesuaikan path dashboard Anda
                exit;

            } else {
                // --- LOGIN GAGAL: ROLE TIDAK COCOK ---
                $message = "Akses Ditolak: Akun Anda tidak terdaftar sebagai Affiliator.";
            }

        } else { 
            // --- LOGIN GAGAL: AUTH ERROR ---
            $error_msg = $response['data']['error_description'] ?? 'Email atau password salah.'; 
            $message = "Login Gagal: " . htmlspecialchars($error_msg); 
        } 
    } else { 
        $message = "Mohon masukkan email dan password yang valid."; 
    } 
} 
?> 
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Login | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    
    <style>
        /* Custom Style untuk mempercantik halaman login */
        body.login-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box {
            width: 400px;
        }
        @media (max-width: 576px) {
            .login-box {
                width: 90%;
            }
        }
    </style>
</head>

<body class="login-page">

    <div class="login-box">
        <div class="login-logo mb-4 text-center">
            <a href="#" class="fw-bold text-dark text-decoration-none">
                <img src="../../assets/images/logo-mytahfidz.png" alt="Logo" style="height: 50px; margin-bottom: 10px;">
                <br>
                My Tahfidz <b>Affiliate</b>
            </a>
        </div>

        <div class="card card-outline card-primary shadow-lg">
            <div class="card-header text-center">
                <h3 class="card-title mb-0 fw-bold">Sign In</h3>
            </div>
            
            <div class="card-body login-card-body">
                <p class="login-box-msg text-muted">Masuk untuk mengakses area afiliasi</p>

                <?php if ($message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i> <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
                <?php endif; ?>

                <form method="post">
                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-light border-end-0">
                                <span class="bi bi-envelope"></span>
                            </div>
                            <input type="email" class="form-control border-start-0 ps-0" id="email" name="email" placeholder="Email Address" value="<?php echo htmlspecialchars($email_input); ?>" required />
                        </div>
                    </div>

                    <div class="mb-3">
                        <div class="input-group">
                            <div class="input-group-text bg-light border-end-0">
                                <span class="bi bi-lock-fill"></span>
                            </div>
                            <input type="password" class="form-control border-start-0 ps-0" id="password" name="password" placeholder="Password" required />
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-12">
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary fw-bold">
                                    <i class="bi bi-box-arrow-in-right me-2"></i> Sign In
                                </button>
                            </div>
                        </div>
                    </div>
                </form>

                <div class="mt-4 text-center">
                    <p class="mb-1">
                        <a href="forgot_password.php" class="text-decoration-none">Lupa kata sandi?</a>
                    </p>
                    <p class="mb-0">
                        Belum punya akun? <a href="register.php" class="text-decoration-none fw-bold">Daftar sekarang</a>
                    </p>
                </div>
            </div>
            </div>
        
        <div class="text-center mt-3 text-muted small">
            &copy; <?php echo date('Y'); ?> My Tahfidz. All rights reserved.
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../../js/adminlte/adminlte.js"></script>
</body>
</html>