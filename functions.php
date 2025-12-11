<?php
// functions.php

// --- Helper cURL Umum ---

function execute_curl($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

    // Default (public)
    $default_headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY
    ];

    // Gabungkan (tanpa Authorization default)
    $combined_headers = array_merge($default_headers, $headers);

    curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter($combined_headers));

    if ($data && in_array($method, ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return [
        'data' => json_decode($response, true),
        'status' => $http_code
    ];
}


//
// --- 🔥 Fungsi REFRESH TOKEN (AUTO RENEW) ---
function supabase_refresh_token() {
    if (!isset($_SESSION['refresh_token'])) {
        return null;
    }

    $url = SUPABASE_AUTH_URL . "/token?grant_type=refresh_token";

    $payload = [
        "refresh_token" => $_SESSION['refresh_token']
    ];

    $result = execute_curl($url, "POST", $payload);

    if (
        isset($result['data']['access_token']) && 
        isset($result['data']['refresh_token'])
    ) {
        // Update session
        $_SESSION['access_token']  = $result['data']['access_token'];
        $_SESSION['refresh_token'] = $result['data']['refresh_token'];
        return true;
    }

    return false; // gagal refresh
}


// --- Fungsi Auth (LOGIN, SIGNUP) ---
function supabase_auth_request($endpoint, $data) {
    return execute_curl(SUPABASE_AUTH_URL . $endpoint, 'POST', $data);
}


//
// --- 🔥 Fungsi DB + AUTO REFRESH ---
//
function supabase_fetch($endpoint, $method = 'GET', $data = null) {

    $headers = [];

    // if (isset($_SESSION['access_token'])) {
    //     $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    // }

    return execute_curl(SUPABASE_DB_URL . $endpoint, $method, $data, $headers);
}


// --- Panggilan RPC ---
function supabase_rpc($function_name, $data) {
    $url = SUPABASE_URL . '/rpc/' . $function_name;

    $headers = [];
    // if (isset($_SESSION['access_token'])) {
    //     $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    // }

    return execute_curl($url, 'POST', $data, $headers);
}


// --- Afiliasi ---
function track_referral($ref_code) {
    if (!isset($_COOKIE[REFERRAL_COOKIE_NAME]) || $_COOKIE[REFERRAL_COOKIE_NAME] !== $ref_code) {
        setcookie(REFERRAL_COOKIE_NAME, $ref_code, REFERRAL_COOKIE_EXPIRY, '/');
    }
}

function get_affiliate_id_from_cookie() {
    if (isset($_COOKIE[REFERRAL_COOKIE_NAME])) {
        $ref_code = $_COOKIE[REFERRAL_COOKIE_NAME];

        $response = supabase_fetch("/affiliate_details?select=user_id&referral_code=eq.$ref_code");

        if ($response['status'] == 200 && !empty($response['data'])) {
            return $response['data'][0]['user_id'];
        }
    }
    return null;
}

// functions.php

// --- Fungsi Panggil API Edge Function (Buat Invoice Xendit) ---
function request_xendit_invoice($product_id, $customer_email, $referral_code = null) {
    
    // GANTI: Sesuaikan URL ini dengan endpoint Deno Edge Function Anda
    // Contoh: https://[PROJECT_REF].supabase.co/functions/v1/xendit-invoice
    $XENDIT_INVOICE_API_URL = SUPABASE_URL . "/functions/v1/xendit-invoice"; 
    // --- 1. Siapkan Payload ---
    $payload = [
        'product_id' => $product_id,
        'customer_email' => $customer_email,
        // Kirimkan referral_code jika ada (bisa null)
        'referral_code' => $referral_code, 
        // Anda bisa menambahkan customer_name di sini jika diperlukan oleh Edge Function,
        // tetapi saat ini Edge Function hanya memerlukan 3 field di atas.
    ];

    // --- 2. Eksekusi cURL ke Edge Function ---
    // Edge Function TIDAK memerlukan Authorization: Bearer, tetapi Content-Type wajib
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . SUPABASE_KEY,
        'apikey: ' . SUPABASE_KEY
    ];
    
    // Kita gunakan execute_curl, tetapi kita tidak ingin header default 'apikey' Supabase ikut terkirim.
    // Mari kita buat helper cURL khusus untuk API eksternal agar aman.

    // --- Menggunakan Helper cURL Khusus Eksternal ---
    $ch = curl_init($XENDIT_INVOICE_API_URL);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    $result = [
        'data' => json_decode($response, true), 
        'status' => $http_code
    ];

    return $result;
}