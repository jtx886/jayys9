<?php
/**
 * Jay影视 - 站点设置（MySQL settings 表，KV 模式）
 * 支持后台自定义全站主题颜色
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

function get_setting($key, $default = null)
{
    static $cache = null;
    if ($cache === null) {
        $cache = array();
        $rows = DB::all("SELECT skey, svalue FROM settings");
        foreach ($rows as $r) { $cache[$r['skey']] = $r['svalue']; }
    }
    return array_key_exists($key, $cache) && $cache[$key] !== '' ? $cache[$key] : $default;
}

function set_setting($key, $value)
{
    DB::exec("REPLACE INTO settings (skey, svalue) VALUES (?, ?)", array($key, (string)$value));
}

/** 当前生效主题色（后台可改） */
function theme_color()
{
    $c = strtoupper(trim((string)get_setting('theme_color', '#e50914')));
    if (!preg_match('/^#[0-9A-Fa-f]{6}$/', $c)) $c = '#e50914';
    return $c;
}

/** hex -> rgb */
function hex_rgb($hex)
{
    $hex = ltrim($hex, '#');
    return array(hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
}

/** 调亮/调暗 hex 颜色，$amt -255~255 */
function shade_hex($hex, $amt)
{
    list($r, $g, $b) = hex_rgb($hex);
    $r = max(0, min(255, $r + $amt));
    $g = max(0, min(255, $g + $amt));
    $b = max(0, min(255, $b + $amt));
    return sprintf('#%02x%02x%02x', $r, $g, $b);
}

/** 站点内联样式：注入主题 CSS 变量（全站主题颜色） */
function theme_style_tag()
{
    $c   = theme_color();
    $hi  = shade_hex($c, 40);   // hover 亮色
    $lo  = shade_hex($c, -35);  // active 暗色
    list($r, $g, $b) = hex_rgb($c);
    return '<style>:root{--accent:' . $c . ';--accent-h:' . $hi . ';--accent-l:' . $lo
        . ';--accent-rgb:' . $r . ',' . $g . ',' . $b . ';}</style>';
}

function site_name()
{
    return get_setting('site_name', APP_NAME);
}
