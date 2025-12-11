<?php
// pages/login.php
include '../../config.php';
include '../../functions.php';

// Cek jika pengguna sudah login, alihkan ke dashboard
if (isset($_SESSION['access_token'])) {
  header('Location: ../index.html');
  exit;
}

$message = '';
$email_input = ''; // Untuk menjaga input email tetap ada setelah error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Sanitasi dan Validasi Input
  $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
  $password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_SPECIAL_CHARS);
  $email_input = $email;

  if ($email && $password) {
    $auth_data = [
      'email' => $email,
      'password' => $password
    ];

    // Panggil Supabase Auth Endpoint untuk mendapatkan token
    $response = supabase_auth_request('/token?grant_type=password', $auth_data);
    // var_dump($response);
    // echo $response['data']['access_token'];
    // echo $response['data']['user']['id'];
    //             echo $response['data']['user']['email'];
    // die();
    if ($response['status'] == 200) {
      // --- LOGIN BERHASIL ---

      // Simpan token dan user info di sesi. Token ini wajib untuk mengakses DB (PostgREST)
      $_SESSION['access_token'] = $response['data']['access_token'];
      $_SESSION['refresh_token'] = $response['data']['refresh_token'];
      $_SESSION['user_id'] = $response['data']['user']['id'];
      $_SESSION['user_email'] = $response['data']['user']['email'];

      // Alihkan ke Dashboard Afiliasi
      header('Location: ../index.html');
      exit;
    } else {
      // --- LOGIN GAGAL ---

      // Supabase mengembalikan error_description jika gagal
      $error_msg = $response['data']['error_description'] ?? 'Email atau password salah. Coba lagi.';
      $message = "Login Gagal: " . htmlspecialchars($error_msg);
    }
  } else {
    $message = "Mohon masukkan email dan password yang valid";
  }
}
?>
<!doctype html>
<html lang="en">
<!--begin::Head-->

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <title>Login | My Tahfidz Affiliate</title>
  <!--begin::Accessibility Meta Tags-->
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
  <meta name="color-scheme" content="light dark" />
  <meta name="theme-color" content="#007bff" media="(prefers-color-scheme: light)" />
  <meta name="theme-color" content="#1a1a1a" media="(prefers-color-scheme: dark)" />
  <!--end::Accessibility Meta Tags-->
  <!--begin::Primary Meta Tags-->
  <meta name="title" content="Login | My Tahfidz Affiliate" />
  <!-- <meta name="author" content="ColorlibHQ" />
    <meta
      name="description"
      content="AdminLTE is a Free Bootstrap 5 Admin Dashboard, 30 example pages using Vanilla JS. Fully accessible with WCAG 2.1 AA compliance."
    />
    <meta
      name="keywords"
      content="bootstrap 5, bootstrap, bootstrap 5 admin dashboard, bootstrap 5 dashboard, bootstrap 5 charts, bootstrap 5 calendar, bootstrap 5 datepicker, bootstrap 5 tables, bootstrap 5 datatable, vanilla js datatable, colorlibhq, colorlibhq dashboard, colorlibhq admin dashboard, accessible admin panel, WCAG compliant"
    /> -->
  <!--end::Primary Meta Tags-->
  <!--begin::Accessibility Features-->
  <!-- Skip links will be dynamically added by accessibility.js -->
  <meta name="supported-color-schemes" content="light dark" />
  <link rel="preload" href="../../css/adminlte/adminlte.css" as="style" />
  <!--end::Accessibility Features-->
  <!--begin::Fonts-->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css"
    integrity="sha256-tXJfXfp6Ewt1ilPzLDtQnJV4hclT9XuaZUKyUvmyr+Q="
    crossorigin="anonymous"
    media="print"
    onload="this.media='all'" />
  <!--end::Fonts-->
  <!--begin::Third Party Plugin(OverlayScrollbars)-->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css"
    crossorigin="anonymous" />
  <!--end::Third Party Plugin(OverlayScrollbars)-->
  <!--begin::Third Party Plugin(Bootstrap Icons)-->
  <link
    rel="stylesheet"
    href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css"
    crossorigin="anonymous" />
  <!--end::Third Party Plugin(Bootstrap Icons)-->
  <!--begin::Required Plugin(AdminLTE)-->
  <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
  <!--end::Required Plugin(AdminLTE)-->
</head>
<!--end::Head-->
<!--begin::Body-->

<body class="login-page bg-body-secondary">
  <div class="login-box">
    <div class="login-logo">
      <b>My Tahfidz</b> Affiliate
    </div>
    <!-- /.login-logo -->
    <div class="card">
      <div class="card-body login-card-body">
        <p class="login-box-msg">Sign in to start your session</p>
        <?php if ($message): ?>
          <div class="alert alert-danger"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <form method="post">
          <div class="input-group mb-3">
            <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo htmlspecialchars($email_input); ?>" />
            <div class="input-group-text"><span class="bi bi-envelope"></span></div>
          </div>
          <div class="input-group mb-3">
            <input type="password" class="form-control" id="password" name="password" placeholder="Password" />
            <div class="input-group-text"><span class="bi bi-lock-fill"></span></div>
          </div>


          <div class="d-flex justify-content-center align-items-center">

            <div class="col-4 w-100">
              <div class="d-grid gap-2 ">
                <button type="submit" class="btn btn-primary ">Sign In</button>
              </div>
            </div>
          </div>
        </form>
        <p class="mb-1 text-center mt-3"><a href="forgot-password.html">I forgot my password</a></p>
        <p class="mb-0 text-center">
          <a href="register.php" class="text-center"> Register a new account </a>
        </p>
      </div>
      <!-- /.login-card-body -->
    </div>
  </div>
  <!-- /.login-box -->
  <!--begin::Third Party Plugin(OverlayScrollbars)-->
  <script
    src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js"
    crossorigin="anonymous"></script>
  <!--end::Third Party Plugin(OverlayScrollbars)--><!--begin::Required Plugin(popperjs for Bootstrap 5)-->
  <script
    src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"
    crossorigin="anonymous"></script>
  <!--end::Required Plugin(popperjs for Bootstrap 5)--><!--begin::Required Plugin(Bootstrap 5)-->
  <script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js"
    crossorigin="anonymous"></script>
  <!--end::Required Plugin(Bootstrap 5)--><!--begin::Required Plugin(AdminLTE)-->
  <script src="../../js/adminlte/adminlte.js"></script>
  <!--end::Required Plugin(AdminLTE)--><!--begin::OverlayScrollbars Configure-->
  <script>
    const SELECTOR_SIDEBAR_WRAPPER = '.sidebar-wrapper';
    const Default = {
      scrollbarTheme: 'os-theme-light',
      scrollbarAutoHide: 'leave',
      scrollbarClickScroll: true,
    };
    document.addEventListener('DOMContentLoaded', function() {
      const sidebarWrapper = document.querySelector(SELECTOR_SIDEBAR_WRAPPER);
      if (sidebarWrapper && OverlayScrollbarsGlobal?.OverlayScrollbars !== undefined) {
        OverlayScrollbarsGlobal.OverlayScrollbars(sidebarWrapper, {
          scrollbars: {
            theme: Default.scrollbarTheme,
            autoHide: Default.scrollbarAutoHide,
            clickScroll: Default.scrollbarClickScroll,
          },
        });
      }
    });
  </script>
  <!--end::OverlayScrollbars Configure-->
  <!--end::Script-->
</body>
<!--end::Body-->

</html>