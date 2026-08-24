<?php
/**
 * Jay影视 - 播放源接口封装（苹果CMS JSON 格式）
 * 流程：TMDB 标题 -> 播放源搜索(ac=detail&wd=) -> 解析 vod_play_url 得到真实 m3u8 直链
 *      -> urlencode 后拼接解析播放器外壳 -> iframe 播放（严禁把接口地址直接传给播放器）
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

/* ---------- 播放源 ---------- */

function get_sources()
{
    return DB::all("SELECT * FROM play_sources WHERE status = 1 ORDER BY is_default DESC, sort ASC, id ASC");
}

function get_default_source()
{
    $row = DB::row("SELECT * FROM play_sources WHERE is_default = 1 AND status = 1 LIMIT 1");
    if ($row) return $row;
    $row = DB::row("SELECT * FROM play_sources WHERE status = 1 ORDER BY sort ASC, id ASC LIMIT 1");
    if ($row) return $row;
    return array('id' => 0, 'name' => DEFAULT_SOURCE_NAME, 'api_url' => DEFAULT_SOURCE_API, 'is_default' => 1);
}

/* ---------- 标题归一化与匹配 ---------- */

function norm_title($s)
{
    $s = preg_replace('/[\s\-–—_·・:：,，.。!！?？\'’"“”()（）\[\]【】《》<>\/\\|~～+]+/u', '', (string)$s);
    $s = preg_replace('/[(\[（【](.*?)[)\]）】]/u', '', $s);
    return mb_strtolower(trim($s), 'UTF-8');
}

/**
 * 搜索播放源（带缓存）
 * @return array vod 列表
 */
function source_search($apiUrl, $keyword)
{
    if ($apiUrl === '' || $keyword === '') return array();
    $ck = 'srcsearch:' . md5($apiUrl . '|' . $keyword);
    $cached = cache_get($ck);
    if ($cached !== null && is_array($cached)) return $cached;

    $url = $apiUrl . (strpos($apiUrl, '?') !== false ? '&' : '?') . 'ac=detail&wd=' . urlencode($keyword);
    list($body, $err) = http_get($url, 15);
    $list = array();
    if ($body !== false) {
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['list']) && is_array($data['list'])) {
            $list = array_values($data['list']);
        }
    }
    /* 仅缓存非空结果：接口瞬时失败时下次请求自动重试，避免空结果被缓存 15 分钟 */
    if ($list) {
        cache_set($ck, $list, 900);
    }
    return $list;
}

/**
 * 构建单个标题的搜索候选词（音轨 / 季度变体）
 * @return array
 */
function build_search_candidates($title, $season, $audio)
{
    $cnNum = array('', '一', '二', '三', '四', '五', '六', '七', '八', '九', '十',
                   '十一', '十二', '十三', '十四', '十五', '十六', '十七', '十八', '十九', '二十');
    $cands = array();
    if ($audio === 'guoyu') {
        $cands[] = $title . ' 国语版';
        $cands[] = $title . ' 国语';
        $cands[] = $title . ' 普通话';
    }
    if ((int)$season > 1) {
        $s = (int)$season;
        $cands[] = $title . ' 第' . $s . '季';
        if ($s <= 20) $cands[] = $title . ' 第' . $cnNum[$s] . '季';
        $cands[] = $title . ' ' . $s;
        $cands[] = $title . $s;
    }
    $cands[] = $title;
    return $cands;
}

/**
 * 依据 TMDB 标题 + 季度 + 音轨偏好，匹配播放源中的最佳资源
 * @param string $title TMDB 中文标题
 * @param int    $season 季（电视剧）
 * @param string $audio '' | guoyu | yuanban
 * @param array  $aliases TMDB 别名（各地区译名，主标题未命中时依次回退）
 * @return array|null vod
 */
function source_find_vod($title, $season = 1, $audio = '', $aliases = array())
{
    $src = get_default_source();
    if (!$src || empty($src['api_url'])) return null;

    /* 候选队列：主标题优先，别名依次回退 */
    $candidateLists = array(build_search_candidates($title, $season, $audio));
    if (is_array($aliases)) {
        foreach ($aliases as $al) {
            $al = trim((string)$al);
            if ($al === '' || $al === $title) continue;
            $candidateLists[] = build_search_candidates($al, $season, $audio);
        }
    }
    if (count($candidateLists) > 7) $candidateLists = array_slice($candidateLists, 0, 7);

    $cnNum = array('', '一', '二', '三', '四', '五', '六', '七', '八', '九', '十',
                   '十一', '十二', '十三', '十四', '十五', '十六', '十七', '十八', '十九', '二十');

    foreach ($candidateLists as $cl) {
        foreach ($cl as $idx => $cand) {
            if (trim($cand) === '') continue;
            $list = source_search($src['api_url'], $cand);
            if (!$list) continue;

            $nc = norm_title($cand);
            $best = null; $bestScore = 0;
            foreach ($list as $vod) {
                if (!is_array($vod) || empty($vod['vod_play_url'])) continue;
                $name = isset($vod['vod_name']) ? (string)$vod['vod_name'] : '';
                if ($name === '') continue;
                $nn = norm_title($name);
                if ($nn === '' ) continue;

                $score = 0;
                if ($nn === $nc) $score = 100;
                elseif (strpos($nn, $nc) === 0) $score = 78;
                elseif (strpos($nn, $nc) !== false) $score = 58;
                elseif (strpos($nc, $nn) === 0 || strpos($nc, $nn) !== false) {
                    $score = (mb_strlen($name, 'UTF-8') >= 2) ? 40 : 0;
                }
                if ($score <= 0) continue;

                // 季度修正：季>1 时，包含季数标记的资源优先，纯基础标题降权
                if ((int)$season > 1 && $idx >= 1) {
                    $seasonHit = (strpos($name, (string)$season) !== false)
                        || preg_match('/第' . ($season <= 20 ? $cnNum[$season] : $season) . '季/u', $name);
                    if ($seasonHit) $score += 20;
                    elseif ($nn === norm_title($title)) $score = min($score, 35);
                }
                // 音轨偏好
                $tagStr = $name . ' ' . (isset($vod['vod_play_from']) ? $vod['vod_play_from'] : '');
                if ($audio === 'guoyu' && preg_match('/国语|普通话|中文配音/u', $tagStr)) $score += 18;
                if ($audio === 'yuanban' && preg_match('/原声|原版|原味|中字/u', $tagStr)) $score += 10;

                // 集数多的资源略优（更完整）
                $epCount = substr_count((string)$vod['vod_play_url'], '$');
                if ($epCount > 0) $score += min(8, (int)($epCount / 12));

                if ($score > $bestScore) { $bestScore = $score; $best = $vod; }
            }
            if ($best && $bestScore >= 40) return $best;
        }
    }
    return null;
}

/* ---------- 剧集解析 ---------- */

/**
 * 解析 vod_play_url 为多线路剧集组
 * @return array [ ['from'=>'线路名','eps'=>[['name'=>'第01集','url'=>'http...m3u8'],...] ], ...]
 */
function parse_play_groups($vod)
{
    $froms = explode('$$$', isset($vod['vod_play_from']) ? (string)$vod['vod_play_from'] : '');
    $urls  = explode('$$$', isset($vod['vod_play_url']) ? (string)$vod['vod_play_url'] : '');
    $groups = array();
    foreach ($froms as $i => $from) {
        $from = trim($from);
        $eps = array();
        $raw = isset($urls[$i]) ? trim($urls[$i]) : '';
        if ($raw !== '') {
            foreach (explode('#', $raw) as $seg) {
                $seg = trim($seg);
                if ($seg === '') continue;
                $p = explode('$', $seg, 2);
                if (count($p) === 2 && trim($p[1]) !== '') {
                    $epName = trim($p[0]) !== '' ? trim($p[0]) : ('第' . (count($eps) + 1) . '集');
                    $eps[] = array('name' => $epName, 'url' => trim($p[1]));
                }
            }
        }
        if ($eps) {
            $groups[] = array('from' => $from !== '' ? $from : ('线路' . (count($groups) + 1)), 'eps' => $eps);
        }
    }
    return $groups;
}

/** 按音轨偏好挑选播放线路 */
function pick_group($groups, $audio)
{
    if (!$groups) return null;
    if ($audio === 'guoyu') {
        foreach ($groups as $g) {
            if (preg_match('/国语|普通话|中文配音|guoyu|mandarin/iu', $g['from'])) return $g;
        }
    } elseif ($audio === 'yuanban') {
        foreach ($groups as $g) {
            if (preg_match('/原声|原版|原味|中字|raw|yuanban/iu', $g['from'])) return $g;
        }
    }
    return $groups[0];
}

/**
 * 解析某集的播放信息（核心逻辑）
 * @return array|null { m3u8, player, epName, vodName, group, total }
 */
function resolve_episode($title, $season, $epIndex, $audio = '', $aliases = array())
{
    $vod = source_find_vod($title, (int)$season, $audio, $aliases);
    if (!$vod) return null;
    $groups = parse_play_groups($vod);
    if (!$groups) return null;
    $group = pick_group($groups, $audio);
    if (!$group || empty($group['eps'])) return null;

    $epIndex = (int)$epIndex;
    if ($epIndex < 0 || $epIndex >= count($group['eps'])) $epIndex = 0;
    $ep = $group['eps'][$epIndex];

    // 严禁把源接口地址传给播放器 —— 此处必须是真实 m3u8 直链
    $m3u8 = $ep['url'];
    if (!preg_match('#^https?://#i', $m3u8)) return null;

    return array(
        'm3u8'    => $m3u8,
        'player'  => PLAYER_SHELL . urlencode($m3u8),
        'epName'  => $ep['name'],
        'epIndex' => $epIndex,
        'vodName' => isset($vod['vod_name']) ? $vod['vod_name'] : $title,
        'group'   => $group['from'],
        'total'   => count($group['eps']),
        'vod'     => $vod,
    );
}

/* ---------- m3u8 时长解析（用于观看进度展示） ---------- */

function resolve_relative_url($base, $rel)
{
    if (preg_match('#^https?://#i', $rel)) return $rel;
    $p = parse_url($base);
    $origin = $p['scheme'] . '://' . $p['host'] . (isset($p['port']) ? ':' . $p['port'] : '');
    $path = isset($p['path']) ? $p['path'] : '/';
    if (strpos($rel, '/') === 0) return $origin . $rel;
    $dir = preg_replace('#/[^/]*$#', '/', $path);
    return $origin . $dir . $rel;
}

function m3u8_duration($url)
{
    $ck = 'm3u8dur:' . md5($url);
    $cached = cache_get($ck);
    if ($cached !== null) return (float)$cached;

    $dur = 0.0;
    list($body,) = http_get($url, 10);
    if ($body !== false) {
        if (strpos($body, '#EXT-X-STREAM-INF') !== false) {
            // master playlist：取第一条子播放列表
            if (preg_match('/^([^\s?#]+\.m3u8[^\s]*)$/im', $body, $m)) {
                $sub = resolve_relative_url($url, trim($m[1]));
                list($b2,) = http_get($sub, 10);
                if ($b2 !== false && preg_match_all('/#EXTINF:([\d.]+)/', $b2, $mm)) {
                    foreach ($mm[1] as $v) $dur += (float)$v;
                }
            }
        } elseif (preg_match_all('/#EXTINF:([\d.]+)/', $body, $mm)) {
            foreach ($mm[1] as $v) $dur += (float)$v;
        }
    }
    cache_set($ck, $dur, 86400);
    return $dur;
}
