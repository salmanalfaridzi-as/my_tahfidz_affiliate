<?php 
include '../../config.php';
include '../../functions.php';

// Cek autentikasi
if (!isset($_SESSION['access_token'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = []; // Array untuk menyimpan pesan sukses/error
$error = false;

// --- FUNGSI UPDATE DATA KE SUPABASE (PATCH) ---
function update_supabase_data($table, $user_id, $data) {
    global $error;
    // Menggunakan supabase_fetch dengan method PATCH, mengasumsikan ia mengembalikan status
    $response = supabase_fetch("/{$table}?user_id=eq.{$user_id}", 'PATCH', $data);

    if (isset($response['status']) && $response['status'] != 204) { // 204 No Content adalah sukses untuk PATCH
        $error = true;
        $error_msg = $response['data']['message'] ?? 'Gagal memperbarui data. Cek logs.';
        return ['type' => 'danger', 'text' => "Update Gagal: " . htmlspecialchars($error_msg)];
    }
    return ['type' => 'success', 'text' => "Data berhasil diperbarui."];
}

// --- LOGIKA FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // ===================================================
    // 1. UPDATE DATA PROFIL DASAR (Nama/Email)
    // ===================================================
    if (isset($_POST['update_profile'])) {
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_SPECIAL_CHARS);
        $profile_data = ['name' => $name];
        $message[] = update_supabase_data('profiles', $user_id, $profile_data);
    }

    // ===================================================
    // 2. UPDATE DATA REKENING BANK
    // ===================================================
    if (isset($_POST['update_bank'])) {
        // bank_name diambil dari hidden input (diisi oleh JS setelah memilih dari daftar)
        $bank_name = filter_input(INPUT_POST, 'bank_name', FILTER_SANITIZE_SPECIAL_CHARS);
        $bank_account_number = filter_input(INPUT_POST, 'bank_account_number', FILTER_SANITIZE_NUMBER_INT);
        $bank_account_holder_name = filter_input(INPUT_POST, 'bank_account_holder_name', FILTER_SANITIZE_SPECIAL_CHARS);

        if (empty($bank_name)) {
             $error = true;
             $message[] = ['type' => 'danger', 'text' => "Mohon pilih bank dari daftar pencarian."];
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

// --- FETCH DATA USER TERKINI ---
// Fetch data dari dua tabel sekaligus
$fetch_profile = supabase_fetch("/user_profile?user_id=eq.{$user_id}&select=name,email");
$fetch_affiliate = supabase_fetch("/affiliate_details?user_id=eq.{$user_id}&select=bank_name,bank_account_number,bank_account_holder_name");

$profile = $fetch_profile['data'][0] ?? null;
$affiliate = $fetch_affiliate['data'][0] ?? null;

if (!$profile) {
    $error = true;
    $message[] = ['type' => 'danger', 'text' => 'Gagal memuat data profil.'];
}

$affiliate = $affiliate ?? [
    'bank_name' => '', 
    'bank_account_number' => '', 
    'bank_account_holder_name' => '' 
];

// --- Tentukan active menu untuk navbar ---
$active = 'affiliate_profile'; 
?>
<!doctype html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
<title>Profile Settings | My Tahfidz Affiliate</title>
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
<link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
<div class="app-wrapper">
    <?php $active = "profile"; include "../partials/navbar.php"; ?>
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-sm-6"><h3 class="mb-0">Pengaturan Profil</h3></div>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                <?php foreach ($message as $msg) {
                    echo "<div class='alert alert-{$msg['type']} alert-dismissible fade show' role='alert'>";
                    echo htmlspecialchars($msg['text']);
                    echo '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>';
                    echo '</div>';
                } if ($error && !empty($message)) exit; // Hentikan jika ada error fatal (setelah menampilkan pesan) ?>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0"><i class="bi bi-person-circle me-2"></i> Data Akun</h5>
                            </div>
                            <div class="card-body">
                                <form method="POST">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="mb-3">
                                        <label for="name" class="form-label">Nama Lengkap</label>
                                        <input type="text" class="form-control" id="name" name="name" value="<?php echo htmlspecialchars($profile['name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email (Tidak Dapat Diubah)</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($profile['email'] ?? ''); ?>" disabled>
                                    </div>
                                    <button type="submit" class="btn btn-primary float-end">Simpan Profil</button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0"><i class="bi bi-bank me-2"></i> Detail Rekening Bank</h5>
                            </div>
                            <div class="card-body">
                                <p class="text-muted small">Detail ini digunakan untuk pencairan komisi (Payout). Silakan pilih bank dari daftar pencarian.</p>
                                <form method="POST" id="bank_form">
                                    <input type="hidden" name="update_bank" value="1">
                                    
                                    <div class="mb-3">
                                        <label for="bank_search_input" class="form-label">Nama Bank</label>
                                        <div class="dropdown">
                                            <input type="text" class="form-control" id="bank_search_input" 
                                                   placeholder="Ketik untuk mencari bank..." required 
                                                   autocomplete="off" 
                                                   value="<?= htmlspecialchars($affiliate['bank_name'] ?? ''); ?>">
                                            
                                            <input type="hidden" id="bank_name_hidden" name="bank_name" 
                                                   value="<?= htmlspecialchars($affiliate['bank_name'] ?? ''); ?>">

                                            <div class="dropdown-menu" id="bank_dropdown_results" style="width: 100%; max-height: 250px; overflow-y: auto;">
                                                </div>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label for="bank_account_holder_name" class="form-label">Nama Pemilik Rekening</label>
                                        <input type="text" class="form-control" id="bank_account_holder_name" name="bank_account_holder_name" value="<?php echo htmlspecialchars($affiliate['bank_account_holder_name'] ?? ''); ?>" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="bank_account_number" class="form-label">Nomor Rekening</label>
                                        <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" value="<?php echo htmlspecialchars($affiliate['bank_account_number'] ?? ''); ?>" required>
                                    </div>
                                    <button type="submit" class="btn btn-success float-end">Simpan Rekening</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div> 
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script> 
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script> 
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script> 
<script src="../../js/adminlte/adminlte.js"></script> 

<script>
    // URL file JSON bank Anda
    const BANK_JSON_URL = '../../assets/jsons/bank_list.json';
    const defaultBankName = '<?= htmlspecialchars($affiliate['bank_name'] ?? '') ?>';
    let allBanks = []; 

    $(document).ready(function() {
        loadBankData();
        initializeAutocomplete();
        handleSubmitForm();
    });

    // 1. Mengambil data JSON Bank
    function loadBankData() {
        fetch(BANK_JSON_URL)
            .then(response => {
                if (!response.ok) throw new Error('Gagal memuat file JSON bank');
                return response.json();
            })
            .then(data => {
                let bankArray = [];
                
                if (Array.isArray(data)) {
                    bankArray = data; 
                } else if (typeof data === 'object' && data !== null) {
                    bankArray = data.banks || data.data || data.result || [];
                }
                
                if (!Array.isArray(bankArray)) {
                     console.error("Struktur JSON tidak valid.");
                     bankArray = [];
                }
                allBanks = bankArray;
            })
            .catch(error => {
                console.error('Fetch Error:', error);
            });
    }

    // 2. Logika Autocomplete
    function initializeAutocomplete() {
        const $input = $('#bank_search_input');
        const $results = $('#bank_dropdown_results');
        const $hiddenInput = $('#bank_name_hidden');

        // Fungsi untuk menampilkan hasil pencarian
        const showResults = (query) => {
            $results.empty().removeClass('show');
            const normalizedQuery = query.toLowerCase().trim();

            if (normalizedQuery.length === 0) return;

            const filteredBanks = allBanks.filter(bank => 
                bank.nama_bank.toLowerCase().includes(normalizedQuery)
            ).slice(0, 10); // Batasi hingga 10 hasil

            if (filteredBanks.length === 0) {
                $results.append('<a class="dropdown-item disabled">Tidak ada bank yang cocok.</a>');
            } else {
                filteredBanks.forEach(bank => {
                    const $item = $('<a class="dropdown-item">').text(bank.nama_bank);
                    $item.data('bank-name', bank.nama_bank);
                    $results.append($item);
                });
            }
            
            $results.addClass('show'); // Tampilkan dropdown
            
            // Atur posisi dropdown
            $results.css({
                'position': 'absolute',
                'z-index': 1000,
                'top': $input.outerHeight()
            });
        };

        // Event saat pengguna mengetik
        $input.on('input', function() {
            const query = $(this).val();
            $hiddenInput.val(''); // Reset hidden value saat mengetik
            showResults(query);
        });

        // Event saat item di dropdown diklik
        $results.on('click', '.dropdown-item', function() {
            if ($(this).hasClass('disabled')) return; 
            
            const bankName = $(this).data('bank-name');
            
            $input.val(bankName);      // Isi input utama
            $hiddenInput.val(bankName); // Isi hidden input untuk submit
            $results.removeClass('show'); // Sembunyikan dropdown
            $input.removeClass('is-invalid'); // Hapus feedback validasi jika ada
        });

        // Event untuk menyembunyikan dropdown saat klik di luar
        $(document).on('click', function(e) {
            if (!$(e.target).closest('.dropdown').length) {
                $results.removeClass('show');
            }
        });
    }

    // 3. Handle Submit Form (Memastikan bank sudah dipilih dari daftar)
    function handleSubmitForm() {
        const $form = $('#bank_form');
        const $input = $('#bank_search_input');
        const $hiddenInput = $('#bank_name_hidden');

        $form.on('submit', function(e) {
            const bankNameSent = $hiddenInput.val().trim();
            
            // Periksa apakah bank yang dipilih ada di daftar JSON (atau nilai awal dari DB)
            const isBankSelected = allBanks.some(bank => bank.nama_bank === bankNameSent) || bankNameSent === defaultBankName;
            
            // Tambahkan validasi jika bank_name_hidden kosong atau tidak valid
            if (!bankNameSent || (!isBankSelected && $input.val().trim() !== bankNameSent)) {
                
                // Cek final: Jika nilai input sama dengan nilai hidden (dan bukan kosong)
                // Ini menangani kasus nilai default dari PHP
                if ($input.val().trim() === bankNameSent && bankNameSent !== '') {
                    // Lolos
                } else {
                    alert("Mohon pilih bank tujuan dari daftar pencarian yang muncul, atau perbarui input.");
                    $input.addClass('is-invalid'); // Tambahkan feedback visual
                    $input.focus();
                    e.preventDefault();
                    return;
                }
            }
            
            $input.removeClass('is-invalid');
            // Form akan disubmit
        });
    }
</script>
</body>
</html>