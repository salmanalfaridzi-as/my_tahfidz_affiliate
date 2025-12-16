<?php
// public/index.php (Revisi: Hide Manual Referral Input Jika Terdeteksi)
include 'config.php';
include 'functions.php';

$product_response = supabase_fetch("/products?select=id,name,price");

$products_list = [];
if ($product_response['status'] == 200 && !empty($product_response['data'])) {
    $products_list = $product_response['data'];
} else {
    var_dump($product_response);
    die("Error: Gagal memuat data produk dari database atau tidak ada produk tersedia.");
}

// --- 2. Inisialisasi Referral ---
$referral_code_from_url = null;
$affiliate_name = null;

// Cek kode referral dari URL
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referral_code_from_url = filter_input(INPUT_GET, 'ref', FILTER_SANITIZE_SPECIAL_CHARS);
    // Simpan ke cookie dan ambil detailnya
    track_referral($referral_code_from_url);
}

// Ambil kode referral final dari cookie (prioritas)
$final_referral_code = $_COOKIE[REFERRAL_COOKIE_NAME] ?? $referral_code_from_url;

// --- VARIABEL KONTROL BARU ---
// Jika kode terdeteksi dari URL atau Cookie, kita TIDAK akan menampilkan field input manual
$show_manual_ref_input = empty($final_referral_code);

// --- 3. Ambil Nama Afiliasi (Jika Kode Ada) ---
if ($final_referral_code) {
    // ... (Logika pengambilan nama afiliasi tetap sama) ...
    $affiliate_data_res = supabase_fetch("/affiliate_details?referral_code=eq.$final_referral_code&select=user_profile(name)");

    if ($affiliate_data_res['status'] == 200 && !empty($affiliate_data_res['data'])) {
        $user_profile = $affiliate_data_res['data'][0]['user_profile'];
        if ($user_profile && isset($user_profile['name'])) {
            $affiliate_name = $user_profile['name'];
        }
    }
}


// ... (Logika GET product_id tetap sama) ...
if (isset($_GET['product']) && !empty($_GET['product'])) {
    $product_id = filter_input(INPUT_GET, 'product', FILTER_SANITIZE_SPECIAL_CHARS);
}


// --- 4. Logika Form Submission (Checkout) ---
$error_message = null;
$success_message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_checkout'])) {

    $customer_email = filter_input(INPUT_POST, 'customer_email', FILTER_VALIDATE_EMAIL);
    $selected_product_id = filter_input(INPUT_POST, 'product_id', FILTER_SANITIZE_STRING);

    // Ambil kode referral dari input manual HANYA JIKA FIELD ITU ADA
    // Jika field itu di-hide, nilainya akan kosong, jadi kita tetap gunakan $final_referral_code
    $manual_ref_code = filter_input(INPUT_POST, 'manual_referral_code', FILTER_SANITIZE_STRING);

    // Tentukan kode referral yang akan diproses: Manual (Jika diisi) > Cookie/URL
    $final_code_to_process = $manual_ref_code ?: $final_referral_code;

    // ... (Logika validasi dan pemanggilan request_xendit_invoice tetap sama) ...
    if (!$customer_email) {
        $error_message = "Email yang dimasukkan tidak valid.";
    } elseif (!$selected_product_id) {
        $error_message = "Mohon pilih paket aplikasi yang ingin Anda beli.";
    } else {
        $invoice_response = request_doku_invoice(
            $selected_product_id,
            $customer_email,
            $final_code_to_process
        );

        if ($invoice_response['status'] == 200 && isset($invoice_response['data']['redirect_url'])) {
            $redirect_url = $invoice_response['data']['redirect_url'];
            header("Location: " . $redirect_url);
            exit;
        } else {
            var_dump($invoice_response);
            $error_message = $invoice_response['data']['error'] ?? 'Gagal memproses pembayaran. Silakan coba lagi.';
        }
    }
}
?>

<!doctype html>
<html lang="id">

<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title>MyTahfidz - Belajar Ngaji Mudah dan Menyenangkan</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="css/adminlte/adminlte.css" />
    <style>
        .hero-section {
            background: linear-gradient(135deg, #4CAF50 0%, #8BC34A 100%);
            color: white;
            padding: 80px 0;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }

        .fitur-card {
            transition: transform 0.3s;
        }

        .fitur-card:hover {
            transform: translateY(-5px);
        }

        .form-section {
            background-color: #f8f9fa;
        }

        .price-display {
            font-size: 2rem;
            font-weight: bold;
            color: #28a745;
            /* Bootstrap Success Green */
        }
    </style>
</head>

<body class="hold-transition layout-top-nav">
    <div class="wrapper">

        <nav class="main-header navbar navbar-expand-md navbar-light navbar-white">
            <div class="container">
                <a href="index.php" class="navbar-brand">
                    <span class="brand-text font-weight-bold"><i class="bi bi-book me-2"></i> MyTahfidz</span>
                </a>
            </div>
        </nav>

        <div class="content-wrapper">
            <div class="hero-section">
                <div class="container text-center">
                    <h1 class="display-3 font-weight-bold">Ayo Ngaji Sekarang!</h1>
                    <p class="lead">Pilih paket terbaik untuk belajar membaca Al-Qur'an sejak dini.</p>
                    <a href="#daftar" class="btn btn-warning btn-lg mt-3"><i class="bi bi-arrow-down-circle me-2"></i> Pilih Paket!</a>
                </div>
            </div>

            <div class="content pt-4">
                <div class="container">
                    <h2 class="text-center mb-4">✨ Fitur Unggulan Aplikasi ✨</h2>
                    <div class="row">
                        <div class="col-md-4">
                            <div class="card fitur-card text-center shadow-sm">
                                <div class="card-body"><i class="bi bi-person-video2 text-success" style="font-size: 3rem;"></i>
                                    <h5 class="card-title mt-2">Belajar Interaktif</h5>
                                    <p class="card-text">Dilengkapi suara dan animasi yang membuat belajar Iqro tidak membosankan.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card fitur-card text-center shadow-sm">
                                <div class="card-body"><i class="bi bi-star-fill text-warning" style="font-size: 3rem;"></i>
                                    <h5 class="card-title mt-2">Level Bertingkat</h5>
                                    <p class="card-text">Metode belajar dari dasar (Iqro 1) hingga lancar (Iqro 6) sesuai panduan.</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card fitur-card text-center shadow-sm">
                                <div class="card-body"><i class="bi bi-lock-fill text-primary" style="font-size: 3rem;"></i>
                                    <h5 class="card-title mt-2">Akses Penuh Lifetime</h5>
                                    <p class="card-text">Bayar sekali, dapatkan akses penuh selamanya tanpa biaya bulanan.</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row justify-content-center mt-5 form-section" id="daftar">
                        <div class="col-lg-8">
                            <div class="card card-outline card-success shadow-lg">
                                <div class="card-header">
                                    <h3 class="card-title"><i class="bi bi-credit-card-fill me-2"></i> Pilih Paket dan Pembayaran</h3>
                                </div>
                                <div class="card-body">
                                    <p class="lead text-center">Harga paket yang dipilih:</p>
                                    <div class="text-center mb-4">
                                        <span class="price-display">Rp <span id="display-price">0</span></span>
                                    </div>

                                    <?php if ($error_message): ?>
                                        <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                            <i class="bi bi-x-circle-fill me-2"></i> <?php echo htmlspecialchars($error_message); ?>
                                            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                                        </div>
                                    <?php endif; ?>

                                    <form method="POST">
                                        <input type="hidden" name="submit_checkout" value="1">
<div class="mb-3">
                                            <label for="product_id" class="form-label">Pilih Paket Aplikasi</label>
                                            <select class="form-control form-control-lg" id="product_id" name="product_id" required>
                                                <option value="" disabled selected>-- Pilih Paket --</option>
                                                <?php
                                                $first_product_id = $products_list[0]['id'] ?? null;
                                                $selected_id = $product_id ?? $first_product_id;
                                                ?>
                                                <?php foreach ($products_list as $product): ?>
                                                    <option
                                                        value="<?php echo htmlspecialchars($product['id']); ?>"
                                                        data-price="<?php echo htmlspecialchars($product['price']); ?>"
                                                        <?php
                                                        // Tambahkan logika 'selected'
                                                        if ($product['id'] == $selected_id) {
                                                            echo 'selected';
                                                        }
                                                        ?>>
                                                        <?php echo htmlspecialchars($product['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="mb-3">
                                            <label for="customer_email" class="form-label">Email Anda (Untuk Akses Aplikasi)</label>
                                            <input type="email" class="form-control form-control-lg" id="customer_email" name="customer_email"
                                                placeholder="contoh@email.com" required value="<?php echo htmlspecialchars($_POST['customer_email'] ?? ''); ?>">
                                            <!-- <small class="form-text text-muted">Pastikan email aktif, link akses akan dikirim ke email ini.</small> -->
                                        </div>
                                        <?php if ($affiliate_name): ?>
                                            <div class="alert alert-success py-2 small">
                                                <i class="bi bi-person-check-fill me-1"></i> Anda diarahkan oleh Afiliasi **<?php echo htmlspecialchars($affiliate_name); ?>**.
                                                <input type="hidden" name="final_referral_code" value="<?php echo htmlspecialchars($final_referral_code); ?>">
                                            </div>
                                        <?php elseif ($final_referral_code): ?>
                                            <div class="alert alert-info py-2 small">
                                                <i class="bi bi-tag-fill me-1"></i> Kode referral terdeteksi di browser Anda.
                                                <input type="hidden" name="final_referral_code" value="<?php echo htmlspecialchars($final_referral_code); ?>">
                                            </div>
                                        <?php endif; ?>

                                        <?php if ($show_manual_ref_input): ?>
                                            <div class="mb-3">
                                                <label for="manual_referral_code" class="form-label">Punya Kode Referral?</label>
                                                <input type="text" class="form-control" id="manual_referral_code" name="manual_referral_code"
                                                    placeholder="Masukkan Kode Jika Ada">
                                                <!-- <small class="form-text text-muted">Dapatkan diskon/bonus dengan memasukkan kode dari teman Anda.</small> -->
                                            </div>
                                        <?php endif; ?>


                                        <div class="d-grid mt-4">
                                            <button type="submit" class="btn btn-success btn-lg">
                                                <i class="bi bi-bag-check-fill me-2"></i> Lanjutkan ke Pembayaran
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
        <footer class="main-footer">
            <div class="container">
                MyTahfidz &copy; 2025. All rights reserved.
            </div>
        </footer>
    </div>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
    <script src="js/adminlte/adminlte.js"></script>

    <script>
        // Data produk untuk referensi di JS
        const productPrices = {};
        <?php foreach ($products_list as $product): ?>
            productPrices['<?php echo htmlspecialchars($product['id']); ?>'] = '<?php echo $product['price']; ?>';
        <?php endforeach; ?>

        function formatRupiah(number) {
            return new Intl.NumberFormat('id-ID').format(number);
        }

        function updatePriceDisplay() {
            const selectedId = $('#product_id').val();
            const price = productPrices[selectedId] || 0;
            $('#display-price').text(formatRupiah(price));
        }

        $(document).ready(function() {
            // Panggil saat halaman pertama dimuat
            updatePriceDisplay();

            // Panggil saat dropdown produk berubah
            $('#product_id').on('change', updatePriceDisplay);
        });
    </script>

</body>

</html>