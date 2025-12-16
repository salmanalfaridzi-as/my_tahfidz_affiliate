<?php
// functions.php

// --- Helper cURL Umum ---

function execute_curl($url, $method = 'GET', $data = null, $headers = [])
{
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
function supabase_refresh_token()
{
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
function supabase_auth_request($endpoint, $data)
{
    return execute_curl(SUPABASE_AUTH_URL . $endpoint, 'POST', $data);
}


//
// --- 🔥 Fungsi DB + AUTO REFRESH ---
//
function supabase_fetch($endpoint, $method = 'GET', $data = null)
{

    $headers = [];

    // if (isset($_SESSION['access_token'])) {
    //     $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    // }

    return execute_curl(SUPABASE_DB_URL . $endpoint, $method, $data, $headers);
}


// --- Panggilan RPC ---
function supabase_rpc($function_name, $data)
{
    $url = SUPABASE_URL . '/rpc/' . $function_name;

    $headers = [];
    // if (isset($_SESSION['access_token'])) {
    //     $headers[] = 'Authorization: Bearer ' . $_SESSION['access_token'];
    // }

    return execute_curl($url, 'POST', $data, $headers);
}


// --- Afiliasi ---
function track_referral($ref_code)
{
    if (!isset($_COOKIE[REFERRAL_COOKIE_NAME]) || $_COOKIE[REFERRAL_COOKIE_NAME] !== $ref_code) {
        setcookie(REFERRAL_COOKIE_NAME, $ref_code, REFERRAL_COOKIE_EXPIRY, '/');
    }
}

function get_affiliate_id_from_cookie()
{
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
function request_xendit_invoice($product_id, $customer_email, $referral_code = null)
{

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

function request_doku_invoice($product_id, $customer_email, $referral_code = null)
{

    // GANTI: Sesuaikan URL ini dengan endpoint Deno Edge Function Anda
    // Contoh: https://[PROJECT_REF].supabase.co/functions/v1/doku-invoice
    $DOKU_INVOICE_API_URL = SUPABASE_URL . "/functions/v1/doku-invoice";
    // --- 1. Siapkan Payload ---
    $payload = [
        'product_id' => $product_id,
        'customer_name' => 'testing',
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
    $ch = curl_init($DOKU_INVOICE_API_URL);
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

function check_and_update_doku_order_full(string $invoiceNumber, string $dokuRequestId)
{
   // ===============================
    // 1. SETUP VARIABLES
    // ===============================
    $timestamp = gmdate('Y-m-d\TH:i:s\Z');
    $requestId = trim($dokuRequestId);
    
    // Tentukan path endpoint (Request-Target)
    // Pastikan ini SAMA PERSIS dengan bagian akhir URL (mulai dari slash pertama setelah domain)
    $requestTarget = '/orders/v1/status/' . $invoiceNumber;

    // ===============================
    // 2. GENERATE SIGNATURE
    // ===============================
    // Format standar DOKU: ClientId + RequestId + Timestamp + RequestTarget + Digest
    // Karena method GET (Status Check), Digest biasanya kosong atau tidak dimasukkan.
    
    $stringToSign = "Client-Id:" . trim(DOKU_CLIENT_ID) . "\n" .
                    "Request-Id:" . $requestId . "\n" .
                    "Request-Timestamp:" . $timestamp . "\n" .
                    "Request-Target:" . $requestTarget;
    
    // Note: Jika format di atas gagal, coba format legacy (concatenation tanpa label):
    // $stringToSign = trim(DOKU_CLIENT_ID) . $requestId . $timestamp . $requestTarget;

    // PENTING: Gunakan parameter 'true' pada hash_hmac untuk output binary, lalu base64_encode
    $signatureRaw = hash_hmac('sha256', $stringToSign, trim(DOKU_SECRET_KEY), true);
    $signatureBase64 = base64_encode($signatureRaw);
    
    // Tambahkan prefix HMACSHA256=
    $finalSignature = 'HMACSHA256=' . $signatureBase64;

    // ===============================
    // 3. PREPARE HEADERS
    // ===============================
    $headers = [
        'Client-Id: ' . trim(DOKU_CLIENT_ID),
        'Request-Id: ' . $requestId,
        'Request-Timestamp: ' . $timestamp,
        'Signature: ' . $finalSignature, // Gunakan signature yang sudah ada prefix
    ];

    $url = DOKU_BASE_URL . $requestTarget; // Gunakan requestTarget agar konsisten
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_TIMEOUT        => 30,
    ]);

    $response = curl_exec($ch);
    curl_close($ch);
    // print_r($response);
    // die();
    if (!$response) return false;

    $body = json_decode($response, true);
    if (!$body) return false;

    // ===============================
    // 2. AMBIL DATA DOKU
    // ===============================
    $dokuStatus    = $body['transaction']['status'] ?? null;
    $invoiceNumber = $body['order']['invoice_number'] ?? null;
    // $dokuPaymentId = $body['payment']['payment_id'] ?? null;

    if (!$invoiceNumber) return false;

    // ===============================
    // 3. NORMALIZE STATUS
    // ===============================
    $dbStatus = 'PENDING';
    $isPaid = in_array($dokuStatus, ['SUCCESS', 'SETTLED']);

    if ($isPaid) {
        $dbStatus = 'SUCCESS';
    } elseif (in_array($dokuStatus, ['FAILED', 'EXPIRED'])) {
        $dbStatus = $dokuStatus;
    }

    // ===============================
    // 4. UPDATE TRANSACTION
    // ===============================
    $updatePayload = [
        'status' => $dbStatus,
        // 'doku_payment_id' => $dokuPaymentId,
        'paid_at' => $isPaid ? date('c') : null,
    ];

    $trxRes = supabase_fetch(
        "/transactions?doku_invoice_number=eq.$invoiceNumber",
        'PATCH',
        $updatePayload
    );
    
    if ($trxRes['status'] !== 200 || empty($trxRes['data'][0])) {
        return false;
    }

    $trx = $trxRes['data'][0];

    // ===============================
    // 5. KOMISI AFILIASI
    // ===============================
    if ($dbStatus === 'SUCCESS' && !empty($trx['affiliate_user_id'])) {

        $affiliateId = $trx['affiliate_user_id'];
        $productId   = $trx['product_id'];

        // A. Ambil commission rate
        $productRes = supabase_fetch("/products?select=commission_rate_price&id=eq.$productId");

        $commissionRate = $productRes['data'][0]['commission_rate_price'] ?? 0;

        if ($commissionRate > 0) {

            // B. Cek duplikasi
            $refCheck = supabase_fetch(
                "/referrals?select=id&transaction_id=eq.{$trx['id']}"
            );

            if (empty($refCheck['data'])) {

                // C. Insert referral
                supabase_fetch('/referrals', 'POST', [
                    'transaction_id'    => $trx['id'],
                    'affiliate_user_id' => $affiliateId,
                    'commission_earned' => $commissionRate
                ]);

                // D. Update wallet affiliate
                $walletRes = supabase_fetch(
                    "/affiliate_details?select=wallet_balance&user_id=eq.$affiliateId"
                );

                $oldBalance = $walletRes['data'][0]['wallet_balance'] ?? 0;
                $newBalance = $oldBalance + $commissionRate;

                supabase_fetch(
                    "/affiliate_details?user_id=eq.$affiliateId",
                    'PATCH',
                    ['wallet_balance' => $newBalance]
                );
            }
        }
    }

    return $dbStatus;
}
