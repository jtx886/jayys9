<?php
session_start();

function isInstalled() {
    return file_exists(__DIR__ . '/config.php');
}

if (!isInstalled() && !strpos($_SERVER['PHP_SELF'], 'install.php')) {
    header('Location: install.php');
    exit;
}

if (isInstalled()) {
    require_once __DIR__ . '/config.php';
    require_once __DIR__ . '/database.php';
    require_once __DIR__ . '/smtp.php';

    try {
        $db = Database::getInstance(DB_HOST, DB_NAME, DB_USER, DB_PASS);
    } catch (Exception $e) {
        if (!strpos($_SERVER['PHP_SELF'], 'install.php')) {
            die('系统错误，请重新安装');
        }
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

function currentUser() {
    global $db;
    if (!isLoggedIn()) return null;
    return $db->fetch("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
}

function redirect($url) {
    header("Location: {$url}");
    exit;
}

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function isBanned($user) {
    if (!$user) return false;
    if ($user['banned_until'] === null) return false;
    return strtotime($user['banned_until']) > time();
}

function formatTime($timestamp) {
    $diff = time() - $timestamp;
    if ($diff < 60) return '刚刚';
    if ($diff < 3600) return floor($diff / 60) . '分钟前';
    if ($diff < 86400) return floor($diff / 3600) . '小时前';
    if ($diff < 2592000) return floor($diff / 86400) . '天前';
    return date('Y-m-d', $timestamp);
}

function getSetting($key, $default = '') {
    global $db;
    $row = $db->fetch("SELECT value FROM settings WHERE `key` = ?", [$key]);
    return $row ? $row['value'] : $default;
}

function setSetting($key, $value) {
    global $db;
    $exists = $db->fetch("SELECT id FROM settings WHERE `key` = ?", [$key]);
    if ($exists) {
        $db->update('settings', ['value' => $value], '`key` = ?', [$key]);
    } else {
        $db->insert('settings', ['key' => $key, 'value' => $value]);
    }
}

function getThemeColor() {
    return getSetting('theme_color', '#8b5cf6');
}

function getDefaultPlaySource() {
    global $db;
    return $db->fetch("SELECT * FROM play_sources WHERE is_default = 1 LIMIT 1");
}

function tmdbRequest($endpoint, $params = []) {
    $apiKey = 'eyJhbGciOiJIUzI1NiJ9.eyJhdWQiOiJkYjE4MWI0ZDYxNTQ4YTRkNWQxMzlkNTRiMjlhNTQyNCIsInN1YiI6IjY2YjBlNWU3NmQ0YTdhMDEyY2MxOWFkYSIsInNjb3BlcyI6WyJhcGlfcmVhZCJdLCJ2ZXJzaW9uIjoxfQ.yH4X0w0fJqYV3b5yR9zP7vX6tK8pL4mN2qQ0sT9uW3c';
    
    $url = 'https://api.themoviedb.org/3/' . $endpoint;
    if (!empty($params)) {
        $url .= '?' . http_build_query($params);
    }
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $apiKey,
        'accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function getCachedMedia($tmdbId, $type) {
    global $db;
    return $db->fetch("SELECT * FROM media_cache WHERE tmdb_id = ? AND media_type = ?", [$tmdbId, $type]);
}

function cacheMedia($tmdbId, $type, $data) {
    global $db;
    $exists = getCachedMedia($tmdbId, $type);
    $cachedData = is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : $data;
    if ($exists) {
        $db->update('media_cache', ['data' => $cachedData, 'updated_at' => time()], 'tmdb_id = ? AND media_type = ?', [$tmdbId, $type]);
    } else {
        $db->insert('media_cache', [
            'tmdb_id' => $tmdbId,
            'media_type' => $type,
            'data' => $cachedData,
            'updated_at' => time()
        ]);
    }
}

function getTmdbImage($path, $size = 'w500') {
    if (!$path) return 'data:image/svg+xml;base64,' . base64_encode('<svg xmlns="http://www.w3.org/2000/svg" width="300" height="450" viewBox="0 0 300 450"><rect fill="#1a1a2e" width="300" height="450"/><text x="150" y="225" fill="#64748b" font-size="16" text-anchor="middle" font-family="sans-serif">暂无图片</text></svg>');
    return "https://image.tmdb.org/t/p/{$size}{$path}";
}

function generateAvatar($username) {
    $colors = ['#8b5cf6', '#ec4899', '#06b6d4', '#f59e0b', '#10b981', '#ef4444'];
    $color = $colors[crc32($username) % count($colors)];
    $letter = mb_substr($username, 0, 1);
    return '<svg width="40" height="40" viewBox="0 0 40 40"><circle cx="20" cy="20" r="20" fill="' . $color . '"/><text x="20" y="26" fill="white" font-size="18" text-anchor="middle" font-weight="bold" font-family="sans-serif">' . htmlspecialchars($letter) . '</text></svg>';
}
