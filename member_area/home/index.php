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
// 1. AMBIL DATA AFFILIATE USER & STATISTIK UTAMA (HEADER BOX)
// ==========================================================

// Ambil Saldo dan Referral Code
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=referral_code,wallet_balance");
$aff_data = $aff_res['data'][0] ?? ['referral_code' => 'N/A', 'wallet_balance' => 0];
$referral_code = $aff_data['referral_code'];
$current_balance = $aff_data['wallet_balance'];

// Ambil Semua Data Referral untuk Statistik Dasar (Leads, Profit, Commission)
$stats_res = supabase_fetch("/referrals?affiliate_user_id=eq.$user_id&select=id,commission_earned,transactions(total_amount,status,paid_at)");
$stats = $stats_res['data'] ?? [];

// Default stat values
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

// Format angka
function rupiah($angka) {
    return "Rp " . number_format($angka, 0, ',', '.');
}

// --- 3. FUNGSI FILTER WAKTU (Untuk Header Cards) ---
function filter_sales_by_period($all_stats, $period) {
    $start_of_period = null;
    $now = new DateTime();

    if ($period === 'today') {
        $start_of_period = (new DateTime())->setTime(0, 0, 0);
    } elseif ($period === 'month') {
        $start_of_period = (new DateTime())->setDate($now->format('Y'), $now->format('m'), 1)->setTime(0, 0, 0);
    }
    
    $filtered_stats = [
        'lead' => 0,
        'sales' => 0,
        'profit' => 0,
        'commission' => 0,
    ];

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

// --- 4. HITUNG DATA UNTUK TIAP PERIODE ---
$stats_today = filter_sales_by_period($stats, 'today');
$stats_month = filter_sales_by_period($stats, 'month');
$stats_all = [
    'lead' => $total_lead,
    'sales' => $total_sales,
    'profit' => $total_profit,
    'commission' => $total_commission,
];

// ==========================================================
// 2. DATA DINAMIS UNTUK CHARTS (30 Hari & 12 Bulan)
// ==========================================================

// Fungsi untuk mendapatkan tanggal N hari/bulan yang lalu
function get_date_n_ago($unit, $value) {
    $date = new DateTime();
    $date->modify("-{$value} {$unit}");
    return $date->getTimestamp();
}

// Filter hanya data penjualan yang PAID/SETTLED dan punya paid_at
$all_sales = array_filter($stats, function ($ref) {
    $txn = $ref['transactions'] ?? null;
    return $txn && ($txn['status'] === 'PAID' || $txn['status'] === 'SETTLED') && !empty($txn['paid_at']);
});

// --- CHART 1: 30 HARI ---
$daily_data = []; // Diubah namanya dari $daily_sales
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

// Susun data final (pastikan urutan sesuai inisialisasi)
ksort($daily_data);
$series_profit_30_days = array_column($daily_data, 'profit');
$series_commission_30_days = array_column($daily_data, 'commission');


// --- CHART 2: 12 BULAN ---
$monthly_data = []; // Diubah namanya dari $monthly_sales
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

// Susun data final (pastikan urutan sesuai inisialisasi)
ksort($monthly_data);
$series_profit_12_months = array_column($monthly_data, 'profit');
$series_commission_12_months = array_column($monthly_data, 'commission');


// JSON encode data untuk JavaScript (REVISI STRUKTUR JSON)
$chart_30_days_json = json_encode([
    'categories' => $date_series_30_days,
    'series_profit' => $series_profit_30_days,
    'series_commission' => $series_commission_30_days,
]);

$chart_12_months_json = json_encode([
    'categories' => $date_series_12_months,
    'series_profit' => $series_profit_12_months,
    'series_commission' => $series_commission_12_months,
]);


// =============================
// AMBIL LIST REFERRALS (Tabel di bawah)
// =============================
$referrals_res = supabase_fetch("/referrals?affiliate_user_id=eq.$user_id&select=id,commission_earned,transactions(customer_email,total_amount,status)&order=id.desc");
$referrals = $referrals_res['data'] ?? [];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Dashboard | My Tahfidz Affiliate</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
<link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
<style> 
    .small-box { padding: 20px; border-radius: 12px; color: #fff; } 
    .small-box .inner h3 { font-size: 28px; font-weight: bold; } 
    .small-box .inner p { margin: 0; } 
</style>
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
<div class="app-wrapper">
    <?php $active = 'home'; include "../partials/navbar.php"; ?>
    <main class="app-main">
        <div class="app-content-header">
            <div class="container-fluid">
                <h3 class="mb-0">Dashboard Afiliasi</h3>
                <div class="d-flex justify-content-between align-items-center mt-2">
                    <p class="mb-0 text-muted">Kode Referral Anda: <strong class="text-primary"><?= htmlspecialchars($referral_code) ?></strong></p>
                    <p class="mb-0 text-success">Saldo Anda: <strong><?= rupiah($current_balance) ?></strong></p>
                </div>
            </div>
        </div>
        <div class="app-content">
            <div class="container-fluid">
                
                <div class="row mt-3">
                    <h4 class="mb-3">Statistik Hari Ini</h4>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-primary"><div class="inner"><h3><?= $stats_today['lead'] ?></h3><p>Lead Hari Ini</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-success"><div class="inner"><h3><?= $stats_today['sales'] ?></h3><p>Sales Hari Ini</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-warning"><div class="inner"><h3><?= rupiah($stats_today['profit']) ?></h3><p>Profit Hari Ini</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-danger"><div class="inner"><h3><?= rupiah($stats_today['commission']) ?></h3><p>Komisi Hari Ini</p></div></div></div>
                </div>

                <div class="row">
                    <h4 class="mb-3">Statistik Bulan Ini</h4>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-primary"><div class="inner"><h3><?= $stats_month['lead'] ?></h3><p>Lead Bulan Ini</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-success"><div class="inner"><h3><?= $stats_month['sales'] ?></h3><p>Sales Bulan Ini</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-warning"><div class="inner"><h3><?= rupiah($stats_month['profit']) ?></h3><p>Profit Bulan Ini</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-danger"><div class="inner"><h3><?= rupiah($stats_month['commission']) ?></h3><p>Komisi Bulan Ini</p></div></div></div>
                </div>

                <div class="row">
                    <h4 class="mb-3">Statistik Total (Semua Waktu)</h4>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-primary"><div class="inner"><h3><?= $stats_all['lead'] ?></h3><p>Total Lead</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-success"><div class="inner"><h3><?= $stats_all['sales'] ?></h3><p>Total Sales</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-warning"><div class="inner"><h3><?= rupiah($stats_all['profit']) ?></h3><p>Total Profit Kotor</p></div></div></div>
                    <div class="col-lg-3 col-6"><div class="small-box text-bg-danger"><div class="inner"><h3><?= rupiah($stats_all['commission']) ?></h3><p>Total Komisi Dihasilkan</p></div></div></div>
                </div>

                <div class="row"> 
                    <div class="col-lg-12">
                        <div class="card mb-4 mt-4">
                            <div class="card-header border-0">
                                <h3 class="card-title">Penjualan 30 Hari Terakhir</h3>
                            </div>
                            <div class="card-body">
                                <div class="position-relative mb-4">
                                    <div id="chart-30-days"></div> 
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-lg-12">
                        <div class="card mb-4">
                            <div class="card-header border-0">
                                <h3 class="card-title">Penjualan 12 Bulan Terakhir</h3>
                            </div>
                            <div class="card-body">
                                <div class="position-relative mb-4">
                                    <div id="chart-12-months"></div> 
                                </div>
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

<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.37.1/dist/apexcharts.min.js" integrity="sha256-+vh8GkaU7C9/wbSLIcwq82tQ2wTf44aOHA8HlBMwRI8=" crossorigin="anonymous" ></script>

<script>
    // Data JSON dari PHP
    const chart30DaysData = <?= $chart_30_days_json ?>;
    const chart12MonthsData = <?= $chart_12_months_json ?>;

    function formatRupiahJs(number) {
        return 'Rp ' + new Intl.NumberFormat('id-ID').format(number);
    }

    // ===================================
    // 1. CHART 30 HARI (AREA CHART)
    // ===================================
    const chart30DaysOptions = {
        series: [{
            name: 'Profit Kotor',
            data: chart30DaysData.series_profit,
        },
        {
            name: 'Komisi Anda',
            data: chart30DaysData.series_commission,
        }],
        chart: {
            type: 'area', 
            height: 280,
            toolbar: { show: false, },
        },
        colors: ['#0d6efd', '#dc3545'], // Biru (Profit) dan Merah (Komisi)
        dataLabels: { enabled: false, },
        stroke: { curve: 'smooth' }, 
        xaxis: {
            categories: chart30DaysData.categories,
        },
        yaxis: {
            labels: {
                formatter: function(val) { return formatRupiahJs(val); }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) { return formatRupiahJs(val); },
            },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'center',
        }
    };

    const chart30Days = new ApexCharts(
        document.querySelector('#chart-30-days'),
        chart30DaysOptions,
    );
    chart30Days.render();


    // ===================================
    // 2. CHART 12 BULAN (BAR CHART)
    // ===================================
    const chart12MonthsOptions = {
        series: [{
            name: 'Profit Kotor',
            data: chart12MonthsData.series_profit,
        },
        {
            name: 'Komisi Anda',
            data: chart12MonthsData.series_commission,
        }],
        chart: {
            type: 'bar',
            height: 280,
            toolbar: { show: false, },
        },
        plotOptions: {
            bar: {
                horizontal: false,
                columnWidth: '70%',
                endingShape: 'rounded',
            },
        },
        colors: ['#20c997', '#ffc107'], // Hijau (Profit) dan Kuning (Komisi)
        dataLabels: { enabled: false, },
        xaxis: {
            categories: chart12MonthsData.categories,
        },
        yaxis: {
            labels: {
                formatter: function(val) { return formatRupiahJs(val); }
            }
        },
        tooltip: {
            y: {
                formatter: function(val) { return formatRupiahJs(val); },
            },
        },
        legend: {
            position: 'top',
            horizontalAlign: 'center',
        }
    };

    const chart12Months = new ApexCharts(
        document.querySelector('#chart-12-months'),
        chart12MonthsOptions,
    );
    chart12Months.render();
</script> 
</body>
</html>