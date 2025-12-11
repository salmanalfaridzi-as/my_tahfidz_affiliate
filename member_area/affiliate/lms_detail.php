<?php

include '../../config.php';
include '../../functions.php';

// Cek autentikasi
if (!isset($_SESSION['access_token'])) {
    header('Location: ../auth/login.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$course_id = filter_input(INPUT_GET, 'course_id', FILTER_SANITIZE_STRING);
$material_id = filter_input(INPUT_GET, 'material_id', FILTER_SANITIZE_STRING);

$error_message = '';

if (empty($course_id)) {
    $error_message = 'ID Kursus tidak ditemukan.';
} else {
    // --- 1. Ambil Detail Kursus Utama ---
    $course_res = supabase_fetch("/lms_courses?id=eq.$course_id&select=id,title,description,content");
    $course = $course_res['data'][0] ?? null;

    if (!$course) {
        $error_message = 'Kursus tidak ditemukan atau tidak tersedia.';
    } else {
        // --- 2. Ambil Daftar Materi Kursus (Materials) ---
        // Urutkan berdasarkan sort_order
        $materials_res = supabase_fetch("/lms_materials?course_id=eq.$course_id&order=sort_order.asc&select=id,title,type,video_url,sort_order");
        $materials = $materials_res['data'] ?? [];

        if (empty($materials)) {
            $error_message = 'Belum ada materi yang ditambahkan untuk kursus ini.';
        } else {
            // --- 3. Tentukan Materi yang Sedang Aktif Dilihat ---
            $active_material = null;
            
            // Jika material_id tidak diset, ambil materi pertama sebagai default
            if (empty($material_id)) {
                $active_material = $materials[0];
            } else {
                // Cari materi yang ID-nya cocok dengan material_id di URL
                foreach ($materials as $m) {
                    if ($m['id'] === $material_id) {
                        $active_material = $m;
                        break;
                    }
                }
            }

            // Jika materi aktif sudah ditemukan, ambil konten lengkapnya
            if ($active_material) {
                // Fetch materi aktif secara detail (untuk mengambil 'content')
                $active_material_res = supabase_fetch("/lms_materials?id=eq." . $active_material['id'] . "&select=id,title,type,video_url,content");
                $active_material = $active_material_res['data'][0] ?? $active_material;
            } else {
                 $error_message = 'Materi tidak valid.';
            }
        }
    }
}

// Tentukan active menu untuk navbar
$active = 'affiliate_link'; 
?>
<!doctype html>
<html lang="en">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <title><?= htmlspecialchars($course['title'] ?? 'LMS') ?> | Iqro Affiliate</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@fontsource/source-sans-3@5.0.12/index.css" crossorigin="anonymous" media="print" onload="this.media='all'" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/styles/overlayscrollbars.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.13.1/font/bootstrap-icons.min.css" crossorigin="anonymous" />
    <link rel="stylesheet" href="../../css/adminlte/adminlte.css" />
    <style>
        .sidebar-materials {
            height: calc(100vh - 120px); /* Kurangi tinggi header/navbar */
            overflow-y: auto;
            background-color: #f8f9fa;
            border-right: 1px solid #dee2e6;
        }
        .material-item {
            border-radius: 0;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .material-item.active {
            background-color: #007bff;
            color: white !important;
            font-weight: bold;
        }
        .material-item.active .text-muted, 
        .material-item.active i {
            color: white !important;
        }
        .video-container {
            position: relative;
            width: 100%;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
            margin-bottom: 20px;
        }
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 8px;
        }
        .material-content h4 {
             border-left: 4px solid #ffc107;
             padding-left: 10px;
        }
    </style>
</head>
<body class="layout-fixed sidebar-expand-lg sidebar-open bg-body-tertiary">
<div class="app-wrapper">
    <?php include "../partials/navbar.php"; ?>
    <main class="app-main">
        
        <div class="app-content-header">
            <div class="container-fluid">
                <h3 class="mb-0">📚 Kursus: <?= htmlspecialchars($course['title'] ?? 'Detail Kursus') ?></h3>
                <p class="text-muted"><?= htmlspecialchars($course['description'] ?? '') ?></p>
            </div>
        </div>
        
        <div class="app-content">
            <div class="container-fluid">
                
                <?php if ($error_message): ?>
                    <div class="alert alert-danger"><?= $error_message ?></div>
                <?php else: ?>

                <div class="row">
                    <div class="col-lg-3">
                        <div class="card shadow-sm sidebar-materials">
                            <div class="card-header bg-dark text-white">
                                <h6 class="mb-0">Daftar Materi (<?= count($materials) ?>)</h6>
                            </div>
                            <div class="list-group list-group-flush">
                                <?php foreach ($materials as $m): 
                                    $link = "lms_detail.php?course_id=$course_id&material_id=" . $m['id'];
                                    $is_active = $active_material['id'] === $m['id'];
                                    $icon = ($m['type'] === 'video') ? 'bi-play-circle' : 'bi-file-text';
                                ?>
                                    <a href="<?= $link ?>" 
                                       class="list-group-item list-group-item-action material-item <?= $is_active ? 'active' : '' ?>">
                                        <i class="bi <?= $icon ?> me-2"></i> 
                                        <span class="text-muted me-1"><?= $m['sort_order'] ?>.</span>
                                        <?= htmlspecialchars($m['title']) ?>
                                    </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9">
                        <div class="card shadow">
                            <div class="card-header bg-light">
                                <h4 class="mb-0 text-primary"><i class="bi <?= $active_material['type'] === 'video' ? 'bi-play-fill' : 'bi-file-earmark-text' ?> me-2"></i> <?= htmlspecialchars($active_material['title'] ?? 'Pilih Materi') ?></h4>
                            </div>
                            <div class="card-body">
                                <?php if ($active_material && $active_material['type'] === 'video' && !empty($active_material['video_url'])): ?>
                                    <div class="video-container">
                                        <iframe src="<?= htmlspecialchars($active_material['video_url']) ?>?autoplay=0&rel=0" 
                                                frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" 
                                                allowfullscreen></iframe>
                                    </div>
                                <?php endif; ?>

                                <div class="material-content">
                                    <?php if ($active_material): ?>
                                        <h4>Deskripsi Materi</h4>
                                        <div class="p-3 border rounded">
                                            <?= $active_material['content'] ?? 'Tidak ada deskripsi konten.' ?>
                                        </div>
                                    <?php else: ?>
                                         <div class="alert alert-info">Silakan pilih materi dari daftar di samping untuk mulai belajar.</div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <?php endif; ?>
            </div>
        </div>
    </main>
</div> 
<script src="https://cdn.jsdelivr.net/npm/overlayscrollbars@2.11.0/browser/overlayscrollbars.browser.es6.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.7/dist/js/bootstrap.min.js" crossorigin="anonymous"></script>
<script src="../../js/adminlte/adminlte.js"></script>

</body>
</html>