<?php

use App\Auth;
use function App\{e, csrf, csrfField};

$user = Auth::user();
$current = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$title = $title ?? 'ค้นพบรายวิชา';
require_once __DIR__ . '/icons.php';
?>
<!DOCTYPE html>
<html lang="th">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <meta name="csrf-token" content="<?= e(csrf()) ?>">
    <meta name="description" content="ศูนย์การศึกษาอาชีพออนไลน์ วิทยาลัยเทคนิคกำแพงเพชร สำหรับนักเรียน ปวช. ปวส. และประชาชนทั่วไป">
    <title><?= e($title) ?> · VEKEC | วิทยาลัยเทคนิคกำแพงเพชร</title>
    <link rel="icon" href="/assets/favicon.svg" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans+Thai:wght@400;450;500;550;600;650;700;750&display=swap" rel="stylesheet"><?php if (!empty($richEditor)): ?>
        <link rel="stylesheet" href="/assets/vendor/quill/quill.snow.css"><?php endif; ?>
    <link rel="stylesheet" href="/assets/app.css?v=<?= filemtime(dirname(__DIR__).'/public/assets/app.css') ?>"><?php if (!empty($certificateAssets)): ?>
        <link rel="stylesheet" href="/assets/certificate.css?v=<?= filemtime(dirname(__DIR__).'/public/assets/certificate.css') ?>">
        <script src="/assets/certificate-layout.js" defer></script><?php endif; ?>
    <link rel="stylesheet" href="/assets/rich-editor.css">
    <script src="/assets/jquery.min.js" defer></script>
    <script src="/assets/app.js?v=<?= filemtime(dirname(__DIR__).'/public/assets/app.js') ?>" defer></script><?php if (!empty($certificateDesigner)): ?><script src="/assets/certificate-designer.js?v=<?= filemtime(dirname(__DIR__).'/public/assets/certificate-designer.js') ?>" defer></script><?php endif; ?><?php if (!empty($richEditor)): ?><script src="/assets/vendor/quill/quill.js" defer></script>
        <script src="/assets/rich-editor.js" defer></script><?php endif; ?>
<?php if (!empty($aiDraftAssets)): ?>
    <link rel="stylesheet" href="/assets/ai-draft.css?v=<?= filemtime(dirname(__DIR__).'/public/assets/ai-draft.css') ?>">
    <script src="/assets/ai-draft.js?v=<?= filemtime(dirname(__DIR__).'/public/assets/ai-draft.js') ?>" defer></script>
<?php endif; ?>
</head>

<body>
    <a class="skip-link" href="#main">ข้ามไปยังเนื้อหา</a>
    <header class="topbar">
        <div class="brand-group"><button class="icon-button mobile-menu" aria-label="เปิดเมนู" aria-expanded="false" aria-controls="sidebar"><?= icon('menu') ?></button><a href="/" class="brand"><span class="brand-mark"><?= icon('school') ?></span><span><strong>VEKEC<span class="brand-dot">.</span></strong><small>วิทยาลัยเทคนิคกำแพงเพชร</small></span></a><span class="brand-divider"></span><span class="topbar-description">ศูนย์การศึกษาอาชีพ อาชีวศึกษา</span></div>
        <nav class="account-nav" aria-label="บัญชีผู้ใช้"><?php if ($user): ?><a href="/profile" class="account"><span class="avatar"><?= e(preg_split('//u', $user['name'], -1, PREG_SPLIT_NO_EMPTY)[0] ?? 'U') ?></span><span class="account-name"><?= e($user['name']) ?><small><?= ['admin' => 'ผู้ดูแลระบบ', 'teacher' => 'ครูผู้สอน', 'student' => 'ผู้เรียน'][$user['role']] ?></small></span></a>
                <form method="post" action="/logout"><?= csrfField() ?><button class="icon-button" title="ออกจากระบบ" aria-label="ออกจากระบบ"><?= icon('logout') ?></button></form><?php else: ?><a href="/login" class="login-link">เข้าสู่ระบบ</a><a href="/register" class="button small">สมัครสมาชิก <?= icon('arrow') ?></a><?php endif; ?>
        </nav>
    </header>
    <div class="app-shell">
        <aside id="sidebar" class="sidebar">
            <div class="sidebar-label">พื้นที่การเรียนรู้</div>
            <nav aria-label="เมนูหลัก">
                <a href="/" class="nav-item <?= $current === '/' ? 'active' : '' ?>"><?= icon('compass') ?>ค้นพบรายวิชา<?php if ($current === '/'): ?><span class="nav-active-dot"></span><?php endif; ?></a>
                <a href="/dashboard" class="nav-item <?= $current === '/dashboard' ? 'active' : '' ?>"><?= icon('book') ?>การเรียนรู้ของฉัน</a>
                <a href="/certificates" class="nav-item <?= $current === '/certificates' ? 'active' : '' ?>"><?= icon('award') ?>ใบประกาศนียบัตร</a>
                <?php if ($user && in_array($user['role'], ['teacher', 'admin'])): ?><div class="sidebar-label spaced">สำหรับครูผู้สอน</div><a href="/teach" class="nav-item <?= str_starts_with($current, '/teach') ? 'active' : '' ?>"><?= icon('layers') ?>จัดการรายวิชา</a><?php endif; ?>
                <?php if ($user && in_array($user['role'], ['teacher','admin'])): ?><a href="/teach/ai-connections" class="nav-item <?= $current === '/teach/ai-connections' ? 'active' : '' ?>"><?= icon('spark') ?>การเชื่อมต่อ AI</a><?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?><a href="/admin" class="nav-item <?= ($current === '/admin' || str_starts_with($current, '/admin/users')) ? 'active' : '' ?>"><?= icon('settings') ?>จัดการระบบ</a><?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?><a href="/admin/categories" class="nav-item <?= $current === '/admin/categories' ? 'active' : '' ?>"><?= icon('layers') ?>จัดการหมวดหมู่</a><?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?><a href="/admin/certificate-design" class="nav-item <?= str_starts_with($current, '/admin/certificate-design') ? 'active' : '' ?>"><?= icon('award') ?>ออกแบบเกียรติบัตร</a><?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?><a href="/admin/ai-providers" class="nav-item <?= $current === '/admin/ai-providers' ? 'active' : '' ?>"><?= icon('spark') ?>ผู้ให้บริการ AI</a><?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?><a href="/admin/department-videos" class="nav-item <?= $current === '/admin/department-videos' ? 'active' : '' ?>"><?= icon('play') ?>วิดีโอแนะนำแผนกวิชา</a><?php endif; ?>
                <?php if ($user && $user['role'] === 'admin'): ?><a href="/admin/guidance-certificate-design" class="nav-item <?= str_starts_with($current, '/admin/guidance-certificate-design') ? 'active' : '' ?>"><?= icon('award') ?>ออกแบบเกียรติบัตรแนะแนว</a><a href="/admin/guidance-certificate-numbers" class="nav-item <?= str_starts_with($current, '/admin/guidance-certificate-numbers') ? 'active' : '' ?>"><?= icon('file') ?>เลขที่เกียรติบัตรแนะแนว</a><?php endif; ?>
                <div class="sidebar-label spaced">บัญชีของฉัน</div><a href="/profile" class="nav-item <?= $current === '/profile' ? 'active' : '' ?>"><?= icon('user') ?>ข้อมูลส่วนตัว</a>
            </nav>
            <div class="sidebar-bottom">
                <div class="help-icon"><?= icon('spark') ?></div><strong>ทักษะเล็ก ๆ<br>สร้างโอกาสที่ยิ่งใหญ่</strong>
                <p>เริ่มเรียนรู้ในแบบของคุณ<br>ได้ทุกที่ ทุกเวลา</p><a href="/#courses">สำรวจรายวิชา <?= icon('arrow') ?></a>
            </div>
            <div class="sidebar-credit"><span class="status-dot"></span> LEARNING NEVER STOPS</div>
        </aside><button class="sidebar-scrim" aria-label="ปิดเมนู"></button>
        <main id="main" class="main"><?php if (!empty($_SESSION['flash'])): ?><div class="notice" role="status"><?= icon('check') ?><?= e($_SESSION['flash']) ?></div><?php unset($_SESSION['flash']);
                                                                                                                                                                    endif; ?>
