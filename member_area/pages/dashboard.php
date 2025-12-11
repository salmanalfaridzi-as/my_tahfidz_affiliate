<?php
// pages/dashboard.php
include '../../config.php';
include '../../functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
$user_id = $_SESSION['user_id'];

// 1. Ambil detail afiliasi
$aff_res = supabase_fetch("/affiliate_details?user_id=eq.$user_id&select=referral_code,wallet_balance");
$aff_data = $aff_res['data'][0] ?? ['referral_code' => 'N/A', 'wallet_balance' => 0];
$referral_code = $aff_data['referral_code'];
$current_balance = $aff_data['wallet_balance'];

// 2. Logika Payout (Menggunakan RPC yang sudah dibuat di FASE 1)
// ... [LOGIKA PENGAJUAN PAYOUT DARI JAWABAN SEBELUMNYA] ...

// 3. Ambil Riwayat Komisi
$referrals_res = supabase_fetch("/referrals?affiliate_user_id=eq.$user_id&select=*,transactions(buyer_email,total_amount)&order=id.desc");
$referrals = $referrals_res['data'] ?? [];
?>

<!DOCTYPE html>
<html>
<body>
    <h2>Selamat Datang, Afiliasi!</h2>
    <p>Kode Referal Anda: <strong><?php echo htmlspecialchars($referral_code); ?></strong></p>
    <p>Tautan Afiliasi: 
        <a href="http://lokalhost/[DOMAIN_ANDA]/product.php?ref=<?php echo $referral_code; ?>">
            LINK PRODUK
        </a>
    </p>

    <h3>Saldo Komisi: Rp<?php echo number_format($current_balance, 0, ',', '.'); ?></h3>

    <h3>Riwayat Komisi Terbaru</h3>
    <table>
        <tr><th>Pembeli</th><th>Total Transaksi</th><th>Komisi Diterima</th></tr>
        <?php foreach ($referrals as $ref): ?>
        <tr>
            <td><?php echo htmlspecialchars($ref['transactions']['buyer_email']); ?></td>
            <td>Rp<?php echo number_format($ref['transactions']['total_amount'], 0); ?></td>
            <td>Rp<?php echo number_format($ref['commission_earned'], 0); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>

    <p><a href="logout.php">Logout</a></p>
</body>
</html>