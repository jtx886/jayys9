<?php
/**
 * Jay影视 - 应用引导文件（所有页面统一引入）
 */
/* 禁止直接通过 URL 访问本文件 */
if (count(get_included_files()) === 1) { http_response_code(403); exit('Forbidden'); }

if (!defined('APP_ROOT')) { define('APP_ROOT', dirname(__DIR__)); }
require_once APP_ROOT . '/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('JAYSESS');
    session_set_cookie_params(0, '/', '', false, true);
    @session_start();
}

require_once APP_ROOT . '/includes/db.php';
require_once APP_ROOT . '/includes/helpers.php';
require_once APP_ROOT . '/includes/settings.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/mailer.php';
require_once APP_ROOT . '/includes/tmdb.php';
require_once APP_ROOT . '/includes/source.php';

/* 运行环境兼容：PHP 7.4 ~ 8.x 统一关闭 mysqli 异常抛出 */
if (class_exists('mysqli')) { mysqli_report(MYSQLI_REPORT_OFF); }
