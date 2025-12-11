<?php
// functions.php

// --- Helper cURL Umum ---
function execute_curl($url, $method = 'GET', $data = null, $headers = []) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    
    // Default headers
    $default_headers = [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_KEY,
    ];
    $combined_headers = array_merge($default_headers, $headers);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter($combined_headers));

    if ($data && in_array($method, ['POST', 'PATCH'])) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['data' => json_decode($response, true), 'status' => $http_code];
}

// --- Fungsi Supabase Auth ---
function supabase_auth_request($endpoint, $data) {
    return execute_curl(SUPABASE_AUTH_URL . $endpoint, 'POST', $data);
}

// --- Fungsi Supabase DB (PostgREST) ---
function supabase_fetch($endpoint, $method = 'GET', $data = null) {
    $headers = [];
    if (isset($_SESSION['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }
    return execute_curl(SUPABASE_DB_URL . $endpoint, $method, $data, $headers);
}

// --- Fungsi Pelacakan Afiliasi ---
function track_referral($ref_code) {
    if (!isset($_COOKIE[REFERRAL_COOKIE_NAME]) || $_COOKIE[REFERRAL_COOKIE_NAME] !== $ref_code) {
        setcookie(REFERRAL_COOKIE_NAME, $ref_code, REFERRAL_COOKIE_EXPIRY, '/');
    }
}

function get_affiliate_id_from_cookie() {
    if (isset($_COOKIE[REFERRAL_COOKIE_NAME])) {
        $ref_code = $_COOKIE[REFERRAL_COOKIE_NAME];
        
        // Cari user_id di tabel affiliate_details
        $response = supabase_fetch("/affiliate_details?select=user_id&referral_code=eq.$ref_code");

        if ($response['status'] == 200 && !empty($response['data'])) {
            return $response['data'][0]['user_id'];
        }
    }
    return null;
}

// --- Fungsi Panggilan RPC (PostgreSQL Function) ---
function supabase_rpc($function_name, $data) {
    $url = SUPABASE_URL . '/rpc/' . $function_name;
    
    $headers = [];
    if (isset($_SESSION['access_token'])) {
        $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    }
    return execute_curl($url, 'POST', $data, $headers);
}
?>