<?php
/**
 * Jay影视 - 一键安装脚本
 * 访问 /install.php 自动建表并初始化默认数据（默认管理员：杰同学 / 101113）
 * 安装完成后请删除本文件！
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
date_default_timezone_set('Asia/Shanghai');

require_once __DIR__ . '/config.php';

$installedLock = __DIR__ . '/data/installed.lock';
$done = false; $error = ''; $steps = array();

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if (file_exists($installedLock)) {
    $error = '检测到系统已安装（data/installed.lock 存在）。如需重新安装，请先删除该锁文件。';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (version_compare(PHP_VERSION, '7.4.0', '<')) throw new Exception('PHP 版本过低，需要 PHP 7.4 及以上，当前 ' . PHP_VERSION);
        if (!class_exists('mysqli')) throw new Exception('缺少 mysqli 扩展');
        if (!function_exists('curl_init') && !ini_get('allow_url_fopen')) throw new Exception('需要 curl 扩展或 allow_url_fopen');

        mysqli_report(MYSQLI_REPORT_OFF);
        $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
        if (!$conn) throw new Exception('数据库连接失败：' . mysqli_connect_error());
        mysqli_set_charset($conn, 'utf8mb4');

        $tables = array();

        /* 用户表 */
        $tables['users'] = "CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(50) NOT NULL,
            `email` VARCHAR(120) NOT NULL,
            `password` VARCHAR(255) NOT NULL,
            `avatar` VARCHAR(255) DEFAULT '',
            `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
            `status` TINYINT(1) NOT NULL DEFAULT 1 COMMENT '1正常 0封禁',
            `ban_reason` VARCHAR(500) DEFAULT NULL,
            `ban_start` DATETIME DEFAULT NULL,
            `ban_end` DATETIME DEFAULT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_username` (`username`),
            UNIQUE KEY `uk_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 邮箱验证码表 */
        $tables['email_codes'] = "CREATE TABLE IF NOT EXISTS `email_codes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(120) NOT NULL,
            `code` VARCHAR(10) NOT NULL,
            `expires_at` DATETIME NOT NULL,
            `used` TINYINT(1) NOT NULL DEFAULT 0,
            `sent_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_email` (`email`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 收藏表 */
        $tables['favorites'] = "CREATE TABLE IF NOT EXISTS `favorites` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `tmdb_id` INT UNSIGNED NOT NULL,
            `media_type` VARCHAR(10) NOT NULL DEFAULT 'movie',
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `poster` VARCHAR(500) DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_user_media` (`user_id`,`tmdb_id`,`media_type`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 观看历史表 */
        $tables['watch_history'] = "CREATE TABLE IF NOT EXISTS `watch_history` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `tmdb_id` INT UNSIGNED NOT NULL,
            `media_type` VARCHAR(10) NOT NULL DEFAULT 'movie',
            `title` VARCHAR(255) NOT NULL DEFAULT '',
            `poster` VARCHAR(500) DEFAULT '',
            `episode` VARCHAR(80) NOT NULL DEFAULT '',
            `position` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT '播放秒数',
            `duration` INT UNSIGNED NOT NULL DEFAULT 0,
            `updated_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_user_media_ep` (`user_id`,`tmdb_id`,`episode`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 反馈表 */
        $tables['feedback'] = "CREATE TABLE IF NOT EXISTS `feedback` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `user_id` INT UNSIGNED NOT NULL,
            `title` VARCHAR(200) NOT NULL,
            `content` TEXT NOT NULL,
            `is_public` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 反馈回复表 */
        $tables['feedback_replies'] = "CREATE TABLE IF NOT EXISTS `feedback_replies` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `feedback_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `content` TEXT NOT NULL,
            `is_admin` TINYINT(1) NOT NULL DEFAULT 0,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_fid` (`feedback_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 反馈点赞表 */
        $tables['feedback_likes'] = "CREATE TABLE IF NOT EXISTS `feedback_likes` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `feedback_id` INT UNSIGNED NOT NULL,
            `user_id` INT UNSIGNED NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_fid_user` (`feedback_id`,`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 网站公告表 */
        $tables['announcements'] = "CREATE TABLE IF NOT EXISTS `announcements` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title` VARCHAR(200) NOT NULL,
            `content` TEXT NOT NULL,
            `is_active` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 播放源表 */
        $tables['play_sources'] = "CREATE TABLE IF NOT EXISTS `play_sources` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `name` VARCHAR(100) NOT NULL,
            `api_url` VARCHAR(500) NOT NULL,
            `is_default` TINYINT(1) NOT NULL DEFAULT 0,
            `sort` INT NOT NULL DEFAULT 0,
            `status` TINYINT(1) NOT NULL DEFAULT 1,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 站点设置表 */
        $tables['settings'] = "CREATE TABLE IF NOT EXISTS `settings` (
            `skey` VARCHAR(60) NOT NULL,
            `svalue` TEXT,
            PRIMARY KEY (`skey`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 媒体缓存表（TMDB / 播放源 / m3u8时长） */
        $tables['media_cache'] = "CREATE TABLE IF NOT EXISTS `media_cache` (
            `cache_key` VARCHAR(80) NOT NULL,
            `content` LONGTEXT,
            `expires_at` DATETIME DEFAULT NULL,
            PRIMARY KEY (`cache_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        /* 邮件发送日志表 */
        $tables['mail_log'] = "CREATE TABLE IF NOT EXISTS `mail_log` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `email` VARCHAR(120) NOT NULL,
            `subject` VARCHAR(255) DEFAULT '',
            `status` TINYINT(1) NOT NULL DEFAULT 0,
            `error` VARCHAR(500) DEFAULT '',
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_time` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        foreach ($tables as $name => $sql) {
            if (!mysqli_query($conn, $sql)) throw new Exception("创建表 {$name} 失败：" . mysqli_error($conn));
            $steps[] = '创建数据表 ' . $name . ' ✓';
        }

        $now = date('Y-m-d H:i:s');

        /* 默认管理员：杰同学 / 101113 */
        $adminExists = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM users WHERE username = '杰同学'")) > 0;
        if (!$adminExists) {
            $hash = password_hash('101113', PASSWORD_DEFAULT);
            $stmt = mysqli_prepare($conn, "INSERT INTO users (username, email, password, is_admin, status, created_at) VALUES ('杰同学', ?, ?, 1, 1, ?)");
            $adminEmail = 'admin@jaymovie.local';
            mysqli_stmt_bind_param($stmt, 'sss', $adminEmail, $hash, $now);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $steps[] = '创建默认管理员「杰同学」（密码 101113）✓';
        } else {
            $steps[] = '管理员已存在，跳过 ✓';
        }

        /* 默认播放源 */
        $srcExists = mysqli_num_rows(mysqli_query($conn, "SELECT id FROM play_sources LIMIT 1")) > 0;
        if (!$srcExists) {
            $stmt = mysqli_prepare($conn, "INSERT INTO play_sources (name, api_url, is_default, sort, status, created_at) VALUES (?, ?, 1, 0, 1, ?)");
            $name = DEFAULT_SOURCE_NAME; $api = DEFAULT_SOURCE_API;
            mysqli_stmt_bind_param($stmt, 'sss', $name, $api, $now);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
            $steps[] = '写入默认播放源（' . DEFAULT_SOURCE_NAME . '）✓';
        }

        /* 默认设置 */
        $defaults = array(
            'site_name'   => APP_NAME,
            'theme_color' => '#e50914',
            'tmdb_key'    => TMDB_API_KEY,
            'ann_version' => date('YmdHis'),
        );
        foreach ($defaults as $k => $v) {
            $stmt = mysqli_prepare($conn, "REPLACE INTO settings (skey, svalue) VALUES (?, ?)");
            mysqli_stmt_bind_param($stmt, 'ss', $k, $v);
            mysqli_stmt_execute($stmt); mysqli_stmt_close($stmt);
        }
        $steps[] = '写入默认站点设置 ✓';

        /* 上传目录 */
        foreach (array('/uploads', '/uploads/avatars', '/data') as $dir) {
            $p = __DIR__ . $dir;
            if (!is_dir($p)) { @mkdir($p, 0755, true); }
        }
        @file_put_contents(__DIR__ . '/uploads/avatars/index.html', '');
        @file_put_contents(__DIR__ . '/uploads/index.html', '');
        $steps[] = '创建上传目录 ✓';

        /* 安装锁 */
        @mkdir(__DIR__ . '/data', 0755, true);
        @file_put_contents($installedLock, 'installed at ' . $now);
        $steps[] = '生成安装锁 ✓';
        $done = true;
        mysqli_close($conn);
    } catch (Exception $ex) {
        $error = $ex->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>安装向导 - <?php echo h(APP_NAME); ?></title>
<style>
    body { font-family: "PingFang SC","Microsoft YaHei",sans-serif; background:#0a0d14; color:#e6eaf2; margin:0; min-height:100vh; display:flex; align-items:center; justify-content:center; padding:30px 16px; }
    .card { width:100%; max-width:640px; background:#151a26; border:1px solid rgba(148,163,184,.15); border-radius:20px; padding:38px 36px; box-shadow:0 20px 50px rgba(0,0,0,.5); }
    h1 { font-size:24px; margin:0 0 6px; }
    .sub { color:#8a93a8; font-size:13.5px; margin-bottom:26px; }
    .ok-list { list-style:none; padding:0; margin:18px 0; }
    .ok-list li { padding:7px 0; color:#86efac; font-size:14px; border-bottom:1px dashed rgba(148,163,184,.12); }
    .err { background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.4); color:#fca5a5; padding:14px 16px; border-radius:12px; font-size:14px; margin-bottom:18px; }
    .env { width:100%; border-collapse:collapse; font-size:13.5px; margin-bottom:22px; }
    .env td { padding:9px 12px; border-bottom:1px solid rgba(148,163,184,.1); }
    .env td:first-child { color:#8a93a8; }
    .pass { color:#86efac; } .fail { color:#fca5a5; }
    button { width:100%; padding:14px; border:0; border-radius:12px; background:linear-gradient(135deg,#e50914,#b00610); color:#fff; font-size:15px; font-weight:700; cursor:pointer; transition:transform .2s, box-shadow .2s; }
    button:hover { transform:translateY(-2px); box-shadow:0 10px 26px rgba(229,9,20,.4); }
    .links a { display:inline-block; margin:16px 10px 0 0; color:#ff2b36; font-size:14px; font-weight:600; }
    .note { margin-top:22px; font-size:12.5px; color:#8a93a8; line-height:22px; background:rgba(148,163,184,.06); border-radius:10px; padding:12px 14px; }
</style>
</head>
<body>
<div class="card">
    <h1><?php echo h(APP_NAME); ?> · 安装向导</h1>
    <p class="sub">PHP <?php echo h(PHP_VERSION); ?> · MySQL 数据库 · TMDB 元数据 · 适配 InfinityFree</p>

    <?php if ($error): ?>
        <div class="err"><?php echo h($error); ?></div>
        <?php if (strpos($error, '已安装') !== false): ?>
            <div class="links"><a href="index.php">返回首页</a><a href="admin/login.php">进入后台</a></div>
        <?php endif; ?>
    <?php elseif ($done): ?>
        <ul class="ok-list">
            <?php foreach ($steps as $s) echo '<li>' . h($s) . '</li>'; ?>
        </ul>
        <div class="note">
            <b>安装完成！</b><br>
            前台：<a style="color:#ff2b36" href="index.php">index.php</a> ｜
            后台：<a style="color:#ff2b36" href="admin/login.php">admin/login.php</a><br>
            默认管理员：<b>杰同学</b>　密码：<b>101113</b><br>
            为了安全，安装完成后请<b style="color:#fca5a5">立即删除 install.php</b>。
        </div>
    <?php else: ?>
        <table class="env">
            <tr><td>PHP 版本（需 ≥ 7.4）</td><td class="<?php echo version_compare(PHP_VERSION, '7.4.0', '>=') ? 'pass' : 'fail'; ?>"><?php echo h(PHP_VERSION); ?></td></tr>
            <tr><td>mysqli 扩展</td><td class="<?php echo class_exists('mysqli') ? 'pass' : 'fail'; ?>"><?php echo class_exists('mysqli') ? '已启用' : '未启用'; ?></td></tr>
            <tr><td>curl / allow_url_fopen</td><td class="<?php echo (function_exists('curl_init') || ini_get('allow_url_fopen')) ? 'pass' : 'fail'; ?>"><?php echo function_exists('curl_init') ? 'curl 已启用' : (ini_get('allow_url_fopen') ? 'fopen 可用' : '不可用'); ?></td></tr>
            <tr><td>openssl（SMTP SSL）</td><td class="<?php echo extension_loaded('openssl') ? 'pass' : 'fail'; ?>"><?php echo extension_loaded('openssl') ? '已启用' : '未启用'; ?></td></tr>
            <tr><td>数据库主机</td><td><?php echo h(DB_HOST); ?> / <?php echo h(DB_NAME); ?></td></tr>
        </table>
        <form method="post">
            <button type="submit">开始安装（自动建表 + 初始化数据）</button>
        </form>
        <div class="note">
            安装将创建 12 张业务数据表，写入默认管理员（杰同学 / 101113）、默认播放源与站点设置。<br>
            若连接失败请检查 config.php 中的数据库配置。
        </div>
    <?php endif; ?>
</div>
</body>
</html>
