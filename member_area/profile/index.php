<?php 
include '../../config.php'; 
include '../../functions.php'; 

// Cek autentikasi
if (!isset($_SESSION['access_token'])) { 
    header('Location: ../auth/login.php'); 
    exit; 
} 

$user_id = $_SESSION['user_id']; 
$message = []; 
$error = false; 

// --- FUNGSI UPDATE DATA --- 
function update_supabase_data($table, $user_id, $data) {
    global $error;
    $response = supabase_fetch("/{$table}?user_id=eq.{$user_id}", 'PATCH', $data);
    if (isset($response['status']) && $response['status'] != 204) {
        $error = true;
        $error_msg = $response['data']['message'] ?? 'Gagal memperbarui data.';
        return ['type' => 'danger', 'text' => "Gagal: " . htmlspecialchars($error_msg)];
    }
    return ['type' => 'success', 'text' => "Data berhasil diperbarui."];
}

// --- LOGIKA FORM SUBMISSION --- 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. UPDATE PROFILE
    if (isset($_POST['update_profile'])) {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $message[] = update_supabase_data('user_profile', $user_id, ['name' => $name]);
    }
    // 2. UPDATE BANK
    if (isset($_POST['update_bank'])) {
        $bank_name = filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $bank_account_number = filter_input(INPUT_POST, 'bank_account_number', FILTER_SANITIZE_NUMBER_INT);
        $bank_account_holder_name = filter_input(INPUT_POST, 'bank_account_holder_name', FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($bank_name)) {
            $error = true;
            $message[] = ['type' => 'danger', 'text' => "Mohon pilih bank dari daftar valid."];
        } else {
            $bank_data = [
                'bank_name' => $bank_name,
                'bank_account_number' => $bank_account_number,
                'bank_account_holder_name' => $bank_account_holder_name,
            ];
            $message[] = update_supabase_data('affiliate_details', $user_id, $bank_data);
        }
    }
}

// --- FETCH DATA --- 
$fetch_profile = supabase_fetch("/user_profile?user_id=eq.{$user_id}&select=name,email");
$fetch_affiliate = supabase_fetch("/affiliate_details?user_id=eq.{$user_id}&select=bank_name,bank_account_number,bank_account_holder_name");

$profile = $fetch_profile['data'][0] ?? null;
$affiliate = $fetch_affiliate['data'][0] ?? ['bank_name' => '', 'bank_account_number' => '', 'bank_account_holder_name' => ''];

if (!$profile) {
    $error = true;
    $message[] = ['type' => 'danger', 'text' => 'Gagal memuat data profil.'];
}
?> 

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Pengaturan Akun | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    
    <style>
        .profile-user-img {
            width: 100px;
            height: 100px;
            border: 3px solid #adb5bd;
            padding: 3px;
            object-fit: cover;
        }
        .list-group-item-action:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php $active = "profile"; include "../partials/navbar.php"; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h3 class="mb-0 fw-bold text-dark">Pengaturan Profil</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active">Profil</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    
                    <?php foreach ($message as $msg): ?>
                        <div class="alert alert-<?= $msg['type'] ?> alert-dismissible fade show shadow-sm" role="alert">
                            <i class="bi bi-info-circle-fill me-2"></i><?= htmlspecialchars($msg['text']) ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                        </div>
                    <?php endforeach; ?>

                    <?php if ($error && empty($profile)) exit; ?>

                    <div class="row">
                        <div class="col-lg-4 mb-4">
                            <div class="card card-outline card-primary shadow-sm h-100">
                                <div class="card-body box-profile">
                                    <div class="text-center mb-4">
                                        <img class="profile-user-img img-fluid rounded-circle"
                                             src="https://ui-avatars.com/api/?name=<?= urlencode($profile['name']) ?>&background=0d6efd&color=fff&size=128"
                                             alt="User profile picture">
                                        <h3 class="profile-username text-center mt-3 fw-bold"><?= htmlspecialchars($profile['name']) ?></h3>
                                        <p class="text-muted text-center">Affiliate Partner</p>
                                    </div>

                                    <hr>

                                    <form method="POST">
                                        <input type="hidden" name="update_profile" value="1">
                                        
                                        <div class="mb-3">
                                            <label class="form-label fw-bold small text-uppercase">Nama Lengkap</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($profile['name'] ?? ''); ?>" required>
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-bold small text-uppercase">Email Login</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-envelope"></i></span>
                                                <input type="email" class="form-control bg-light" value="<?= htmlspecialchars($profile['email'] ?? ''); ?>" disabled readonly>
                                            </div>
                                            <div class="form-text text-muted" style="font-size: 0.75rem;"><i class="bi bi-lock-fill"></i> Email tidak dapat diubah.</div>
                                        </div>

                                        <button type="submit" class="btn btn-primary w-100 fw-bold">
                                            <i class="bi bi-save me-1"></i> Simpan Profil
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 mb-4">
                            <div class="card card-outline card-success shadow-sm h-100">
                                <div class="card-header border-bottom">
                                    <h3 class="card-title fw-bold text-success"><i class="bi bi-bank2 me-2"></i>Rekening Pencairan</h3>
                                </div>
                                <div class="card-body">
                                    <div class="alert alert-light border-start border-success border-4 text-muted small mb-4">
                                        <i class="bi bi-info-circle-fill text-success me-1"></i>
                                        Data ini digunakan admin untuk mentransfer komisi Anda. Pastikan data valid.
                                    </div>

                                    <form method="POST" id="bank_form">
                                        <input type="hidden" name="update_bank" value="1">
                                        
                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-bold">Nama Bank</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                                <input type="text" class="form-control" id="bank_search_input" 
                                                       placeholder="Ketik untuk mencari (Cth: BCA, BRI)..." 
                                                       autocomplete="off" 
                                                       value="<?= htmlspecialchars($affiliate['bank_name'] ?? ''); ?>">
                                                <input type="hidden" id="bank_name_hidden" name="bank_name" 
                                                       value="<?= htmlspecialchars($affiliate['bank_name'] ?? ''); ?>">
                                            </div>
                                            <div id="bank_dropdown_results" class="list-group shadow" 
                                                 style="display:none; position: absolute; top: 100%; left: 0; width: 100%; z-index: 1050; max-height: 250px; overflow-y: auto;">
                                            </div>
                                        </div>

                                        <div class="row">
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Nomor Rekening</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light"><i class="bi bi-credit-card-2-front"></i></span>
                                                    <input type="text" class="form-control" name="bank_account_number" 
                                                           value="<?= htmlspecialchars($affiliate['bank_account_number'] ?? ''); ?>" required placeholder="Cth: 1234567890">
                                                </div>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <label class="form-label fw-bold">Atas Nama (Pemilik)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text bg-light"><i class="bi bi-person-vcard"></i></span>
                                                    <input type="text" class="form-control" name="bank_account_holder_name" 
                                                           value="<?= htmlspecialchars($affiliate['bank_account_holder_name'] ?? ''); ?>" required placeholder="Sesuai buku tabungan">
                                                </div>
                                            </div>
                                        </div>

                                        <hr class="my-4">

                                        <div class="d-flex justify-content-end">
                                            <button type="submit" class="btn btn-success fw-bold px-4">
                                                <i class="bi bi-check-circle-fill me-2"></i> Simpan Rekening
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div> </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../../js/adminlte/adminlte.js"></script>

    <script>
        // --- 1. DATA BANK CADANGAN (FALLBACK) ---
        // Sama seperti di withdrawal, ini menjamin dropdown tetap jalan
        const FALLBACK_BANKS = [
            { "nama_bank": "BCA" }, { "nama_bank": "MANDIRI" }, { "nama_bank": "BNI" },
            { "nama_bank": "BRI" }, { "nama_bank": "BSI (SYARIAH INDONESIA)" },
            { "nama_bank": "CIMB NIAGA" }, { "nama_bank": "JAGO" }, { "nama_bank": "SEABANK" },
            { "nama_bank": "PERMATA" }, { "nama_bank": "DANAMON" }, { "nama_bank": "BTPN (JENIUS)" }
        ];

        const BANK_JSON_URL = '../../assets/jsons/bank_list.json'; 
        let allBanks = [];

        $(document).ready(function() {
            loadBankData();
            initializeAutocomplete();
            
            // Validasi sebelum submit
            $('#bank_form').on('submit', function(e) {
                const hiddenVal = $('#bank_name_hidden').val();
                const visibleVal = $('#bank_search_input').val();
                
                // Jika hidden kosong tapi visible ada isinya (user ngetik manual tapi ga klik dropdown)
                if(!hiddenVal && visibleVal) {
                     $('#bank_name_hidden').val(visibleVal);
                } else if (!hiddenVal && !visibleVal) {
                    alert("Mohon isi nama bank.");
                    e.preventDefault();
                }
            });
        });

        // --- 2. LOAD DATA ---
        function loadBankData() {
            fetch(BANK_JSON_URL)
                .then(res => {
                    if (!res.ok) throw new Error('Failed');
                    return res.json();
                })
                .then(data => {
                    if (Array.isArray(data)) allBanks = data;
                    else if (data.banks) allBanks = data.banks;
                    else allBanks = FALLBACK_BANKS;
                })
                .catch(err => {
                    allBanks = FALLBACK_BANKS; // Gunakan fallback jika fetch gagal
                });
        }

        // --- 3. AUTOCOMPLETE LOGIC ---
        function initializeAutocomplete() {
            const $input = $('#bank_search_input');
            const $results = $('#bank_dropdown_results');
            const $hiddenInput = $('#bank_name_hidden');

            $input.on('input focus', function() {
                const query = $(this).val().toLowerCase().trim();
                $results.empty();
                $hiddenInput.val(''); // Reset hidden saat mengetik ulang

                if (query.length === 0) {
                    $results.hide();
                    return;
                }

                const filtered = allBanks.filter(b => b.nama_bank.toLowerCase().includes(query)).slice(0, 8);

                if (filtered.length > 0) {
                    filtered.forEach(bank => {
                        const html = `
                            <a href="#" class="list-group-item list-group-item-action py-2" data-name="${bank.nama_bank}">
                                <i class="bi bi-bank2 me-2 text-secondary"></i><span class="fw-bold">${bank.nama_bank}</span>
                            </a>
                        `;
                        $results.append(html);
                    });
                    $results.show();
                } else {
                    $results.html('<div class="list-group-item text-muted small">Bank tidak ditemukan.</div>').show();
                }
            });

            $results.on('click', 'a', function(e) {
                e.preventDefault();
                const selectedName = $(this).data('name');
                $input.val(selectedName);
                $hiddenInput.val(selectedName);
                $results.hide();
            });

            $(document).on('click', function(e) {
                if (!$(e.target).closest('.position-relative').length) {
                    $results.hide();
                }
            });
        }
    </script>
</body>
</html>