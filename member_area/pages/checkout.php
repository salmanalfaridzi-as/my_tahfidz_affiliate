<?php
// pages/checkout.php
include '../../config.php';
include '../../functions.php';

$product_id = $_GET['product_id'] ?? null;
// Asumsi: Ambil data produk $product_price dan $commission_rate dari DB

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $buyer_email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
    $total_amount = 100000; // Contoh harga

    if ($buyer_email) {
        // 1. Ambil ID Afiliasi dari cookie
        $aff_id = get_affiliate_id_from_cookie();

        // 2. Insert Transaksi Pending ke Supabase
        $trx_data = [
            'product_id' => $product_id,
            'buyer_email' => $buyer_email,
            'total_amount' => $total_amount,
            'affiliate_user_id' => $aff_id, // NULL jika tidak ada afiliasi
            'status' => 'pending'
        ];
        
        $trx_res = supabase_fetch("/transactions?select=id", 'POST', $trx_data);
        $local_transaction_id = $trx_res['data'][0]['id'] ?? null;

        if ($local_transaction_id) {
            // 3. Panggil API Xendit untuk membuat Invoice
            $xendit_res = create_xendit_invoice($local_transaction_id, $total_amount, $buyer_email);

            if (isset($xendit_res['invoice_url'])) {
                // 4. Redirect ke halaman pembayaran Xendit
                header("Location: " . $xendit_res['invoice_url']);
                exit;
            }
        }
    }
}

// --- Fungsi untuk Membuat Invoice Xendit (Implementasi di functions.php) ---
// function create_xendit_invoice($transaction_id, $amount, $customer_email) { ... }
?>