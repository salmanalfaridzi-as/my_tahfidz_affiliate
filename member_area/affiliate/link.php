<?php
// member_area/user_affiliate/generate_link.php

include '../../config.php';
include '../../functions.php';

if (!isset($_SESSION['access_token'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// --- 1. Ambil Detail Saldo dan Referral Code ---
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=referral_code");
$aff_data = $aff_res['data'][0] ?? ['referral_code' => 'N/A'];
$referral_code = $aff_data['referral_code'];

// Jika referral_code tidak ditemukan, hentikan proses (user belum terdaftar afiliasi)
if ($referral_code === 'N/A') {
    die("<div class='alert alert-danger'>Akun Anda belum memiliki Kode Referral. Hubungi Admin.</div>");
}

// --- 2. Ambil Daftar Produk (Untuk Generasi Link Spesifik) ---
// Kita asumsikan BASE_URL adalah alamat landing page Anda (misal: https://iqro.com/index.php)
// Anda harus mendefinisikan konstanta BASE_URL di config.php

$products_res = supabase_fetch("/products?select=id,name,commission_rate_price,path");
$products = $products_res['data'] ?? [];

$message = '';
if ($products_res['status'] != 200 || empty($products)) {
    $message = "<div class='alert alert-warning'>Gagal memuat daftar produk. Silakan coba refresh.</div>";
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Link Referral | Iqro Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" media="print" onload="this.media='all'" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    <style>
        .card-link-generator {
            border-left: 5px solid #28a745; /* Green Accent */
            background-color: #f8f9fa;
        }
        .instruction-box {
            padding: 15px;
            background-color: #fff3cd; /* Warna Kuning Lembut */
            border-left: 5px solid #ffc107;
            margin-bottom: 20px;
        }
        .referral-link-display {
            font-size: 1rem;
            word-break: break-all;
            background-color: #e9ecef;
            padding: 10px;
            border-radius: 5px;
        }
        .btn-copy {
            width: 100%;
            font-size: 1.1rem;
            padding: 10px;
        }
    </style>
</head>

<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
    <div class="app-wrapper">
        <?php
        $active = 'affiliate_link'; // Asumsi menu navigasi Anda
        include "../partials/navbar.php";
        ?>
        <main class="app-main">
            <div class="app-content-header">
                <div class="container-fluid">
                    <div class="row">
                        <div class="col-sm-6">
                            <h3 class="mb-0">🔑 Generate Link Referral</h3>
                        </div>
                        <div class="col-sm-6 text-end">
                    <p class="mb-0 text-muted">Kode Referral Anda: <strong class="text-primary"><?= htmlspecialchars($referral_code) ?></strong></p>

                        </div>
                    </div>
                </div>
            </div>
            <div class="app-content">
                <div class="container-fluid">
                    <?php echo $message; ?>

                    <div class="row">
                        <div class="col-lg-12">
                            <div class="instruction-box shadow-sm">
                                <h4>Panduan Sederhana:</h4>
                                <ol class="mb-0">
                                    <li>Pilih <b>Paket Aplikasi</b> di bawah.</li>
                                    <li>Klik tombol <b>"Copy Link Referral"</b>.</li>
                                    <li>Bagikan <b>link</b> tersebut ke grup WA, Facebook, atau teman Anda.</li>
                                    <li>Setiap orang yang membeli melalui <b>link</b> Anda akan tercatat sebagai komisi!</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <?php if (empty($products)): ?>
                            <div class="col-12"><div class="alert alert-info">Tidak ada produk yang terdaftar untuk dibuat link referral.</div></div>
                        <?php else: ?>
                            <?php foreach ($products as $product): ?>
                                <?php
                                $product_id = htmlspecialchars($product['id']);
                                $product_name = htmlspecialchars($product['name']);
                                
                                // FORMAT LINK REFERRAL: [BASE_URL]?ref=[REFERRAL_CODE]
                                $referral_link = BASE_URL . $product['path'] . "ref=" . urlencode($referral_code); 
                                // Jika Anda ingin link spesifik produk: $referral_link = BASE_URL . "?product_id=" . $product_id . "&ref=" . urlencode($referral_code);
                                // Namun, kita gunakan BASE_URL saja (yang sudah kita buat)
                                ?>

                                <div class="col-md-6 mb-4">
                                    <div class="card card-link-generator shadow">
                                        <div class="card-header bg-success text-white">
                                            <h4 class="card-title mb-0"><i class="bi bi-book-half me-2"></i> <?php echo $product_name; ?></h4>
                                        </div>
                                        <div class="card-body">
                                            
                                            <div class="mb-3">
                                                <label class="form-label fw-bold">Link Spesial Anda:</label>
                                                <div class="referral-link-display" id="link-<?php echo $product_id; ?>">
                                                    <?php echo $referral_link; ?>
                                                </div>
                                            </div>
                                            
                                            <button 
                                                class="btn btn-primary btn-copy btn-lg" 
                                                data-link="<?php echo $referral_link; ?>"
                                                onclick="copyLink('<?php echo $product_id; ?>', this)">
                                                <i class="bi bi-link-45deg me-2"></i> Copy Link Referral
                                            </button>
                                            <span id="feedback-<?php echo $product_id; ?>" class="text-success mt-2 d-block" style="display:none;"></span>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="../../js/adminlte/adminlte.js"></script>

    <script>
        /**
         * Fungsi untuk menyalin link ke clipboard dan memberikan feedback.
         * @param {string} productId - ID produk untuk menargetkan elemen feedback.
         * @param {HTMLElement} button - Elemen tombol yang diklik.
         */
        function copyLink(productId, button) {
            const link = document.getElementById(`link-${productId}`).textContent.trim();
            const feedbackElement = document.getElementById(`feedback-${productId}`);
            
            // 1. Salin ke clipboard
            navigator.clipboard.writeText(link).then(() => {
                // 2. Tampilkan feedback sukses
                feedbackElement.textContent = 'Link berhasil disalin!';
                feedbackElement.style.display = 'block';
                button.innerHTML = '<i class="bi bi-check-circle-fill me-2"></i> Berhasil Disalin!';
                button.classList.remove('btn-primary');
                button.classList.add('btn-success');

                // 3. Reset tampilan setelah 3 detik
                setTimeout(() => {
                    feedbackElement.style.display = 'none';
                    button.innerHTML = '<i class="bi bi-link-45deg me-2"></i> Copy Link Referral';
                    button.classList.remove('btn-success');
                    button.classList.add('btn-primary');
                }, 3000);

            }).catch(err => {
                // Tampilkan error jika gagal
                feedbackElement.textContent = 'Gagal menyalin link. Mohon salin manual.';
                feedbackElement.classList.remove('text-success');
                feedbackElement.classList.add('text-danger');
                feedbackElement.style.display = 'block';
                console.error('Copy failed', err);
            });
        }
    </script>
</body>

</html>