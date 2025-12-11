<?php 
include '../../config.php';
include '../../functions.php';

if (!isset($_SESSION['access_token'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Ambil saldo
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=wallet_balance");
$aff_data = $aff_res['data'][0] ?? ['wallet_balance' => 0];
$current_balance = $aff_data['wallet_balance'] ?? 0;

// ==============================
// PAGINATION SETUP
// ==============================
$limit = 10; 
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $limit;

// Ambil total data untuk pagination
$count_res = supabase_fetch("/referrals?affiliate_user_id=eq.$user_id&select=id", "GET");
$total_rows = count($count_res['data'] ?? []);
$total_pages = max(1, ceil($total_rows / $limit));

// Ambil data referrals (pakai limit dan offset)
$query_params = "/referrals?affiliate_user_id=eq.$user_id"
    . "&select=commission_earned,transactions(products(name),customer_email)"
    . "&order=id.desc"
    . "&limit=$limit&offset=$offset";

$referrals_res = supabase_fetch($query_params, 'GET');
$referrals = $referrals_res['data'] ?? [];

$message = '';
if ($referrals_res['status'] != 200) {
    $message = "<div class='alert alert-info'>Gagal memuat data komisi atau belum ada komisi.</div>";
}

?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <title>Commission | My Tahfidz Affiliate</title>

    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
</head>

<body class="layout-fixed sidebar-open bg-body-tertiary">
<div class="app-wrapper">

<?php 
$active = 'affiliate_commission';
include "../partials/navbar.php"; 
?>

<main class="app-main">
    <div class="app-content-header">
        <div class="container-fluid">
            <div class="row">
                <div class="col-sm-6">
                    <h3 class="mb-0">Commission</h3>
                </div>
                <div class="col-sm-6 text-end">
                    <p class="mb-0 text-success">Saldo Anda: <strong>Rp <?= number_format($current_balance, 0, ',', '.'); ?></strong></p>

                </div>
            </div>
        </div>
    </div>

    <div class="app-content">
    <div class="container-fluid">

        <?= $message; ?>

        <div class="card mb-4">
           <div class="card-header">
    <h3 class="card-title">Riwayat Komisi Afiliasi</h3>

    <div class="card-tools">
        <ul class="pagination pagination-sm float-end">

            <!-- PREVIOUS -->
            <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                <a class="page-link" href="?page=<?= max(1, $page - 1) ?>">&laquo;</a>
            </li>

            <!-- PAGE NUMBERS -->
            <?php 
            // Show max 5 pages — cleaner
            $start = max(1, $page - 2);
            $end = min($total_pages, $page + 2);

            for ($i = $start; $i <= $end; $i++): 
            ?>
                <li class="page-item <?= ($page == $i ? 'active' : '') ?>">
                    <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                </li>
            <?php endfor; ?>

            <!-- NEXT -->
            <li class="page-item <?= ($page >= $total_pages ? 'disabled' : '') ?>">
                <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>">&raquo;</a>
            </li>
        </ul>
    </div>
</div>

            <div class="card-body p-0">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Produk</th>
                            <th>Pembeli</th>
                            <th>Komisi Anda</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($referrals)): ?>
                            <tr>
                                <td colspan="4" class="text-center py-4">Tidak ada data komisi.</td>
                            </tr>
                        <?php else: ?>
                            <?php 
                            $no = $offset + 1;
                            foreach ($referrals as $ref): 

                                if (!isset($ref['transactions'])) continue;

                                $trx = $ref['transactions'];
                                $product_name = $trx['products']['name'] ?? 'Produk (Data Hilang)';
                                $customer_email = $trx['customer_email'] ?? 'Pembeli Tidak Diketahui';
                                $commission_earned = $ref['commission_earned'] ?? 0;
                            ?>
                            <tr class="align-middle">
                                <td><?= $no++; ?></td>
                                <td><?= htmlspecialchars($product_name); ?></td>
                                <td><?= htmlspecialchars($customer_email); ?></td>
                                <td><b>Rp<?= number_format($commission_earned, 0, ',', '.'); ?></b></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>
    </div>
</main>

</div>

<script src="../../js/adminlte/adminlte.js"></script>

</body>
</html>
