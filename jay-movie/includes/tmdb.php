<?php
/**
 * Jay影视 - TMDB 元数据接口封装（带 MySQL 缓存，多域名容灾）
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

function tmdb_key()
{
    $k = trim((string)get_setting('tmdb_key', ''));
    if ($k !== '') return $k;
    return defined('TMDB_API_KEY') ? TMDB_API_KEY : '';
}

function tmdb_ok() { return tmdb_key() !== ''; }

/**
 * TMDB GET 请求
 * @param string $path 例如 /trending/all/day、/movie/123、/tv/456/season/1
 * @param array  $params 额外参数
 * @param int    $ttl 缓存秒数
 * @return array|null
 */
function tmdb_get($path, $params = array(), $ttl = 1800)
{
    $key = tmdb_key();
    if ($key === '') return null;

    $params['api_key'] = $key;
    if (!isset($params['language'])) $params['language'] = TMDB_LANG;
    ksort($params);
    $qs = http_build_query($params);

    $ck = 'tmdb:' . md5($path . '|' . $qs);
    $cached = cache_get($ck);
    if ($cached !== null) return $cached;

    $hosts = array('api.tmdb.org', 'api.themoviedb.org');
    $data  = null;
    foreach ($hosts as $host) {
        list($body, $err) = http_get("https://{$host}/3{$path}?{$qs}", 12);
        if ($body !== false) {
            $d = json_decode($body, true);
            if (is_array($d) && empty($d['success']) && isset($d['results']) || (is_array($d) && empty($d['status_code']))) {
                $data = $d;
                break;
            }
            if (is_array($d)) { $data = $d; break; }
        }
    }
    if ($data !== null) cache_set($ck, $data, $ttl);
    return $data;
}

/** TMDB 图片地址 */
function tmdb_img($path, $size = 'w500')
{
    if (empty($path)) return '';
    return TMDB_IMG . $size . $path;
}

/** 提取条目标题（zh 优先，回退原名） */
function tmdb_title($item)
{
    if (isset($item['title']) && $item['title'] !== '') return $item['title'];
    if (isset($item['name']) && $item['name'] !== '') return $item['name'];
    if (isset($item['original_title']) && $item['original_title'] !== '') return $item['original_title'];
    if (isset($item['original_name']) && $item['original_name'] !== '') return $item['original_name'];
    return '未知';
}

function tmdb_year($item)
{
    foreach (array('release_date', 'first_air_date', 'air_date') as $f) {
        if (!empty($item[$f]) && strlen($item[$f]) >= 4) return substr($item[$f], 0, 4);
    }
    return '';
}

function tmdb_date($item)
{
    foreach (array('release_date', 'first_air_date', 'air_date') as $f) {
        if (!empty($item[$f])) return $item[$f];
    }
    return '';
}

function tmdb_rating($item)
{
    return isset($item['vote_average']) ? round((float)$item['vote_average'], 1) : 0;
}

/** 条目媒体类型：movie / tv */
function tmdb_media_type($item)
{
    if (isset($item['media_type']) && in_array($item['media_type'], array('movie', 'tv'), true)) return $item['media_type'];
    if (isset($item['title']) || isset($item['original_title'])) return 'movie';
    return 'tv';
}

/* ---------- 常用封装 ---------- */

function tmdb_trending($media = 'all', $window = 'day')
{
    return tmdb_get("/trending/{$media}/{$window}", array(), 1800);
}

function tmdb_popular($type, $page = 1)
{
    return tmdb_get("/{$type}/popular", array('page' => max(1, (int)$page)), 1800);
}

function tmdb_top_rated($type, $page = 1)
{
    return tmdb_get("/{$type}/top_rated", array('page' => max(1, (int)$page)), 3600);
}

function tmdb_discover($type, $params = array())
{
    return tmdb_get("/discover/{$type}", $params, 1800);
}

function tmdb_search($query, $page = 1)
{
    return tmdb_get('/search/multi', array('query' => $query, 'page' => max(1, (int)$page), 'include_adult' => 'false'), 3600);
}

function tmdb_detail($type, $id, $append = '')
{
    $p = array();
    if ($append !== '') $p['append_to_response'] = $append;
    return tmdb_get("/{$type}/{$id}", $p, 21600);
}

function tmdb_season($tvId, $seasonNo, $append = '')
{
    $p = array();
    if ($append !== '') $p['append_to_response'] = $append;
    return tmdb_get("/tv/{$tvId}/season/{$seasonNo}", $p, 21600);
}

/**
 * TMDB 别名标题（各地区译名），用于播放源匹配回退
 * @return array 标题字符串列表（去重，最多 8 条）
 */
function tmdb_alt_titles($type, $id)
{
    $path = $type === 'movie' ? "/movie/{$id}/alternative_titles" : "/tv/{$id}/alternative/titles";
    $data = tmdb_get($path, array(), 86400);
    $titles = array();
    if (is_array($data)) {
        foreach (array('titles', 'results') as $key) {
            if (!empty($data[$key]) && is_array($data[$key])) {
                foreach ($data[$key] as $t) {
                    $name = '';
                    if (!empty($t['title'])) $name = (string)$t['title'];
                    elseif (!empty($t['name'])) $name = (string)$t['name'];
                    if ($name !== '') $titles[] = $name;
                }
            }
        }
    }
    $titles = array_values(array_unique($titles));
    return array_slice($titles, 0, 8);
}

/* ---------- 分类配置 ---------- */

function category_config($t)
{
    $map = array(
        'movie'   => array('name' => '电影',   'type' => 'movie', 'genres' => ''),
        'tv'      => array('name' => '电视剧', 'type' => 'tv',    'genres' => ''),
        'variety' => array('name' => '综艺',   'type' => 'tv',    'genres' => '10764,10767'),
        'anime'   => array('name' => '动漫',   'type' => 'tv',    'genres' => '16'),
    );
    return isset($map[$t]) ? $map[$t] : $map['movie'];
}

/** 分类列表数据（TMDB discover / popular） */
function category_fetch($t, $page = 1, $sort = 'popular')
{
    $cfg = category_config($t);
    $type = $cfg['type'];
    $page = max(1, (int)$page);
    $params = array('page' => $page);

    if ($cfg['genres'] !== '') $params['with_genres'] = $cfg['genres'];
    if ($sort === 'top') {
        if ($cfg['genres'] === '') {
            $data = tmdb_top_rated($type, $page);
        } else {
            $params['sort_by'] = 'vote_average.desc';
            $params['vote_count.gte'] = 50;
            $data = tmdb_discover($type, $params);
        }
    } elseif ($sort === 'new') {
        $params['sort_by'] = ($type === 'movie') ? 'primary_release_date.desc' : 'first_air_date.desc';
        $params['vote_count.gte'] = 5;
        $data = tmdb_discover($type, $params);
    } else {
        if ($cfg['genres'] === '') {
            $data = tmdb_popular($type, $page);
        } else {
            $params['sort_by'] = 'popularity.desc';
            $data = tmdb_discover($type, $params);
        }
    }
    return $data;
}
