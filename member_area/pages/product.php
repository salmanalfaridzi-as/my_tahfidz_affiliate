<?php
// pages/product.php
include '../../config.php';
include '../../functions.php';

// --- (Contoh data produk) ---
$product_id = 1;
// Fetch data produk dari Supabase untuk mendapatkan harga dan commission_rate
// $product_res = supabase_fetch("/products?id=eq.$product_id");

// --- 1. Tracking Referral ---
if (isset($_GET['ref'])) {
    track_referral($_GET['ref']);
}

// ... [Tampilkan HTML Produk] ...

// Link Checkout (sertakan ID produk dan pastikan session tracking aktif)
echo "<a href='checkout.php?product_id={$product_id}'>Beli Sekarang</a>";
?>