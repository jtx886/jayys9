<?php
/**
 * Jay影视 - 播放页
 * 逻辑：播放源接口获取真实 m3u8 直链 -> urlencode -> 拼接解析播放器外壳 -> iframe 渲染
 * 未登录禁止播放：直接跳转登录页并弹窗提示
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

/* ---------- 权限：未登录禁止播放 ---------- */
$user = require_login_page('play');
$banned = user_banned($user);

$id   = (int)get_param('id', 0);
$type = get_param('type', 'tv');
if (!in_array($type, array('movie', 'tv'), true)) $type = 'tv';
$season = max(1, (int)get_param('season', 1));
$epIdx  = max(0, (int)get_param('ep', 0));
$audio  = get_param('audio', '');
if (!in_array($audio, array('', 'guoyu', 'yuanban'), true)) $audio = '';

if ($id <= 0) { echo empty_state('参数错误'); render_footer(); exit; }

$detail = tmdb_detail($type, $id);
if (!$detail || isset($detail['status_code'])) { echo empty_state('未找到影视信息'); render_footer(); exit; }
$title = tmdb_title($detail);
$poster = tmdb_img($detail['poster_path'], 'w342');

/* ---------- 解析真实 m3u8 并拼接解析播放器 ---------- */
$resolved = $banned ? null : resolve_episode($title, $type === 'tv' ? $season : 1, $epIdx, $audio);
/* 主标题未命中时回退 TMDB 别名（各地区译名）再匹配一次 */
if (!$resolved && !$banned) {
    $resolved = resolve_episode($title, $type === 'tv' ? $season : 1, $epIdx, $audio, tmdb_alt_titles($type, $id));
}

/* 历史记录起点：优先沿用上次的观看秒数 */
$historyRow = DB::row(
    "SELECT * FROM watch_history WHERE user_id = ? AND tmdb_id = ? AND episode = ?",
    array((int)$user['id'], $id, $resolved ? $resolved['epName'] : ('ep' . $epIdx))
);
$startPos = $historyRow ? (int)$historyRow['position'] : 0;

/* m3u8 总时长（用于进度展示，失败则为0） */
$duration = 0;
if ($resolved) $duration = (int)round(m3u8_duration($resolved['m3u8']));

/* 剧集列表（侧栏选集）：复用已匹配的 vod，未匹配时回退别名再试一次 */
$groups = array(); $group = null;
if (!$banned) {
    $vod = $resolved ? $resolved['vod'] : null;
    if (!$vod) {
        $vod = source_find_vod($title, $type === 'tv' ? $season : 1, $audio);
        if (!$vod) $vod = source_find_vod($title, $type === 'tv' ? $season : 1, $audio, tmdb_alt_titles($type, $id));
    }
    $groups = $vod ? parse_play_groups($vod) : array();
    $group = $groups ? pick_group($groups, $audio) : null;
}
$eps = $group ? $group['eps'] : array();
$total = count($eps);
$curName = $resolved ? $resolved['epName'] : '';

$playBase = 'play.php?id=' . $id . '&type=' . $type . ($type === 'tv' ? '&season=' . $season : '') . ($audio !== '' ? '&audio=' . $audio : '');
$prevUrl = $epIdx > 0 ? $playBase . '&ep=' . ($epIdx - 1) : '';
$nextUrl = ($epIdx + 1) < $total ? $playBase . '&ep=' . ($epIdx + 1) : '';

render_header(array('title' => $title . ' ' . $curName . ' 在线播放'));
?>
<div class="container">

    <?php if ($banned): ?>
    <div class="auth-alert err" style="margin:10px 0 24px;padding:16px 18px;border-radius:12px">
        账号已被封禁，无法播放视频。封禁原因：<?php echo e($user['ban_reason'] ?: '违规操作'); ?>
        （<?php echo e(dt($user['ban_start'])); ?> ~ <?php echo e($user['ban_end'] ? dt($user['ban_end']) : '无限期'); ?>）
    </div>
    <?php endif; ?>

    <div class="play-head fade-up">
        <div class="play-title">
            <a href="detail.php?id=<?php echo $id; ?>&type=<?php echo $type; ?><?php echo $type === 'tv' ? '&season=' . $season : ''; ?>" style="color:var(--text)"><?php echo e($title); ?></a>
            <?php if ($curName !== ''): ?><span class="ep-tag"><?php echo e($curName); ?></span><?php endif; ?>
            <?php if ($audio === 'guoyu'): ?><span class="ep-tag">普通话</span><?php elseif ($audio === 'yuanban'): ?><span class="ep-tag">原版</span><?php endif; ?>
        </div>
        <div class="play-nav">
            <?php if ($prevUrl): ?><a class="btn btn-ghost btn-sm" href="<?php echo e($prevUrl); ?>"><span class="ic ic-arrow-l"></span>上一集</a><?php endif; ?>
            <?php if ($nextUrl): ?><a class="btn btn-ghost btn-sm" href="<?php echo e($nextUrl); ?>">下一集<span class="ic ic-arrow-r"></span></a><?php endif; ?>
        </div>
    </div>

    <div class="play-layout fade-up">
        <div>
            <?php if ($banned): ?>
            <div class="player-box">
                <div class="player-tip" style="flex-direction:column;gap:14px">
                    <span class="ic ic-ban" style="font-size:40px"></span>
                    <span>账号封禁期间无法播放</span>
                </div>
            </div>
            <?php elseif ($resolved): ?>
            <div class="player-box">
                <div class="player-tip"><span class="loading-spin"></span></div>
                <iframe id="playPage"
                    data-tmdb="<?php echo $id; ?>"
                    data-type="<?php echo e($type); ?>"
                    data-title="<?php echo e($title); ?>"
                    data-poster="<?php echo e($poster); ?>"
                    data-episode="<?php echo e($curName); ?>"
                    data-start="<?php echo $startPos; ?>"
                    data-duration="<?php echo $duration; ?>"
                    src="<?php echo e($resolved['player']); ?>"
                    allowfullscreen
                    scrolling="no"
                    sandbox="allow-scripts allow-same-origin allow-presentation allow-forms"></iframe>
            </div>
            <?php else: ?>
            <div class="player-box">
                <div class="player-tip" style="flex-direction:column;gap:14px">
                    <span class="ic ic-film" style="font-size:40px"></span>
                    <span>播放资源匹配失败，请稍后再试或更换播放源</span>
                </div>
            </div>
            <?php endif; ?>

            <div class="play-meta">
                <?php if ($resolved): ?>
                <span><span class="ic ic-film"></span> 线路：<?php echo e($resolved['group']); ?></span>
                <span><span class="ic ic-list"></span> 共 <?php echo $total; ?> 集</span>
                <div class="progress-bar"><i id="playProgressBar" style="width:<?php echo $duration > 0 ? min(100, $startPos / $duration * 100) : 0; ?>%"></i></div>
                <span id="playProgressText" style="font-variant-numeric:tabular-nums"><?php echo fmt_duration($startPos) . ($duration > 0 ? ' / ' . fmt_duration($duration) : ''); ?></span>
                <span style="color:var(--sub)">已观看时长自动同步到观看历史</span>
                <?php endif; ?>
            </div>
        </div>

        <aside class="ep-sidebar fade-up">
            <h3><span class="ic ic-list"></span>选集<?php echo $type === 'tv' ? ' · 第' . $season . '季' : ''; ?></h3>
            <div class="ep-list">
                <?php if ($eps): foreach ($eps as $i => $ep): ?>
                <a class="ep-item<?php echo $i === $epIdx ? ' current' : ''; ?>" href="<?php echo e($playBase . '&ep=' . $i); ?>">
                    <span class="idx"><?php echo $i + 1; ?></span>
                    <span class="name"><?php echo e($ep['name']); ?></span>
                    <span class="ic ic-play"></span>
                </a>
                <?php endforeach; else: ?>
                <div style="padding:20px;color:var(--sub);font-size:13px;text-align:center">暂无剧集数据</div>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<?php render_footer(); ?>
