<?php 
include '../../config.php'; 
include '../../functions.php'; 

// Cek sesi login
if (!isset($_SESSION['access_token']) || !isset($_SESSION['user_id'])) { 
    header('Location: ../auth/login.php'); 
    exit; 
} 

$user_id = $_SESSION['user_id']; 

// ==========================================================
// 1. AMBIL DATA AFFILIATE USER & STATISTIK UTAMA
// ==========================================================
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=referral_code,wallet_balance");
$aff_data = $aff_res['data'][0] ?? ['referral_code' => 'N/A', 'wallet_balance' => 0];
$referral_code = $aff_data['referral_code'];
$current_balance = $aff_data['wallet_balance'];

$stats_res = supabase_fetch("/referrals?affiliate_user_id=eq.$user_id&select=id,commission_earned,transactions(total_amount,status,paid_at)");
$stats = $stats_res['data'] ?? [];

$total_lead = count($stats);
$total_sales = 0;
$total_profit = 0;
$total_commission = 0;

foreach ($stats as $row) {
    $total_commission += $row['commission_earned'];
    $txn = $row['transactions'] ?? null;
    if ($txn && ($txn['status'] === 'PAID' || $txn['status'] === 'SETTLED')) {
        $total_sales++;
        $total_profit += $txn['total_amount'];
    }
}

function rupiah($angka) { return "Rp " . number_format($angka, 0, ',', '.'); }

function filter_sales_by_period($all_stats, $period) {
    $start_of_period = null;
    $now = new DateTime();
    if ($period === 'today') {
        $start_of_period = (new DateTime())->setTime(0, 0, 0);
    } elseif ($period === 'month') {
        $start_of_period = (new DateTime())->setDate($now->format('Y'), $now->format('m'), 1)->setTime(0, 0, 0);
    }
    $filtered_stats = ['lead' => 0, 'sales' => 0, 'profit' => 0, 'commission' => 0];
    foreach ($all_stats as $row) {
        $filtered_stats['lead']++;
        $txn = $row['transactions'] ?? null;
        $is_paid = $txn && ($txn['status'] === 'PAID' || $txn['status'] === 'SETTLED');
        if ($is_paid) {
            $paid_at_ts = strtotime($txn['paid_at'] ?? null);
            if ($period === 'all' || ($paid_at_ts && $paid_at_ts >= $start_of_period->getTimestamp())) {
                $filtered_stats['sales']++;
                $filtered_stats['profit'] += $txn['total_amount'];
                $filtered_stats['commission'] += $row['commission_earned'];
            }
        }
    }
    return $filtered_stats;
}

$stats_today = filter_sales_by_period($stats, 'today');
$stats_month = filter_sales_by_period($stats, 'month');
$stats_all = ['lead' => $total_lead, 'sales' => $total_sales, 'profit' => $total_profit, 'commission' => $total_commission];

// ==========================================================
// 2. DATA CHARTS
// ==========================================================
function get_date_n_ago($unit, $value) {
    $date = new DateTime();
    $date->modify("-{$value} {$unit}");
    return $date->getTimestamp();
}

$all_sales = array_filter($stats, function ($ref) {
    $txn = $ref['transactions'] ?? null;
    return $txn && ($txn['status'] === 'PAID' || $txn['status'] === 'SETTLED') && !empty($txn['paid_at']);
});

// CHART 1: 30 HARI
$daily_data = [];
$date_format_daily = 'Y-m-d';
$thirty_days_ago_ts = get_date_n_ago('days', 30);
$date_series_30_days = [];
for ($i = 29; $i >= 0; $i--) {
    $date = (new DateTime())->modify("-$i days");
    $date_key = $date->format($date_format_daily);
    $date_series_30_days[] = $date->format('d/M');
    $daily_data[$date_key] = ['profit' => 0, 'commission' => 0];
}
foreach ($all_sales as $ref) {
    $txn = $ref['transactions'];
    $paid_ts = strtotime($txn['paid_at']);
    if ($paid_ts >= $thirty_days_ago_ts) {
        $paid_date_key = date($date_format_daily, $paid_ts);
        if (isset($daily_data[$paid_date_key])) {
            $daily_data[$paid_date_key]['profit'] += $txn['total_amount'];
            $daily_data[$paid_date_key]['commission'] += $ref['commission_earned'];
        }
    }
}
ksort($daily_data);
$chart_30_days_json = json_encode([
    'categories' => $date_series_30_days,
    'series_profit' => array_column($daily_data, 'profit'),
    'series_commission' => array_column($daily_data, 'commission'),
]);

// CHART 2: 12 BULAN
$monthly_data = [];
$date_format_monthly = 'Y-m';
$twelve_months_ago_ts = get_date_n_ago('months', 12);
$date_series_12_months = [];
for ($i = 11; $i >= 0; $i--) {
    $date = (new DateTime())->modify("-$i months");
    $month_key = $date->format($date_format_monthly);
    $date_series_12_months[] = $date->format('M Y');
    $monthly_data[$month_key] = ['profit' => 0, 'commission' => 0];
}
foreach ($all_sales as $ref) {
    $txn = $ref['transactions'];
    $paid_ts = strtotime($txn['paid_at']);
    if ($paid_ts >= $twelve_months_ago_ts) {
        $paid_month_key = date($date_format_monthly, $paid_ts);
        if (isset($monthly_data[$paid_month_key])) {
            $monthly_data[$paid_month_key]['profit'] += $txn['total_amount'];
            $monthly_data[$paid_month_key]['commission'] += $ref['commission_earned'];
        }
    }
}
ksort($monthly_data);
$chart_12_months_json = json_encode([
    'categories' => $date_series_12_months,
    'series_profit' => array_column($monthly_data, 'profit'),
    'series_commission' => array_column($monthly_data, 'commission'),
]);
?> 

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8" />
    <title>Dashboard Affiliate | My Tahfidz</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />

    <style>
        /* Custom Small Box Styles */
        .small-box {
            border-radius: 0.5rem;
            position: relative;
            display: block;
            margin-bottom: 20px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            color: #fff;
            overflow: hidden;
            transition: transform 0.2s;
        }
        .small-box:hover { transform: translateY(-3px); }
        .small-box .inner { padding: 20px; }
        .small-box h3 { font-size: 2rem; font-weight: 700; margin: 0 0 5px 0; white-space: nowrap; }
        .small-box p { font-size: 1rem; margin-bottom: 0; opacity: 0.9; }
        .small-box .icon {
            position: absolute;
            top: 15px;
            right: 15px;
            z-index: 0;
            font-size: 50px;
            color: rgba(255, 255, 255, 0.25);
        }
        
        /* Hero Card Style */
        .card-hero {
            background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 100%);
            color: white;
            border: none;
        }
        .card-balance {
            background: linear-gradient(135deg, #198754 0%, #146c43 100%);
            color: white;
            border: none;
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php $active = 'home'; include "../partials/navbar.php"; ?>

        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-sm-6">
                            <h3 class="mb-0 fw-bold text-dark"><i class="bi bi-speedometer2 me-2"></i>Dashboard Afiliasi</h3>
                            <p class="text-muted mb-0">Selamat datang kembali! Berikut ringkasan performa Anda.</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    
                    <div class="row mb-4">
                        <div class="col-md-6 mb-3 mb-md-0">
                            <div class="card card-hero shadow-sm h-100">
                                <div class="card-body d-flex align-items-center justify-content-between px-3">
                                    <div>
                                        <h6 class="text-uppercase opacity-75 fw-bold ls-1 mb-1">Kode Referral Anda</h6>
                                        <h2 class="mb-0 fw-bold" id="refCodeText"><?= htmlspecialchars($referral_code) ?></h2>
                                    </div>
                                    <button class="btn btn-light btn-sm fw-bold shadow-sm text-primary ms-auto" onclick="copyRefCode()">
                                        <i class="bi bi-copy me-1"></i> Salin
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="card card-balance shadow-sm h-100">
                                <div class="card-body d-flex align-items-center justify-content-between">
                                    <div>
                                        <h6 class="text-uppercase opacity-75 fw-bold ls-1 mb-1">Saldo Dompet</h6>
                                        <h2 class="mb-0 fw-bold"><?= rupiah($current_balance) ?></h2>
                                    </div>
                                    <i class="bi bi-wallet2 fs-1 opacity-50 ms-auto"></i>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="card card-outline card-primary shadow-sm mb-4">
                        <div class="card-header border-bottom-0 d-flex justify-content-between align-items-center">
                            <h3 class="card-title fw-bold text-secondary mt-1">
                                <i class="bi bi-bar-chart-line me-2"></i>Ringkasan Statistik
                            </h3>
                            <div class="card-tools ms-auto">
                                <ul class="nav nav-pills" id="pills-tab" role="tablist">
                                    <li class="nav-item">
                                        <button class="nav-link active fw-bold" id="pills-today-tab" data-bs-toggle="pill" data-bs-target="#pills-today" type="button">Hari Ini</button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link fw-bold" id="pills-month-tab" data-bs-toggle="pill" data-bs-target="#pills-month" type="button">Bulan Ini</button>
                                    </li>
                                    <li class="nav-item">
                                        <button class="nav-link fw-bold" id="pills-all-tab" data-bs-toggle="pill" data-bs-target="#pills-all" type="button">Semua</button>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        <div class="card-body bg-light">
                            <div class="tab-content" id="pills-tabContent">
                                <div class="tab-pane fade show active" id="pills-today">
                                    <?php render_stats_row($stats_today); ?>
                                </div>
                                <div class="tab-pane fade" id="pills-month">
                                    <?php render_stats_row($stats_month); ?>
                                </div>
                                <div class="tab-pane fade" id="pills-all">
                                    <?php render_stats_row($stats_all); ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-lg-7 mb-4">
                            <div class="card card-outline card-info shadow h-100">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold"><i class="bi bi-graph-up me-2"></i>Tren 30 Hari Terakhir</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"><i class="bi bi-dash-lg"></i></button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="chart-30-days"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-5 mb-4">
                            <div class="card card-outline card-success shadow h-100">
                                <div class="card-header border-0">
                                    <h3 class="card-title fw-bold"><i class="bi bi-calendar-check me-2"></i>Performa Bulanan</h3>
                                    <div class="card-tools">
                                        <button type="button" class="btn btn-tool" data-lte-toggle="card-collapse"><i class="bi bi-dash-lg"></i></button>
                                    </div>
                                </div>
                                <div class="card-body">
                                    <div id="chart-12-months"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </main>
    </div>

    <?php function render_stats_row($data) { ?>
        <div class="row">
            <div class="col-lg-3 col-6">
                <div class="small-box bg-primary">
                    <div class="inner">
                        <h3><?= $data['lead'] ?></h3>
                        <p>Total Leads</p>
                    </div>
                    <div class="icon"><i class="bi bi-people-fill"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-success">
                    <div class="inner">
                        <h3><?= $data['sales'] ?></h3>
                        <p>Sales Closing</p>
                    </div>
                    <div class="icon"><i class="bi bi-cart-check-fill"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-warning text-dark">
                    <div class="inner">
                        <h3><?= rupiah($data['profit']) ?></h3>
                        <p>Omset Kotor</p>
                    </div>
                    <div class="icon"><i class="bi bi-currency-dollar"></i></div>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="small-box bg-danger">
                    <div class="inner">
                        <h3><?= rupiah($data['commission']) ?></h3>
                        <p>Komisi Bersih</p>
                    </div>
                    <div class="icon"><i class="bi bi-wallet-fill"></i></div>
                </div>
            </div>
        </div>
    <?php } ?>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../../js/adminlte/adminlte.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js"></script>

    <script>
        // Copy Code Function
        function copyRefCode() {
            const code = document.getElementById('refCodeText').innerText;
            navigator.clipboard.writeText(code).then(() => {
                alert("Kode Referral berhasil disalin!");
            });
        }

        const chart30DaysData = <?= $chart_30_days_json ?>;
        const chart12MonthsData = <?= $chart_12_months_json ?>;
        function formatRupiahJs(number) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(number); }

        // Chart 1: Area (30 Days)
        const chart30Days = new ApexCharts(document.querySelector('#chart-30-days'), {
            series: [{ name: 'Profit Kotor', data: chart30DaysData.series_profit }, { name: 'Komisi Anda', data: chart30DaysData.series_commission }],
            chart: { type: 'area', height: 300, toolbar: { show: false }, fontFamily: 'Source Sans 3, sans-serif' },
            colors: ['#0dcaf0', '#dc3545'],
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.3 } },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            xaxis: { categories: chart30DaysData.categories, tooltip: { enabled: false } },
            yaxis: { labels: { formatter: (val) => formatRupiahJs(val) } },
            tooltip: { y: { formatter: (val) => formatRupiahJs(val) } },
            legend: { position: 'top' }
        });
        chart30Days.render();

        // Chart 2: Bar (12 Months)
        const chart12Months = new ApexCharts(document.querySelector('#chart-12-months'), {
            series: [{ name: 'Profit', data: chart12MonthsData.series_profit }, { name: 'Komisi', data: chart12MonthsData.series_commission }],
            chart: { type: 'bar', height: 300, toolbar: { show: false }, fontFamily: 'Source Sans 3, sans-serif' },
            colors: ['#198754', '#ffc107'],
            plotOptions: { bar: { borderRadius: 4, columnWidth: '60%' } },
            dataLabels: { enabled: false },
            xaxis: { categories: chart12MonthsData.categories },
            yaxis: { labels: { formatter: (val) => formatRupiahJs(val) } },
            tooltip: { y: { formatter: (val) => formatRupiahJs(val) } },
            legend: { position: 'top' }
        });
        chart12Months.render();
    </script>
</body>
</html>