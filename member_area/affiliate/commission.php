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
$query_params = "/referrals?affiliate_user_id=eq.$user_id" .
    "&select=commission_earned,transactions(products(name),customer_email)" .
    "&order=id.desc" .
    "&limit=$limit&offset=$offset";

$referrals_res = supabase_fetch($query_params, 'GET');
$referrals = $referrals_res['data'] ?? [];

$message = '';
if ($referrals_res['status'] != 200) {
    $message = "<div class='alert alert-warning alert-dismissible fade show'><i class='bi bi-exclamation-triangle me-2'></i>Gagal memuat data komisi atau belum ada komisi. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}

$active = 'affiliate_commission';
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Komisi Saya | My Tahfidz Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    
    <style>
        .small-box {
            border-radius: 0.5rem;
            position: relative;
            display: block;
            margin-bottom: 20px;
            box-shadow: 0 1px 1px rgba(0,0,0,0.1);
            overflow: hidden;
            color: #fff;
        }
        .small-box .inner { padding: 20px; }
        .small-box h3 { font-size: 2.2rem; font-weight: 700; margin: 0 0 10px 0; white-space: nowrap; padding: 0; }
        .small-box p { font-size: 1rem; margin-bottom: 0; }
        .small-box .icon {
            position: absolute;
            top: 10px;
            right: 15px;
            z-index: 0;
            font-size: 60px;
            color: rgba(0, 0, 0, 0.15);
        }
        .table-avatar {
            width: 35px; height: 35px;
            background: #e9ecef;
            color: #495057;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin-right: 10px;
            font-size: 0.9rem;
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php include "../partials/navbar.php"; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h3 class="mb-0 fw-bold text-dark">Daftar Komisi</h3>
                        </div>
                        <div class="col-sm-6">
                            <ol class="breadcrumb float-sm-end">
                                <li class="breadcrumb-item"><a href="#">Home</a></li>
                                <li class="breadcrumb-item active" aria-current="page">Komisi</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <?= $message; ?>

                    <div class="row">
                        <div class="col-lg-4 col-md-6 col-12">
                            <div class="small-box text-bg-success">
                                <div class="inner">
                                    <h3>Rp <?= number_format($current_balance, 0, ',', '.'); ?></h3>
                                    <p>Saldo Dompet Saat Ini</p>
                                </div>
                                <div class="icon">
                                    <i class="bi bi-wallet2"></i>
                                </div>
                            </div>
                        </div>
                        </div>

                    <div class="card card-outline card-primary shadow-sm mb-4">
                        <div class="card-header border-0 pb-0">
                            <h3 class="card-title mt-1"><i class="bi bi-receipt me-2"></i>Riwayat Transaksi</h3>
                            <div class="card-tools">
                                <ul class="pagination pagination-sm m-0">
                                    <li class="page-item <?= ($page <= 1 ? 'disabled' : '') ?>">
                                        <a class="page-link" href="?page=<?= max(1, $page - 1) ?>"><i class="bi bi-chevron-left"></i></a>
                                    </li>
                                    
                                    <?php 
                                    $start = max(1, $page - 2);
                                    $end = min($total_pages, $page + 2);
                                    for ($i = $start; $i <= $end; $i++): ?>
                                        <li class="page-item <?= ($page == $i ? 'active' : '') ?>">
                                            <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                        </li>
                                    <?php endfor; ?>

                                    <li class="page-item <?= ($page >= $total_pages ? 'disabled' : '') ?>">
                                        <a class="page-link" href="?page=<?= min($total_pages, $page + 1) ?>"><i class="bi bi-chevron-right"></i></a>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="card-body p-0 table-responsive mt-3">
                            <table class="table table-striped table-hover align-middle mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th style="width: 50px;" class="text-center">#</th>
                                        <th>Produk Terjual</th>
                                        <th>Data Pembeli</th>
                                        <th class="text-end">Komisi Didapat</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($referrals)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-5 text-muted">
                                                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                                Belum ada transaksi masuk.
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $no = $offset + 1; 
                                        foreach ($referrals as $ref): 
                                            if (!isset($ref['transactions'])) continue;
                                            $trx = $ref['transactions'];
                                            $product_name = $trx['products']['name'] ?? 'Produk (Data Hilang)';
                                            $customer_email = $trx['customer_email'] ?? '-';
                                            $commission_earned = $ref['commission_earned'] ?? 0;
                                        ?>
                                        <tr>
                                            <td class="text-center fw-bold text-secondary"><?= $no++; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-primary-subtle text-primary rounded p-2 me-2">
                                                        <i class="bi bi-box-seam"></i>
                                                    </div>
                                                    <span class="fw-medium"><?= htmlspecialchars($product_name); ?></span>
                                                </div>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="table-avatar">
                                                        <i class="bi bi-person"></i>
                                                    </div>
                                                    <span class="text-muted small"><?= htmlspecialchars($customer_email); ?></span>
                                                </div>
                                            </td>
                                            <td class="text-end">
                                                <span class="badge text-bg-success fs-6 fw-normal">
                                                    + Rp<?= number_format($commission_earned, 0, ',', '.'); ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="card-footer clearfix bg-white">
                            <small class="text-muted">Menampilkan <?= count($referrals) ?> data dari total <?= $total_rows ?> transaksi.</small>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../../js/adminlte/adminlte.js"></script>
</body>
</html>