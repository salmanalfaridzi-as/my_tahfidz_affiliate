<?php
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

if ($referral_code === 'N/A') {
    die("<div class='alert alert-danger'>Akun Anda belum memiliki Kode Referral. Hubungi Admin.</div>");
}

// --- 2. Ambil Daftar Produk ---
$products_res = supabase_fetch("/products?is_affiliate_visible=eq.true&select=id,name,commission_rate_price,path");
$products = $products_res['data'] ?? [];

// --- 3. Ambil Daftar Kursus LMS ---
$courses_res = supabase_fetch("/lms_courses?is_published=eq.true&select=id,title,description,thumbnail_url,content");
$courses = $courses_res['data'] ?? [];

$message = '';
if ($products_res['status'] != 200 || empty($products)) {
    $message .= "<div class='alert alert-warning alert-dismissible fade show'>Gagal memuat daftar produk atau produk kosong. <button type='button' class='btn-close' data-bs-dismiss='alert'></button></div>";
}
if ($courses_res['status'] != 200) {
    $message .= "<div class='alert alert-danger'>Gagal memuat daftar kursus LMS.</div>";
}

$active = 'affiliate_link';
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>Link Referral & LMS | Iqro Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />

    <style>
        /* Custom Styles for Affiliate Page */
        .instruction-box {
            background: linear-gradient(to right, #fff3cd, #fff);
            border-left: 5px solid #ffc107;
            border-radius: 8px;
            padding: 20px;
        }

        /* Product Link Cards */
        .card-link-generator {
            border: none;
            transition: transform 0.2s;
        }
        .card-link-generator:hover {
            transform: translateY(-5px);
        }
        .referral-input-group {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 6px;
            padding: 8px;
            font-family: monospace;
            color: #d63384;
            word-break: break-all;
        }

        /* LMS Course Cards */
        .course-card {
            border: none;
            border-radius: 12px;
            overflow: hidden;
            transition: all 0.3s ease;
            background: #fff;
            height: 100%; /* Agar tinggi card sama */
        }
        .course-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
        }
        .course-thumb-wrapper {
            position: relative;
            height: 180px; /* Tinggi gambar fix */
            overflow: hidden;
        }
        .course-thumbnail {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s;
        }
        .course-card:hover .course-thumbnail {
            transform: scale(1.05);
        }
        .course-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(0,0,0,0.6);
            color: #fff;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
            backdrop-filter: blur(4px);
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
                            <h3 class="mb-0 text-dark fw-bold"><i class="bi bi-link-45deg me-2"></i>Affiliate Tools</h3>
                        </div>
                        <div class="col-sm-6 text-end">
                            <span class="badge bg-primary fs-6 p-2 rounded-pill shadow-sm">
                                Referral Code: <?= htmlspecialchars($referral_code) ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="app-content">
                <div class="container-fluid">
                    <?= $message; ?>

                    <div class="row mb-4">
                        <div class="col-12">
                            <div class="instruction-box shadow-sm">
                                <h5 class="fw-bold text-warning-emphasis"><i class="bi bi-lightbulb me-2"></i>Cara Kerja Affiliate</h5>
                                <ol class="mb-0 ps-3">
                                    <li>Pilih produk yang ingin Anda promosikan di bawah ini.</li>
                                    <li>Klik tombol <b>"Salin Link"</b>.</li>
                                    <li>Bagikan link ke media sosial atau teman. Komisi otomatis masuk saat ada pembelian.</li>
                                </ol>
                            </div>
                        </div>
                    </div>

                    <div class="d-flex align-items-center mb-3">
                        <h4 class="fw-bold text-secondary mb-0">📦 Produk Tersedia</h4>
                        <hr class="flex-grow-1 ms-3">
                    </div>

                    <div class="row">
                        <?php if (empty($products)): ?>
                            <div class="col-12 text-center py-5 text-muted">Belum ada produk aktif.</div>
                        <?php else: ?>
                            <?php foreach ($products as $product): 
                                $product_id = htmlspecialchars($product['id']);
                                $referral_link = $product['path'] . "?ref=" . urlencode($referral_code);
                            ?>
                            <div class="col-md-6 col-lg-6 mb-4">
                                <div class="card card-link-generator card-outline card-success shadow-sm h-100">
                                    <div class="card-header bg-transparent border-bottom-0 pt-3">
                                        <h5 class="card-title fw-bold text-success">
                                            <i class="bi bi-bag-check-fill me-2"></i><?= htmlspecialchars($product['name']); ?>
                                        </h5>
                                    </div>
                                    <div class="card-body pt-0">
                                        <p class="text-muted small mb-2">Link Referral Unik Anda:</p>
                                        <div class="referral-input-group mb-3 text-truncate" id="link-<?= $product_id; ?>">
                                            <?= $referral_link; ?>
                                        </div>
                                        <button class="btn btn-success w-100 fw-bold" 
                                                onclick="copyLink('<?= $product_id; ?>', this)">
                                            <i class="bi bi-clipboard me-2"></i> Salin Link
                                        </button>
                                        <small id="feedback-<?= $product_id; ?>" class="d-block text-center mt-2 fw-bold text-success" style="display:none!important;"></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <div class="d-flex align-items-center mt-2 mb-3">
                        <h4 class="fw-bold text-secondary mb-0">🎓 Materi Pelatihan (LMS)</h4>
                        <hr class="flex-grow-1 ms-3">
                    </div>

                    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                        <?php if (empty($courses)): ?>
                            <div class="col-12">
                                <div class="alert alert-light border text-center">Belum ada materi pelatihan.</div>
                            </div>
                        <?php else: ?>
                            <?php foreach ($courses as $course): 
                                $c_id = htmlspecialchars($course['id']);
                                $c_title = htmlspecialchars($course['title']);
                                $c_desc = htmlspecialchars($course['description']);
                                $c_thumb = !empty($course['thumbnail_url']) ? htmlspecialchars($course['thumbnail_url']) : 'https://placehold.co/600x400?text=Course+Image';
                                $c_content = isset($course['content']) ? html_entity_decode($course['content']) : '<p>Tidak ada konten teks.</p>';
                                $modalId = "modalCourse" . $c_id;
                            ?>
                            <div class="col">
                                <div class="card course-card shadow-sm h-100">
                                    <div class="course-thumb-wrapper">
                                        <img src="<?= $c_thumb ?>" class="course-thumbnail" alt="<?= $c_title ?>">
                                        <div class="course-badge"><i class="bi bi-play-circle me-1"></i> Materi</div>
                                    </div>
                                    
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title fw-bold text-dark mb-2"><?= $c_title ?></h5>
                                        <p class="card-text text-muted small flex-grow-1">
                                            <?= substr($c_desc, 0, 90) . (strlen($c_desc) > 90 ? '...' : '') ?>
                                        </p>
                                        
                                        <button type="button" class="btn btn-outline-warning w-100 mt-3 rounded-pill fw-bold" data-bs-toggle="modal" data-bs-target="#<?= $modalId ?>">
                                            <i class="bi bi-eye me-2"></i> Pelajari Sekarang
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <div class="modal fade" id="<?= $modalId ?>" tabindex="-1" aria-hidden="true">
                                <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
                                    <div class="modal-content">
                                        <div class="modal-header bg-warning-subtle">
                                            <h5 class="modal-title fw-bold"><i class="bi bi-book me-2"></i> <?= $c_title ?></h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body p-4">
                                            <img src="<?= $c_thumb ?>" class="img-fluid rounded mb-4 w-100 shadow-sm" style="max-height: 300px; object-fit: cover;">
                                            
                                            <h6 class="fw-bold border-bottom pb-2 mb-3">Deskripsi & Materi:</h6>
                                            <div class="course-content-text">
                                                <?= $c_content ?>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Tutup</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div> <br><br>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"></script>
    <script src="../../js/adminlte/adminlte.js"></script>

    <script>
        function copyLink(productId, button) {
            const linkText = document.getElementById(`link-${productId}`).innerText.trim();
            const feedback = document.getElementById(`feedback-${productId}`);
            const originalHtml = button.innerHTML;

            navigator.clipboard.writeText(linkText).then(() => {
                button.classList.remove('btn-success');
                button.classList.add('btn-dark'); // Visual feedback button change
                button.innerHTML = '<i class="bi bi-check2-all"></i> Tersalin!';
                
                feedback.textContent = 'Link berhasil disalin ke clipboard!';
                feedback.style.display = 'block';

                setTimeout(() => {
                    button.classList.remove('btn-dark');
                    button.classList.add('btn-success');
                    button.innerHTML = originalHtml;
                    feedback.style.display = 'none';
                }, 2500);
            }).catch(err => {
                alert("Gagal menyalin otomatis. Silakan blok dan copy manual.");
            });
        }
    </script>
</body>
</html> 