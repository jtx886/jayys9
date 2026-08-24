<?php
/**
 * Jay影视 - 管理后台公共引导（独立管理员会话 + 布局）
 */
/* 禁止直接通过 URL 访问本文件 */
if (count(get_included_files()) === 1) { http_response_code(403); exit('Forbidden'); }

if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require_once APP_ROOT . '/includes/init.php';

/* ---------- 管理员会话（与前台用户会话分离） ---------- */
function admin_user()
{
    static $admin = false;
    if ($admin !== false) return $admin;
    $admin = null;
    if (!empty($_SESSION['admin_id'])) {
        $a = DB::row("SELECT * FROM users WHERE id = ? AND is_admin = 1", array((int)$_SESSION['admin_id']));
        if ($a) $admin = $a;
    }
    return $admin;
}

function require_admin()
{
    $a = admin_user();
    if (!$a) redirect('login.php');
    return $a;
}

/* ---------- 后台布局 ---------- */
function admin_header($opts = array())
{
    $title = isset($opts['title']) ? $opts['title'] : '管理后台';
    $active = isset($opts['active']) ? $opts['active'] : '';
    $admin = admin_user();

    $navItems = array(
        'dashboard'    => array('仪表盘',   'index.php',        'ic-home'),
        'users'        => array('用户管理', 'users.php',        'ic-user'),
        'sources'      => array('播放源管理', 'sources.php',    'ic-film'),
        'mail'         => array('邮件推送', 'mail.php',         'ic-mail'),
        'announcement' => array('网站公告', 'announcement.php', 'ic-megaphone'),
        'feedback'     => array('反馈管理', 'feedback.php',     'ic-chat'),
        'settings'     => array('网站设置', 'settings.php',     'ic-crown'),
    );

    $nav = '';
    foreach ($navItems as $k => $n) {
        $nav .= '<a class="anav' . ($active === $k ? ' active' : '') . '" href="' . $n[1] . '">'
            . '<span class="ic ' . $n[2] . '"></span><span>' . $n[0] . '</span></a>';
    }

    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="csrf" content="' . csrf_token() . '">'
        . '<title>' . e($title) . ' - ' . e(site_name()) . '管理后台</title>'
        . theme_style_tag()
        . '<link rel="stylesheet" href="../assets/css/style.css?v=' . APP_VERSION . '">'
        . '<link rel="stylesheet" href="../assets/css/admin.css?v=' . APP_VERSION . '">'
        . '</head><body class="admin-body"><div class="admin-shell">';

    echo '<aside class="admin-side">'
        . '<div class="admin-brand"><span class="brand-logo"></span><div><b>' . e(site_name()) . '</b><i>管理后台</i></div></div>'
        . '<nav class="admin-nav">' . $nav . '</nav>'
        . '<div class="admin-side-foot">'
        . '<a href="../index.php" target="_blank"><span class="ic ic-eye"></span>查看前台</a>'
        . '<a href="logout.php"><span class="ic ic-logout"></span>退出登录</a>'
        . '</div></aside>';

    echo '<div class="admin-main">'
        . '<header class="admin-topbar"><h1>' . e($title) . '</h1>'
        . '<div class="admin-me"><span class="ic ic-crown"></span>'
        . '<span class="admin-me-name">' . e($admin ? $admin['username'] : '') . '<span class="badge-dev">开发者</span></span>'
        . '</div></header>'
        . '<main class="admin-content">';
}

function admin_footer()
{
    echo '</main></div></div>';
    echo '<script src="../assets/js/main.js?v=' . APP_VERSION . '"></script></body></html>';
}

/* 管理员用户名（带红色开发者标识） */
function admin_name_badged($username, $isAdmin)
{
    return e($username) . ((int)$isAdmin === 1 ? '<span class="badge-dev">开发者</span>' : '');
}
