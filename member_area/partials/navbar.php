<?php 

$user_id = $_SESSION['user_id'];
$profile = supabase_fetch("/user_profile?select=name,email,user_id&user_id=eq.$user_id");
$profile = $profile["data"][0];
?>
<nav class="app-header navbar navbar-expand bg-body">
    <!--begin::Container-->
    <div class="container-fluid">
        <!--begin::Start Navbar Links-->
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-lte-toggle="sidebar" href="#" role="button">
                    <i class="bi bi-list"></i>
                </a>
            </li>
        </ul>
        <!--end::Start Navbar Links-->
        <!--begin::End Navbar Links-->
        <ul class="navbar-nav ms-auto">
        
            <li class="nav-item dropdown user-menu">
                <a href="#" class="nav-link dropdown-toggle d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <span class="d-none d-md-inline"><?= $profile["name"] ?></span>
                     <i class="bi bi-person-circle fs-4"></i>
                </a>
                <ul class="dropdown-menu dropdown-menu-lg dropdown-menu-end">
                    <!--begin::User Image-->
                    <li class="text-bg-primary p-3">
                        <div class="d-flex flex-column align-items-center">
                            <strong><?= $profile["name"] ?></strong>
                            <small><?= $profile["email"] ?></small>
                        </div>
                    </li>

                    <!--end::User Image-->
                    <!--begin::Menu Footer-->
                    <li class="user-footer">
                        <a href="#" class="btn btn-default btn-flat">Profile</a>
                        <a href="auth/logout.php" class="btn btn-default btn-flat float-end">Logout</a>
                    </li>
                    <!--end::Menu Footer-->
                </ul>
            </li>
            <!--end::User Menu Dropdown-->
        </ul>
        <!--end::End Navbar Links-->
    </div>
    <!--end::Container-->
</nav>
<aside class="app-sidebar bg-body-secondary shadow" data-bs-theme="dark">
      <!--begin::Sidebar Brand-->
      <div class="sidebar-brand">
        <!--begin::Brand Link-->
        <a href="./index.html" class="brand-link">
          <!--begin::Brand Image-->
          <img
              src="../../assets/images/logo-mytahfidz.png"
              alt="My Tahfidz Logo"
              class="brand-image opacity-75 shadow"
            />
          <!--end::Brand Image-->
        </a>
        <!--end::Brand Link-->
      </div>
      <!--end::Sidebar Brand-->
      <!--begin::Sidebar Wrapper-->
      <div class="sidebar-wrapper">
        <nav class="mt-2">
          <!--begin::Sidebar Menu-->
          <ul
            class="nav sidebar-menu flex-column"
            data-lte-toggle="treeview"
            role="navigation"
            aria-label="Main navigation"
            data-accordion="false"
            id="navigation">
            <li class="nav-item">
              <a href="../home/index.php" class="nav-link <?= $active == 'home' ? 'active' : '';?>">
                <i class="nav-icon bi bi-list"></i>
                <p>
                  Home
                </p>
              </a>
            </li>
            <li class="nav-item">
              <a href="#" class="nav-link">
                <i class="nav-icon bi bi-megaphone-fill"></i>
                <p>
                  Affiliate
                  <i class="nav-arrow bi bi-chevron-right"></i>
                </p>
              </a>
              <ul class="nav nav-treeview">
                <li class="nav-item">
                  <a href="../affiliate/commission.php" class="nav-link <?= $active == 'affiliate_commission' ? 'active' : '';?>">
                    <p>Commission</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="../affiliate/link.php" class="nav-link <?= $active == 'affiliate_link' ? 'active' : '';?>">
                    <p>Link</p>
                  </a>
                </li>
                <!-- <li class="nav-item">
                  <a href="../affiliate/coupon.php" class="nav-link <?= $active == 'affiliate_coupon' ? 'active' : '';?>">
                    <p>Coupon</p>
                  </a>
                </li>
                <li class="nav-item">
                  <a href="../affiliate/order.php" class="nav-link <?= $active == 'affiliate_order' ? 'active' : '';?>">
                    <p>Order</p>
                  </a>
                </li> -->
              </ul>
            </li>
            <!-- <li class="nav-item">
                <a href="index.php" class="nav-link <?= $active == 'leaderboard' ? 'active' : '';?>">
                  <i class="nav-icon bi bi-trophy-fill"></i>
                  <p>
                    Leaderboard
                  </p>
                </a>
              </li> -->
              <!-- <li class="nav-item">
                <a href="index.php" class="nav-link <?= $active == 'order' ? 'active' : '';?>">
                  <i class="nav-icon bi bi-cart-fill"></i>
                  <p>
                    Order
                  </p>
                </a>
              </li> -->
              <li class="nav-item">
                <a href="../withdraw/" class="nav-link <?= $active == 'withdraw' ? 'active' : '';?>">
                  <i class="nav-icon bi bi-cash"></i>
                  <p>
                    Withdraw
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="../profile/" class="nav-link <?= $active == 'profile' ? 'active' : '';?>">
                  <i class="nav-icon bi bi-person-fill"></i>
                  <p>
                    Profile
                  </p>
                </a>
              </li>
              <li class="nav-item">
                <a href="../auth/logout.php" class="nav-link <?= $active == 'logout' ? 'active' : '';?>">
                  <i class="nav-icon bi bi-box-arrow-left"></i>
                  <p>
                    Logout
                  </p>
                </a>
              </li>
          </ul>
          <!--end::Sidebar Menu-->
        </nav>
      </div>
      <!--end::Sidebar Wrapper-->
    </aside>