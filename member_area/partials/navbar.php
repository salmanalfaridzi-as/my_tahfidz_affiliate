<?php 
// Pastikan user_id ada
if (!isset($_SESSION['user_id'])) {
    // Fallback jika session hilang (opsional, sesuaikan dengan logic auth Anda)
    $profile = ["name" => "Guest", "email" => "guest@example.com"];
} else {
    $user_id = $_SESSION['user_id'];
    $profile_res = supabase_fetch("/user_profile?select=name,email,user_id&user_id=eq.$user_id");
    $profile = $profile_res["data"][0] ?? ["name" => "User", "email" => "user@example.com"];
}

// Logika agar menu Affiliate tetap terbuka saat anak menunya aktif
$is_affiliate_active = in_array($active, ['affiliate_commission', 'affiliate_link', 'affiliate_coupon', 'affiliate_order']);
?>

<nav class="app-header navbar navbar-expand bg-body shadow-sm border-bottom-0"> 
    <div class="container-fluid"> 
        <ul class="navbar-nav"> 
            <li class="nav-item"> 
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button"> 
                    <i class="bi bi-list fs-4"></i> 
                </a> 
            </li> 
        </ul> 

        <ul class="navbar-nav ms-auto"> 
            <li class="nav-item dropdown user-menu"> 
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown"> 
                    <div class="d-flex flex-column text-end d-none d-md-block" style="line-height: 1.2;">
                        <span class="fw-bold small"><?= htmlspecialchars($profile["name"]) ?></span>
                    </div>
                    <div class="bg-primary-subtle text-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 35px; height: 35px;">
                        <i class="bi bi-person-fill fs-5"></i>
                    </div>
                </a> 
                
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end shadow border-0 mt-2"> 
                    <li class="user-header text-bg-primary bg-gradient"> 
                        <div class="d-flex flex-column align-items-center justify-content-center h-100">
                            <i class="bi bi-person-circle display-6 mb-2"></i>
                            <p class="mb-0 fw-bold fs-5">
                                <?= htmlspecialchars($profile["name"]) ?>
                            </p>
                            <small class="opacity-75"><?= htmlspecialchars($profile["email"]) ?></small>
                        </div>
                    </li> 
                    
                    <li class="user-footer bg-light d-flex justify-content-between p-3"> 
                        <a href="../profile/" class="btn btn-outline-secondary btn-sm btn-flat">
                            <i class="bi bi-person-gear me-1"></i> Profile
                        </a> 
                        <a href="../auth/logout.php" class="btn btn-danger btn-sm btn-flat">
                            Logout <i class="bi bi-box-arrow-right ms-1"></i>
                        </a> 
                    </li> 
                </ul> 
            </li> 
        </ul> 
    </div> 
</nav> 

<aside class="app-sidebar bg-dark shadow" data-bs-theme="dark"> 
    <div class="sidebar-brand border-bottom border-secondary"> 
        <a href="../home/" class="brand-link text-decoration-none"> 
            <img src="../../assets/images/logo-mytahfidz.png" alt="Logo" class="brand-image img-circle elevation-3" style="opacity: .8"> 
        </a> 
    </div> 

    <div class="sidebar-wrapper"> 
        <nav class="mt-3"> 
            <ul class="nav sidebar-menu flex-column" data-lte-toggle="treeview" role="navigation" aria-label="Main navigation" data-accordion="false"> 
                
                <li class="nav-header opacity-50 small fw-bold">UTAMA</li>

                <li class="nav-item"> 
                    <a href="../home/index.php" class="nav-link <?= $active == 'home' ? 'active' : '';?>"> 
                        <i class="nav-icon bi bi-speedometer2"></i> 
                        <p>Dashboard</p> 
                    </a> 
                </li> 

                <li class="nav-header opacity-50 small fw-bold mt-2">ALAT PROMOSI</li>

                <li class="nav-item <?= $is_affiliate_active ? 'menu-open' : '' ?>"> 
                    <a href="#" class="nav-link <?= $is_affiliate_active ? 'active' : '' ?>"> 
                        <i class="nav-icon bi bi-share-fill"></i> 
                        <p> 
                            Affiliate 
                            <i class="nav-arrow bi bi-chevron-right"></i> 
                        </p> 
                    </a> 
                    <ul class="nav nav-treeview"> 
                        <li class="nav-item"> 
                            <a href="../affiliate/link.php" class="nav-link <?= $active == 'affiliate_link' ? 'active' : '';?>"> 
                                <i class="nav-icon bi bi-circle ms-1" style="font-size: 0.6rem;"></i> 
                                <p>Generate Link</p> 
                            </a> 
                        </li>
                        <li class="nav-item"> 
                            <a href="../affiliate/commission.php" class="nav-link <?= $active == 'affiliate_commission' ? 'active' : '';?>"> 
                                <i class="nav-icon bi bi-circle ms-1" style="font-size: 0.6rem;"></i> 
                                <p>Komisi Saya</p> 
                            </a> 
                        </li> 
                    </ul> 
                </li> 

                <li class="nav-header opacity-50 small fw-bold mt-2">KEUANGAN & AKUN</li>

                <li class="nav-item"> 
                    <a href="../withdraw/" class="nav-link <?= $active == 'withdraw' ? 'active' : '';?>"> 
                        <i class="nav-icon bi bi-cash-coin"></i> 
                        <p>Withdraw (Tarik Dana)</p> 
                    </a> 
                </li> 

                <li class="nav-item"> 
                    <a href="../profile/" class="nav-link <?= $active == 'profile' ? 'active' : '';?>"> 
                        <i class="nav-icon bi bi-person-badge"></i> 
                        <p>Profil Saya</p> 
                    </a> 
                </li> 

                <li class="nav-item mt-3">
                    <hr class="sidebar-divider border-secondary my-0">
                </li>

                <li class="nav-item"> 
                    <a href="../auth/logout.php" class="nav-link text-danger"> 
                        <i class="nav-icon bi bi-box-arrow-left"></i> 
                        <p>Logout</p> 
                    </a> 
                </li> 
            </ul> 
        </nav> 
    </div> 
</aside>