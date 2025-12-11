<?php
include '../../config.php';
include '../../functions.php';

// Cek sesi login
if (!isset($_SESSION['access_token']) || !isset($_SESSION['user_id'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$message = '';
$message_type = '';

// ==========================================================
// 1. AMBIL SALDO & DETAIL BANK
// ==========================================================
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=wallet_balance,bank_name,bank_account_number,bank_account_holder_name");
$aff_data = $aff_res['data'][0] ?? [];
$current_balance = $aff_data['wallet_balance'] ?? 0;

$default_bank_name = $aff_data['bank_name'] ?? '';
$default_account_number = $aff_data['bank_account_number'] ?? '';
$default_account_holder = $aff_data['bank_account_holder_name'] ?? '';

function rupiah($angka)
{
    return "Rp " . number_format($angka, 0, ',', '.');
}

// ==========================================================
// PROSES PENGAJUAN WITHDRAWAL
// ==========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $amount = (int)($_POST['amount_requested'] ?? 0);
    $bank_name = htmlspecialchars($_POST['bank_name'] ?? '');
    $account_number = htmlspecialchars($_POST['bank_account_number'] ?? '');
    $account_holder = htmlspecialchars($_POST['bank_account_holder_name'] ?? '');
    $minimum_withdrawal = 10000;

    if ($amount < $minimum_withdrawal) {
        $message = "Minimal penarikan adalah " . rupiah($minimum_withdrawal) . ".";
        $message_type = 'warning';
    } elseif ($amount > $current_balance) {
        $message = "Saldo tidak mencukupi. Saldo Anda: " . rupiah($current_balance);
        $message_type = 'danger';
    } elseif (empty($bank_name) || empty($account_number) || empty($account_holder)) {
        $message = "Mohon lengkapi semua data bank tujuan.";
        $message_type = 'danger';
    } else {
        $data = [
            'affiliate_user_id' => $user_id,
            'amount_requested' => $amount,
            'bank_name' => $bank_name,
            'bank_account_number' => $account_number,
            'bank_account_holder_name' => $account_holder,
            'status' => 'PENDING',
        ];

        $insert_res = supabase_fetch('/payouts', 'POST', $data);

        if (isset($insert_res['error'])) {
            $error_msg = $insert_res['error']['message'] ?? 'Unknown Error';
            $message = "Gagal mengajukan: " . $error_msg;
            $message_type = 'danger';
        } else {
            // Update saldo
            $new_balance = $current_balance - $amount;
            $update_data = ['wallet_balance' => $new_balance];
            $update_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id", 'PATCH', $update_data);

            if ($update_res['status'] != 204) {
                $message = "Pengajuan berhasil, namun gagal update saldo lokal. Hubungi Admin.";
                $message_type = 'warning';
            } else {
                $message = "<b>Berhasil!</b> Penarikan " . rupiah($amount) . " sedang diproses.";
                $message_type = 'success';
                $current_balance = $new_balance;
            }
        }
    }
}

// ==========================================================
// AMBIL RIWAYAT PENARIKAN
// ==========================================================
$history_res = supabase_fetch("/payouts?affiliate_user_id=eq.$user_id&order=created_at.desc");
$payout_history = $history_res['data'] ?? [];
?>

<!doctype html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <title>Tarik Dana | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />

    <style>
        /* Kartu Saldo Gradient */
        .card-wallet {
            background: linear-gradient(135deg, #0d6efd 0%, #0043a8 100%);
            color: white;
            border: none;
            border-radius: 15px;
            position: relative;
            overflow: hidden;
        }

        .card-wallet::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 150px;
            height: 150px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .wallet-label {
            opacity: 0.8;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .wallet-amount {
            font-size: 2.2rem;
            font-weight: 700;
            margin-top: 5px;
        }

        /* Dropdown Autocomplete */
        .dropdown-menu {
            border-radius: 0.5rem;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border: none;
        }

        .dropdown-item {
            cursor: pointer;
            padding: 10px 15px;
        }

        .dropdown-item:hover {
            background-color: #f8f9fa;
            color: #0d6efd;
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php $active = 'withdraw';
        include "../partials/navbar.php"; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-cash-coin me-2"></i>Withdrawal</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active">Withdraw</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">

                    <?= $message ? "<div class='alert alert-$message_type alert-dismissible fade show shadow-sm mb-4'><i class='bi bi-info-circle-fill me-2'></i>$message <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>" : '' ?>

                    <div class="row">
                        <div class="col-lg-4 col-md-12 mb-4 order-lg-last">
                            <div class="card card-wallet shadow mb-3">
                                <div class="card-body p-4">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div class="wallet-label">Saldo Tersedia</div>
                                        <i class="bi bi-wallet2 fs-4 opacity-50"></i>
                                    </div>
                                    <div class="wallet-amount"><?= rupiah($current_balance) ?></div>
                                    <div class="mt-4 pt-3 border-top border-white border-opacity-25 d-flex justify-content-between align-items-center">
                                        <small class="opacity-75">Min. Penarikan</small>
                                        <span class="fw-bold">Rp 10.000</span>
                                    </div>
                                </div>
                            </div>

                            <div class="card card-outline card-warning shadow-sm">
                                <div class="card-header bg-warning-subtle border-0">
                                    <h5 class="card-title text-warning-emphasis fw-bold"><i class="bi bi-lightbulb me-2"></i>Catatan Penting</h5>
                                </div>
                                <div class="card-body small text-muted">
                                    <ul class="mb-0 ps-3">
                                        <li class="mb-2">Pastikan Nama Pemilik Rekening sesuai dengan buku tabungan untuk menghindari penolakan.</li>
                                        <li class="mb-2">Proses penarikan membutuhkan waktu <b>1-3 hari kerja</b>.</li>
                                        <li>Biaya admin mungkin berlaku tergantung kebijakan bank.</li>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <div class="col-lg-8 col-md-12">

                            <div class="card card-outline card-primary shadow-sm mb-4">
                                <div class="card-header">
                                    <h3 class="card-title fw-bold">Formulir Pengajuan</h3>
                                </div>
                                <div class="card-body">
                                    <form method="POST">
                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nominal Penarikan</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light fw-bold">Rp</span>
                                                <input type="number" class="form-control" id="amount_requested" name="amount_requested" required min="10000" placeholder="0">
                                                <button class="btn btn-outline-secondary" type="button" onclick="setMaxAmount()">Tarik Semua</button>
                                            </div>
                                            <div class="form-text text-end">Maksimal: <?= rupiah($current_balance) ?></div>
                                        </div>

                                        <div class="mb-3 position-relative">
                                            <label class="form-label fw-bold">Nama Bank</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-bank"></i></span>
                                                <input type="text" class="form-control" id="bank_search_input"
                                                    placeholder="Ketik nama bank (Cth: BCA, Mandiri)..."
                                                    autocomplete="off"
                                                    value="<?= htmlspecialchars($default_bank_name) ?>">
                                                <input type="hidden" id="bank_name_hidden" name="bank_name"
                                                    value="<?= htmlspecialchars($default_bank_name) ?>">
                                            </div>

                                            <div id="bank_dropdown_results" class="list-group shadow"
                                                style="display:none; position: absolute; top: 100%; left: 0; width: 100%; z-index: 1050; max-height: 200px; overflow-y: auto;">
                                            </div>
                                        </div>

                                        <div class="mb-3">
                                            <label class="form-label fw-bold">Nomor Rekening</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-123"></i></span>
                                                <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" required placeholder="Contoh: 1234567890" value="<?= htmlspecialchars($default_account_number) ?>">
                                            </div>
                                        </div>

                                        <div class="mb-4">
                                            <label class="form-label fw-bold">Atas Nama (Pemilik)</label>
                                            <div class="input-group">
                                                <span class="input-group-text bg-light"><i class="bi bi-person"></i></span>
                                                <input type="text" class="form-control" id="bank_account_holder_name" name="bank_account_holder_name" required placeholder="Nama sesuai buku tabungan" value="<?= htmlspecialchars($default_account_holder) ?>">
                                            </div>
                                        </div>

                                        <div class="d-grid">
                                            <button type="submit" class="btn btn-primary btn-lg fw-bold" <?= $current_balance < 10000 ? 'disabled' : '' ?>>
                                                <i class="bi bi-send-fill me-2"></i> Ajukan Penarikan Sekarang
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="card card-outline card-secondary shadow-sm">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold"><i class="bi bi-clock-history me-2"></i>Riwayat Penarikan</h3>
                                </div>
                                <div class="card-body p-0 table-responsive">
                                    <table class="table table-striped table-hover align-middle mb-0 text-nowrap">
                                        <thead class="table-light">
                                            <tr>
                                                <th class="text-center">#</th>
                                                <th>Nominal</th>
                                                <th>Tujuan Transfer</th>
                                                <th class="text-center">Status</th>
                                                <th>Waktu</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (empty($payout_history)): ?>
                                                <tr>
                                                    <td colspan="5" class="text-center py-4 text-muted">Belum ada riwayat penarikan.</td>
                                                </tr>
                                            <?php else: ?>
                                                <?php $no = 1;
                                                foreach ($payout_history as $payout):
                                                    $status_class = match ($payout['status']) {
                                                        'PAID' => 'success',
                                                        'REJECTED' => 'danger',
                                                        default => 'warning',
                                                    };
                                                    $status_label = match ($payout['status']) {
                                                        'PAID' => 'Selesai',
                                                        'REJECTED' => 'Ditolak',
                                                        default => 'Menunggu',
                                                    };
                                                ?>
                                                    <tr>
                                                        <td class="text-center"><?= $no++ ?></td>
                                                        <td class="fw-bold text-dark"><?= rupiah($payout['amount_requested']) ?></td>
                                                        <td>
                                                            <div class="small fw-bold"><?= htmlspecialchars($payout['bank_name']) ?></div>
                                                            <div class="small text-muted"><?= htmlspecialchars($payout['bank_account_number']) ?></div>
                                                        </td>
                                                        <td class="text-center">
                                                            <span class="badge bg-<?= $status_class ?>-subtle text-<?= $status_class ?> border border-<?= $status_class ?>-subtle rounded-pill">
                                                                <?= $status_label ?>
                                                            </span>
                                                            <?php if ($payout['status'] === 'PAID' && !empty($payout['proof_of_transfer_url'])): ?>
                                                                <div class="mt-1"><a href="<?= htmlspecialchars($payout['proof_of_transfer_url']) ?>" target="_blank" class="text-decoration-none small"><i class="bi bi-image"></i> Bukti</a></div>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td class="small text-muted utc-time" data-utc-time="<?= htmlspecialchars($payout['created_at']) ?>">Memuat...</td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../../js/adminlte/adminlte.js"></script>

    <script>
        // --- 1. DATA BANK CADANGAN (FALLBACK) ---
        // Ini menjamin dropdown tetap jalan meskipun file JSON gagal diload / path salah
        const FALLBACK_BANKS = [{
                "kode_bank": "014",
                "nama_bank": "BCA"
            },
            {
                "kode_bank": "008",
                "nama_bank": "MANDIRI"
            },
            {
                "kode_bank": "009",
                "nama_bank": "BNI"
            },
            {
                "kode_bank": "002",
                "nama_bank": "BRI"
            },
            {
                "kode_bank": "451",
                "nama_bank": "BSI (SYARIAH INDONESIA)"
            },
            {
                "kode_bank": "022",
                "nama_bank": "CIMB NIAGA"
            },
            {
                "kode_bank": "147",
                "nama_bank": "MUAMALAT"
            },
            {
                "kode_bank": "013",
                "nama_bank": "PERMATA"
            },
            {
                "kode_bank": "011",
                "nama_bank": "DANAMON"
            },
            {
                "kode_bank": "213",
                "nama_bank": "BTPN (JENIUS)"
            },
            {
                "kode_bank": "050",
                "nama_bank": "KEB HANA"
            },
            {
                "kode_bank": "503",
                "nama_bank": "NOBU"
            },
            {
                "kode_bank": "501",
                "nama_bank": "JAGO"
            },
            {
                "kode_bank": "490",
                "nama_bank": "NEO COMMERCE"
            },
            {
                "kode_bank": "535",
                "nama_bank": "SEABANK"
            }
        ];

        const BANK_JSON_URL = '../../assets/jsons/bank_list.json'; // Pastikan path ini benar
        let allBanks = [];

        $(document).ready(function() {
            loadBankData();
            initializeAutocomplete();
            handleFormValidation();
            setMaxAmount(); // Inisialisasi tombol tarik semua
            convertUTCToLocalTime();
        });

        // --- 2. FUNGSI LOAD DATA (Prioritas JSON -> Fallback Manual) ---
        function loadBankData() {
            fetch(BANK_JSON_URL)
                .then(response => {
                    if (!response.ok) throw new Error('File JSON tidak ditemukan');
                    return response.json();
                })
                .then(data => {
                    // Normalisasi Data (Handle berbagai struktur JSON)
                    if (Array.isArray(data)) allBanks = data;
                    else if (data.banks) allBanks = data.banks;
                    else if (data.data) allBanks = data.data;
                    else allBanks = FALLBACK_BANKS; // Pakai cadangan jika format aneh

                    console.log("Bank Data Loaded via JSON:", allBanks.length);
                })
                .catch(error => {
                    console.warn('Gagal load JSON, menggunakan data fallback manual.', error);
                    allBanks = FALLBACK_BANKS; // GUNAKAN DATA MANUAL
                });
        }

        // --- 3. LOGIC AUTOCOMPLETE ---
        function initializeAutocomplete() {
            const $input = $('#bank_search_input');
            const $results = $('#bank_dropdown_results');
            const $hiddenInput = $('#bank_name_hidden');

            // Saat mengetik
            $input.on('input focus', function() {
                const query = $(this).val().toLowerCase().trim();
                $results.empty();
                $hiddenInput.val(''); // Reset hidden value saat user mengetik ulang

                if (query.length === 0) {
                    $results.hide();
                    return;
                }

                // Filter Data
                const filtered = allBanks.filter(b => b.nama_bank.toLowerCase().includes(query)).slice(0, 10);

                if (filtered.length > 0) {
                    filtered.forEach(bank => {
                        const html = `
                        <a href="#" class="list-group-item list-group-item-action py-2" data-name="${bank.nama_bank}">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <span class="fw-bold">${bank.nama_bank}</span>
                                
                            </div>
                        </a>
                    `;
                        $results.append(html);
                    });
                    $results.show();
                } else {
                    $results.html('<div class="list-group-item text-muted small">Bank tidak ditemukan.</div>').show();
                }
            });

            // Saat Item Dipilih
            $results.on('click', 'a', function(e) {
                e.preventDefault();
                const selectedName = $(this).data('name');
                $input.val(selectedName);
                $hiddenInput.val(selectedName);
                $results.hide();
            });

            // Sembunyikan jika klik di luar
            $(document).on('click', function(e) {
                if (!$(e.target).closest('.position-relative').length) {
                    $results.hide();
                }
            });
        }

        // --- 4. VALIDASI FORM SEBELUM SUBMIT ---
        function handleFormValidation() {
            $('form').on('submit', function(e) {
                const bankName = $('#bank_name_hidden').val();
                // Jika user mengetik manual tapi tidak klik dropdown, kita coba ambil value input teksnya
                if (!bankName) {
                    const manualInput = $('#bank_search_input').val().trim();
                    if (manualInput.length > 2) {
                        $('#bank_name_hidden').val(manualInput); // Izinkan input manual
                    } else {
                        alert("Mohon pilih atau isi nama bank dengan benar.");
                        e.preventDefault();
                    }
                }
            });
        }

        // --- 5. FITUR LAINNYA ---
        function setMaxAmount() {
            window.setMaxAmount = function() {
                const maxVal = <?= $current_balance ?>;
                document.getElementById('amount_requested').value = maxVal;
            }
        }

        function convertUTCToLocalTime() {
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: false
            };
            document.querySelectorAll('.utc-time').forEach(el => {
                const utcStr = el.getAttribute('data-utc-time');
                if (utcStr) {
                    el.textContent = new Date(utcStr).toLocaleString('id-ID', options);
                }
            });
        }
    </script>
</body>

</html>