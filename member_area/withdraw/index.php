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
// 1. AMBIL SALDO & DETAIL BANK DARI affiliate_details
// ==========================================================
// Method GET
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=wallet_balance,bank_name,bank_account_number,bank_account_holder_name");
$aff_data = $aff_res['data'][0] ?? [];

$current_balance = $aff_data['wallet_balance'] ?? 0;
// Data bank yang tersimpan (digunakan sebagai nilai default)
$default_bank_name = $aff_data['bank_name'] ?? '';
$default_account_number = $aff_data['bank_account_number'] ?? '';
$default_account_holder = $aff_data['bank_account_holder_name'] ?? '';


// Format angka
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
        $message = "Jumlah penarikan minimal adalah " . rupiah($minimum_withdrawal) . ".";
        $message_type = 'danger';
    } elseif ($amount > $current_balance) {
        $message = "Saldo Anda (" . rupiah($current_balance) . ") tidak mencukupi untuk penarikan sebesar " . rupiah($amount) . ".";
        $message_type = 'danger';
    } elseif (empty($bank_name) || empty($account_number) || empty($account_holder)) {
        $message = "Semua detail rekening bank harus diisi lengkap. Pastikan Anda telah memilih bank dari daftar pencarian.";
        $message_type = 'danger';
    } else {

        // Data untuk dimasukkan ke tabel payouts
        $data = [
            'affiliate_user_id' => $user_id,
            'amount_requested' => $amount,
            'bank_name' => $bank_name,
            'bank_account_number' => $account_number,
            'bank_account_holder_name' => $account_holder,
            'status' => 'PENDING',
        ];

        // --- REVISI: Menggunakan supabase_fetch dengan method POST untuk INSERT ---
        $insert_res = supabase_fetch('/payouts', 'POST', $data);

        // Supabase mengembalikan data saat INSERT berhasil, atau array dengan 'error'
        if (isset($insert_res['error'])) {
            var_dump($insert_res);
            die();
            $error_msg = $insert_res['error']['message'] ?? 'Unknown Error';
            $message = "Gagal mengajukan penarikan: " . $error_msg;
            $message_type = 'danger';
        } else {
            // Setelah pengajuan berhasil, KURANGI SALDO
            $new_balance = $current_balance - $amount;
            $update_data = ['wallet_balance' => $new_balance];

            $update_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id", 'PATCH', $update_data);

            // Cek jika update gagal
            if ($update_res['status'] != 204) {
                $error_msg = $update_res['data']['message'] ?? 'Unknown Update Error';
                $message = "Pengajuan berhasil, tetapi GAGAL MENGURANGI SALDO di database: " . $error_msg;
                $message_type = 'warning';
            } else {
                $message = "Permintaan penarikan sebesar " . rupiah($amount) . " berhasil diajukan dan akan diproses. Saldo Anda saat ini: " . rupiah($new_balance);
                $message_type = 'success';
                $current_balance = $new_balance;
            }
        }
    }
}


// ==========================================================
// AMBIL RIWAYAT PENARIKAN
// ==========================================================
// Method GET
$history_res = supabase_fetch("/payouts?affiliate_user_id=eq.$user_id&order=created_at.desc");
$payout_history = $history_res['data'] ?? [];
?>

<!doctype html>
<html lang="en">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />

    <title>Withdrawal | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />

    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php
        $active = 'withdraw';
        include "../partials/navbar.php";
        ?>
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <h3 class="mb-0">Ajukan Penarikan (Withdrawal)</h3>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">

                    <?php if ($message): ?>
                        <div class="alert alert-<?= $message_type ?> alert-dismissible fade show" role="alert">
                            <?= $message ?>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endif; ?>

                    <div class="card mb-4">
                        <div class="card-header">
                            <h5>Saldo Komisi Tersedia</h5>
                        </div>
                        <div class="card-body">
                            <h2 class="text-success">
                                <strong><?= rupiah($current_balance) ?></strong>
                            </h2>
                            <p class="text-muted">Minimal penarikan: <?= rupiah(10000) ?></p>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Formulir Pengajuan Penarikan</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST">

                                <div class="mb-3">
                                    <label for="amount_requested" class="form-label">Jumlah Penarikan (Rupiah)</label>
                                    <input type="number" class="form-control" id="amount_requested" name="amount_requested" required min="10000"
                                        placeholder="Minimal Rp 10.000">
                                    <div class="form-text">Maksimal: <?= rupiah($current_balance) ?></div>
                                </div>

                                <div class="mb-3">
                                    <label for="bank_search_input" class="form-label">Nama Bank Tujuan (Cari)</label>

                                    <div class="dropdown">
                                        <input type="text" class="form-control" id="bank_search_input"
                                            placeholder="Ketik untuk mencari bank..." required
                                            autocomplete="off"
                                            value="<?= htmlspecialchars($default_bank_name) ?>">

                                        <input type="hidden" id="bank_name_hidden" name="bank_name"
                                            value="<?= htmlspecialchars($default_bank_name) ?>">

                                        <div class="dropdown-menu" id="bank_dropdown_results" style="width: 100%; max-height: 250px; overflow-y: auto;">
                                        </div>
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="bank_account_number" class="form-label">Nomor Rekening Tujuan</label>
                                    <input type="text" class="form-control" id="bank_account_number" name="bank_account_number" required
                                        placeholder="Cth: 1234567890" value="<?= htmlspecialchars($default_account_number) ?>">
                                </div>

                                <div class="mb-3">
                                    <label for="bank_account_holder_name" class="form-label">Nama Pemilik Rekening</label>
                                    <input type="text" class="form-control" id="bank_account_holder_name" name="bank_account_holder_name" required
                                        placeholder="Sesuai nama di buku tabungan" value="<?= htmlspecialchars($default_account_holder) ?>">
                                </div>

                                <button type="submit" class="btn btn-primary"
                                    <?= $current_balance < 10000 ? 'disabled' : '' ?>>
                                    Ajukan Penarikan
                                </button>
                                <?php if ($current_balance < 10000): ?>
                                    <small class="text-danger ms-2">Minimal saldo untuk penarikan adalah Rp 10.000.</small>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>

                    <div class="card mt-4">
                        <div class="card-header">
                            <h5>Riwayat Permintaan Penarikan</h5>
                        </div>
                        <div class="card-body">
                            <?php if (empty($payout_history)): ?>
                                <p class="text-muted">Anda belum memiliki riwayat permintaan penarikan.</p>
                            <?php else: ?>
                                <table class="table table-bordered table-striped">
                                    <thead>
                                        <tr>
                                            <th>No.</th>
                                            <th>Jumlah</th>
                                            <th>Rekening Tujuan</th>
                                            <th>Status</th>
                                            <th>Tanggal Ajuan</th>
                                            <th>Bukti Transfer</th> </tr>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        $no = 1; // Inisialisasi Nomor Urut
                                        foreach ($payout_history as $payout):

                                            // Status badge logic
                                            $status_badge = match ($payout['status']) {
                                                'PAID' => '<span class="badge text-bg-success">PAID</span>',
                                                'REJECTED' => '<span class="badge text-bg-danger">REJECTED</span>',
                                                default => '<span class="badge text-bg-warning">PENDING</span>',
                                            };

                                            // Logika Timezone (Tetap menggunakan class utc-time untuk konversi JS)
                                            // Kita tidak perlu memformat tanggal di sini, biarkan JS yang menangani.
                                        ?>
                                            <tr>
                                                <td><?= $no++ ?></td>
                                                <td><?= rupiah($payout['amount_requested']) ?></td>
                                                <td><?= htmlspecialchars($payout['bank_name']) . ' - ' . htmlspecialchars($payout['bank_account_number']) ?></td>
                                                <td><?= $status_badge ?></td>
                                                <td class="utc-time" data-utc-time="<?= htmlspecialchars($payout['created_at']) ?>">Memuat waktu lokal...</td>
                                                <td>
                                            <?php 
                                            if ($payout['status'] === 'PAID' && !empty($payout['proof_of_transfer_url'])): ?>
                                                <a href="<?= htmlspecialchars($payout['proof_of_transfer_url']) ?>" 
                                                   target="_blank" class="btn btn-sm btn-info">
                                                    Lihat Gambar
                                                </a>
                                            <?php else: ?>
                                                -
                                            <?php endif; ?>
                                        </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php endif; ?>
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
        function convertUTCToLocalTime() {
            // Opsi format tanggal dan waktu lokal yang ramah pengguna
            const options = {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false
            };

            // Loop melalui semua elemen yang berisi waktu UTC
            document.querySelectorAll('.utc-time').forEach(element => {
                const utcTimeStr = element.getAttribute('data-utc-time');

                if (utcTimeStr) {
                    // Buat objek Date. Browser secara otomatis akan mengasumsikan ini UTC
                    // jika formatnya ISO 8601 (seperti dari Supabase) dan mengonversinya saat di-output.
                    const localDate = new Date(utcTimeStr);

                    // Gunakan toLocaleString() untuk format waktu lokal browser
                    // Anda bisa menyesuaikan 'en-GB' dengan locale yang diinginkan (misal: 'id-ID')
                    const formattedTime = localDate.toLocaleString('id-ID', options);

                    // Dapatkan nama timezone, cth: "WIB", "PST", dll.
                    const timeZoneName = Intl.DateTimeFormat('id-ID', {
                        timeZoneName: 'short'
                    }).format(localDate).split(' ').pop();

                    element.textContent = `${formattedTime} ${timeZoneName}`;
                }
            });
        }

        $(document).ready(function() {
            loadBankData();
            initializeAutocomplete();
            handleSubmitForm();

            // Panggil fungsi konversi waktu setelah dokumen siap
            convertUTCToLocalTime();
        });
        // URL file JSON bank Anda
        const BANK_JSON_URL = '../../assets/jsons/bank_list.json';
        const defaultBankName = '<?= htmlspecialchars($default_bank_name) ?>';
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
                $hiddenInput.val('');
                showResults(query);
            });

            // Event saat item di dropdown diklik
            $results.on('click', '.dropdown-item', function() {
                if ($(this).hasClass('disabled')) return;

                const bankName = $(this).data('bank-name');

                $input.val(bankName);
                $hiddenInput.val(bankName);
                $results.removeClass('show');
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
            const $form = $('form');
            const $input = $('#bank_search_input');
            const $hiddenInput = $('#bank_name_hidden');

            $form.on('submit', function(e) {
                const bankNameSent = $hiddenInput.val().trim();

                if (!bankNameSent) {
                    alert("Mohon pilih bank tujuan dari daftar pencarian yang muncul.");
                    $input.focus();
                    e.preventDefault();
                    return;
                }

                // Hidden input sudah memiliki name="bank_name" dan akan dikirim ke PHP.
            });
        }
    </script>
</body>

</html>