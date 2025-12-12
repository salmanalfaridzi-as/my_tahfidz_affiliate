<?php 

include '../../config.php'; 
include '../../functions.php'; 

// Jika pengguna sudah login, tendang ke dashboard
if (isset($_SESSION['access_token'])) { 
    header('Location: ../home/index.php'); 
    exit; 
} 

$message = ''; 
$is_password_updated = false; 

// --- 1. MENANGKAP TOKEN (Dari URL ?access_token=...) ---
$token = filter_input(INPUT_GET, 'access_token', FILTER_SANITIZE_STRING); 
$token_type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING); 

// Cek validitas token dasar
$can_reset = !empty($token) && $token_type === 'recovery'; 

// --- 2. PROSES RESET PASSWORD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_reset) { 
    $new_password = filter_input(INPUT_POST, 'new_password', FILTER_SANITIZE_STRING); 
    $confirm_password = filter_input(INPUT_POST, 'confirm_password', FILTER_SANITIZE_STRING); 
    $post_token = filter_input(INPUT_POST, 'token', FILTER_SANITIZE_STRING); // Ambil dari hidden input

    if (empty($new_password) || empty($confirm_password)) { 
        $message = alert_error('Kata sandi tidak boleh kosong.'); 
    } elseif ($new_password !== $confirm_password) { 
        $message = alert_error('Konfirmasi kata sandi tidak cocok.'); 
    } elseif (strlen($new_password) < 6) { 
        $message = alert_error('Kata sandi minimal 6 karakter.'); 
    } else { 
        // Hit ke Supabase
        $url = SUPABASE_URL . '/auth/v1/user'; 
        $headers = [ 
            'Content-Type: application/json', 
            'apikey: ' . SUPABASE_KEY, 
            'Authorization: Bearer ' . $post_token, // PENTING: Token recovery dipakai sebagai Bearer
        ]; 
        $data = ['password' => $new_password]; 

        $ch = curl_init($url); 
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); 
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); 
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT'); 
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data)); 
        
        $response = curl_exec($ch); 
        $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE); 
        $response_data = json_decode($response, true); 
        curl_close($ch); 

        if ($status_code === 200) { 
            $is_password_updated = true; 
            $message = alert_success("<b>Berhasil!</b> Kata sandi telah diperbarui. Silakan login."); 
        } else { 
            $err = $response_data['msg'] ?? 'Token kadaluarsa.'; 
            $message = alert_error("Gagal mereset. ($err)"); 
            $can_reset = false; 
        } 
    } 
} 

// --- FUNGSI ALERT UI ---
function alert_success($msg) { 
    return "<div class='alert alert-success alert-dismissible fade show'><i class='bi bi-check-circle-fill me-2'></i>$msg</div>"; 
} 
function alert_error($msg) { 
    return "<div class='alert alert-danger alert-dismissible fade show'><i class='bi bi-exclamation-triangle-fill me-2'></i>$msg</div>"; 
} 
?> 

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Ubah Kata Sandi | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    
    <style>
        body.login-page {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-box { width: 400px; }
        @media (max-width: 576px) { .login-box { width: 90%; } }
        .input-group-text { cursor: pointer; }
    </style>
</head>

<body class="login-page">

    <div class="login-box">
        <div class="login-logo mb-4 text-center">
            <a href="#" class="fw-bold text-dark text-decoration-none">
                <br>My Tahfidz Affiliate
            </a>
        </div>

        <div class="card card-outline card-primary shadow-lg">
            <div class="card-header text-center">
                <h4 class="card-title mb-0 fw-bold">Buat Kata Sandi Baru</h4>
            </div>
            
            <div class="card-body">
                <?= $message ?>

                <?php if ($can_reset && !$is_password_updated): ?>
                    <p class="login-box-msg text-muted small">
                        Masukkan kata sandi baru Anda yang aman.
                    </p>

                    <form method="POST" action="">
                        <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

                        <div class="mb-3">
                            <label class="form-label small fw-bold">Kata Sandi Baru</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-lock"></i></span>
                                <input type="password" class="form-control" id="new_password" name="new_password" required placeholder="Minimal 6 karakter">
                                <span class="input-group-text bg-white" onclick="togglePass('new_password')"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold">Ulangi Kata Sandi</label>
                            <div class="input-group">
                                <span class="input-group-text bg-white"><i class="bi bi-lock-fill"></i></span>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" required placeholder="Ketik ulang password">
                                <span class="input-group-text bg-white" onclick="togglePass('confirm_password')"><i class="bi bi-eye"></i></span>
                            </div>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary fw-bold">
                                <i class="bi bi-save me-1"></i> Simpan Password
                            </button>
                        </div>
                    </form>

                <?php elseif ($is_password_updated): ?>
                    <div class="text-center mt-3">
                        <a href="login.php" class="btn btn-success w-100 fw-bold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Login Sekarang
                        </a>
                    </div>

                <?php else: ?>
                    <div class="alert alert-warning text-center small">
                        Link reset tidak valid atau sudah kadaluarsa.
                    </div>
                    <div class="text-center">
                        <a href="forgot_password.php" class="btn btn-outline-secondary w-100">
                            Kirim Ulang Link
                        </a>
                    </div>
                <?php endif; ?>

                <div class="mt-4 text-center">
                    <a href="login.php" class="text-decoration-none small">
                        <i class="bi bi-arrow-left"></i> Kembali ke Halaman Login
                    </a>
                </div>
            </div>
        </div>
        <div class="text-center mt-3 text-muted small">&copy; <?= date('Y') ?> My Tahfidz</div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>

    <script>
        // 1. Script Toggle Password (Show/Hide)
        function togglePass(id) {
            const input = document.getElementById(id);
            input.type = input.type === "password" ? "text" : "password";
        }

        // 2. SCRIPT PENTING: MENANGKAP HASH (#) DARI SUPABASE
        // Supabase mengirim token seperti: .../reset_password.php#access_token=xyz&type=recovery
        // PHP tidak bisa baca Hash (#). Kita harus ubah jadi Query (?) via Javascript lalu reload.
        (function() {
            if (window.location.hash) {
                const hash = window.location.hash.substring(1); // Ambil string setelah #
                const params = new URLSearchParams(hash);
                
                // Jika ada access_token di hash, kita reload halaman dengan format query string (?)
                if (params.get('access_token')) {
                    // Pindahkan parameter dari hash ke search (query params)
                    window.location.href = window.location.pathname + "?" + hash;
                }
            }
        })();
    </script>
</body>
</html>