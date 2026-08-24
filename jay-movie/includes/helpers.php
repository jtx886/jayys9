<?php
/**
 * Jay影视 - 通用函数库（PHP 7.4 - 8.x 兼容）
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

/* ---------- PHP 7.4 兼容 polyfill ---------- */
if (!function_exists('str_contains')) {
    function str_contains($haystack, $needle) { return $needle === '' || strpos($haystack, $needle) !== false; }
}
if (!function_exists('str_starts_with')) {
    function str_starts_with($haystack, $needle) { return strncmp($haystack, $needle, strlen($needle)) === 0; }
}

/* ---------- 输出与请求 ---------- */
function e($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function redirect($url) { header('Location: ' . $url); exit; }

function json_out($data, $code = 200)
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function is_post() { return (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'); }
function post($k, $d = '') { return isset($_POST[$k]) ? trim((string)$_POST[$k]) : $d; }
function get_param($k, $d = '') { return isset($_GET[$k]) ? trim((string)$_GET[$k]) : $d; }
function now_str() { return date('Y-m-d H:i:s'); }
function dt($s) { return $s ? date('Y-m-d H:i', strtotime($s)) : ''; }

/* ---------- HTTP 客户端（curl 优先，兼容 allow_url_fopen） ---------- */
function http_get($url, $timeout = 12)
{
    $body = false; $err = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 4,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_CONNECTTIMEOUT => 6,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => 0,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 JayMovie/' . APP_VERSION,
            CURLOPT_REFERER        => '',
        ));
        $body = curl_exec($ch);
        if ($body === false) { $err = curl_error($ch); }
        else {
            $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if ($http >= 400) { $err = 'HTTP ' . $http; $body = false; }
        }
        curl_close($ch);
    }
    if ($body === false && $err === '' && ini_get('allow_url_fopen')) {
        $ctx  = stream_context_create(array('http' => array('timeout' => $timeout, 'user_agent' => 'JayMovie')));
        $body = @file_get_contents($url, false, $ctx);
        if ($body === false) { $err = 'file_get_contents failed'; }
    }
    return array($body, $err);
}

/* ---------- 媒体缓存（MySQL media_cache 表） ---------- */
function cache_get($key)
{
    $row = DB::row("SELECT content FROM media_cache WHERE cache_key = ? AND expires_at > ?", array($key, now_str()));
    if (!$row) return null;
    $d = json_decode($row['content'], true);
    return $d === null ? $row['content'] : $d;
}

function cache_set($key, $value, $ttlSeconds)
{
    $json = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    DB::exec("REPLACE INTO media_cache (cache_key, content, expires_at) VALUES (?, ?, ?)",
        array($key, $json, date('Y-m-d H:i:s', time() + (int)$ttlSeconds)));
}

function cache_del($key) { DB::exec("DELETE FROM media_cache WHERE cache_key = ?", array($key)); }

/* ---------- 通用工具 ---------- */
function mb_cut($s, $len, $suffix = '...')
{
    $s = (string)$s;
    if (mb_strlen($s, 'UTF-8') <= $len) return $s;
    return mb_substr($s, 0, $len, 'UTF-8') . $suffix;
}

function fmt_duration($seconds)
{
    $seconds = (int)$seconds;
    if ($seconds <= 0) return '00:00';
    $h = floor($seconds / 3600); $m = floor(($seconds % 3600) / 60); $s = $seconds % 60;
    if ($h > 0) return sprintf('%d:%02d:%02d', $h, $m, $s);
    return sprintf('%02d:%02d', $m, $s);
}

function letter_avatar($name)
{
    $n = mb_substr(trim((string)$name), 0, 1, 'UTF-8');
    return $n === '' ? '?' : strtoupper($n);
}

function user_avatar_html($user, $size = 34)
{
    $style = 'width:' . $size . 'px;height:' . $size . 'px;';
    if (!empty($user['avatar']) && file_exists(APP_ROOT . '/' . ltrim($user['avatar'], '/'))) {
        return '<img class="avatar" style="' . $style . '" src="' . e($user['avatar']) . '" alt="">';
    }
    return '<span class="avatar letter-avatar" style="' . $style . '">'
        . e(letter_avatar($user['username'] ?? '?')) . '</span>';
}
