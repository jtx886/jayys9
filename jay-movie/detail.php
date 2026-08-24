<?php
/**
 * Jay影视 - 影视详情页
 * - TMDB 元数据（简介/评分/年份/演员/单集封面）
 * - 剧集/动漫支持切换季（自动拉取对应季完整数据）
 * - 海外影视：普通话/原版 音轨切换；国产影视：自动匹配配音（隐藏切换）
 * - 播放资源来自播放源接口匹配
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$id   = (int)get_param('id', 0);
$type = get_param('type', 'tv');
if (!in_array($type, array('movie', 'tv'), true)) $type = 'tv';
$audio = get_param('audio', '');
if (!in_array($audio, array('', 'guoyu', 'yuanban'), true)) $audio = '';

if ($id <= 0) { echo empty_state('参数错误'); render_footer(); exit; }

$detail = tmdb_detail($type, $id, 'credits');
if (!$detail || isset($detail['status_code'])) {
    render_header(array('title' => '未找到'));
    echo empty_state('未找到该影视信息，可能已被 TMDB 下架');
    render_footer();
    exit;
}

$title = tmdb_title($detail);
$isOverseas = strtolower((string)(isset($detail['original_language']) ? $detail['original_language'] : '')) !== 'zh';

/* ------- 季度处理（剧集/动漫） ------- */
$seasons = array();
$seasonNo = 1;
$seasonData = null;
if ($type === 'tv' && !empty($detail['seasons'])) {
    foreach ($detail['seasons'] as $s) {
        if ((int)$s['season_number'] === 0) continue; // 跳过特别篇
        $seasons[] = $s;
    }
    if (!$seasons && !empty($detail['seasons'])) $seasons = $detail['seasons'];
    $seasonNo = (int)get_param('season', 0);
    if ($seasonNo <= 0) $seasonNo = (int)$seasons[0]['season_number'];
    $valid = false;
    foreach ($seasons as $s) { if ((int)$s['season_number'] === $seasonNo) { $valid = true; break; } }
    if (!$valid) $seasonNo = (int)$seasons[0]['season_number'];
    $seasonData = tmdb_season($id, $seasonNo, 'credits');
}

/* ------- 播放源匹配（主标题未命中时回退 TMDB 别名） ------- */
$matchTitle = $title;
$vod = source_find_vod($matchTitle, $type === 'tv' ? $seasonNo : 1, $audio);
if (!$vod) {
    $vod = source_find_vod($matchTitle, $type === 'tv' ? $seasonNo : 1, $audio, tmdb_alt_titles($type, $id));
}
$groups = $vod ? parse_play_groups($vod) : array();
$group = $groups ? pick_group($groups, $audio) : null;
$srcEps = $group ? $group['eps'] : array();

/* 演员信息：季度演员优先，回退全剧 */
$cast = array();
if ($seasonData && !empty($seasonData['credits']['cast'])) {
    $cast = array_slice($seasonData['credits']['cast'], 0, 14);
} elseif (!empty($detail['credits']['cast'])) {
    $cast = array_slice($detail['credits']['cast'], 0, 14);
}

$tmdbEps = ($type === 'tv' && $seasonData && !empty($seasonData['episodes'])) ? $seasonData['episodes'] : array();

/* 收藏状态 */
$favorited = false; $favCount = 0;
if (is_logged_in()) {
    $u = current_user();
    $favorited = (bool)DB::one("SELECT id FROM favorites WHERE user_id = ? AND tmdb_id = ? AND media_type = ?", array((int)$u['id'], $id, $type));
}
$favCount = (int)DB::one("SELECT COUNT(*) FROM favorites WHERE tmdb_id = ? AND media_type = ?", array($id, $type));

$playBase = 'play.php?id=' . $id . '&type=' . $type . ($type === 'tv' ? '&season=' . $seasonNo : '') . ($audio !== '' ? '&audio=' . $audio : '');
$poster = tmdb_img($detail['poster_path'], 'w500');
$firstPlayUrl = $playBase . '&ep=0';

render_header(array('title' => $title, 'active' => $type === 'movie' ? 'movie' : 'tv'));
?>
<div class="container">

    <section class="detail-hero fade-up">
        <div class="detail-backdrop" style="background-image:url('<?php echo e(tmdb_img($detail['backdrop_path'], 'w1280')); ?>')"></div>
        <div class="detail-wrap">
            <div class="detail-poster">
                <?php if ($poster): ?>
                <img src="<?php echo e($poster); ?>" alt="<?php echo e($title); ?>">
                <?php else: ?>
                <div class="poster-empty" style="width:100%;height:100%"><span class="ic ic-film"></span></div>
                <?php endif; ?>
            </div>
            <div class="detail-info">
                <h1 class="detail-title"><?php echo e($title); ?>
                    <?php if (!empty($detail['original_name']) || !empty($detail['original_title'])): ?>
                    <span class="orig"><?php echo e(isset($detail['original_name']) ? $detail['original_name'] : $detail['original_title']); ?></span>
                    <?php endif; ?>
                </h1>
                <div class="detail-meta">
                    <?php $rate = tmdb_rating($detail); if ($rate > 0): ?>
                    <span class="rate"><span class="ic ic-star"></span><?php echo $rate; ?></span>
                    <?php endif; ?>
                    <?php $y = tmdb_year($detail); if ($y): ?><span><?php echo e($y); ?></span><?php endif; ?>
                    <?php if ($type === 'movie' && !empty($detail['runtime'])): ?><span><?php echo (int)$detail['runtime']; ?> 分钟</span><?php endif; ?>
                    <?php if ($type === 'tv'): ?>
                        <span>共 <?php echo count($seasons); ?> 季</span>
                    <?php endif; ?>
                    <?php if (!empty($detail['original_language'])): ?><span><?php echo e(strtoupper($detail['original_language'])); ?></span><?php endif; ?>
                </div>
                <div class="detail-genres">
                    <?php foreach ((isset($detail['genres']) ? $detail['genres'] : array()) as $g): ?>
                    <span class="genre-tag"><?php echo e($g['name']); ?></span>
                    <?php endforeach; ?>
                </div>
                <p class="detail-overview"><?php echo e(!empty($detail['overview']) ? $detail['overview'] : '暂无剧情简介。'); ?></p>

                <div class="detail-actions">
                    <?php if ($srcEps): ?>
                    <a class="btn btn-accent btn-lg" href="<?php echo e($firstPlayUrl); ?>">
                        <span class="ic ic-play"></span>
                        <?php echo $type === 'movie' ? '立即播放' : '播放 第1集'; ?>
                    </a>
                    <?php else: ?>
                    <button class="btn btn-ghost btn-lg" disabled><span class="ic ic-ban"></span>暂无播放资源</button>
                    <?php endif; ?>
                    <button class="btn btn-ghost btn-lg btn-fav<?php echo $favorited ? ' on' : ''; ?>"
                        data-fav="api/favorite.php"
                        data-tmdb="<?php echo $id; ?>"
                        data-type="<?php echo e($type); ?>"
                        data-title="<?php echo e($title); ?>"
                        data-poster="<?php echo e($poster); ?>">
                        <span class="ic ic-heart<?php echo $favorited ? ' on' : ''; ?>"></span>
                        <?php echo $favorited ? '已收藏' : '收藏'; ?>
                        <span class="fav-count"><?php echo $favCount; ?></span>
                    </button>
                </div>
            </div>
        </div>
    </section>

    <?php /* ---------- 音轨切换（仅海外影视） ---------- */
    if ($isOverseas && $srcEps): ?>
    <div style="margin:22px 0 0" class="fade-up">
        <div class="season-bar">
            <span class="label"><span class="ic ic-mic"></span>音轨选择（海外影视）</span>
            <div class="audio-switch">
                <span class="label">配音</span>
                <a class="audio-opt<?php echo $audio === 'guoyu' ? ' active' : ''; ?>" href="?id=<?php echo $id; ?>&type=<?php echo $type; ?><?php echo $type === 'tv' ? '&season=' . $seasonNo : ''; ?>&audio=guoyu">普通话</a>
                <a class="audio-opt<?php echo $audio !== 'guoyu' ? ' active' : ''; ?>" href="?id=<?php echo $id; ?>&type=<?php echo $type; ?><?php echo $type === 'tv' ? '&season=' . $seasonNo : ''; ?>&audio=yuanban">原版</a>
            </div>
            <?php if ($audio === 'guoyu'): ?>
            <span style="font-size:12.5px;color:var(--sub)">已优先匹配普通话配音资源</span>
            <?php else: ?>
            <span style="font-size:12.5px;color:var(--sub)">原版声道，中文字幕</span>
            <?php endif; ?>
        </div>
    </div>
    <?php elseif (!$isOverseas && $srcEps): ?>
    <input type="hidden" id="isDomestic" value="1">
    <?php endif; ?>

    <?php /* ---------- 季选择 + 剧集列表（剧集/动漫） ---------- */
    if ($type === 'tv'): ?>
    <section style="margin-top:26px" class="fade-up">
        <div class="season-bar">
            <span class="label"><span class="ic ic-tv"></span>剧集列表</span>
            <div class="season-tabs">
                <?php foreach ($seasons as $s): $sn = (int)$s['season_number']; ?>
                <a class="season-tab<?php echo $sn === $seasonNo ? ' active' : ''; ?>"
                   href="?id=<?php echo $id; ?>&type=tv&season=<?php echo $sn; ?><?php echo $audio !== '' ? '&audio=' . $audio : ''; ?>">
                   第<?php echo $sn; ?>季
                </a>
                <?php endforeach; ?>
            </div>
        </div>

        <?php if ($seasonData): ?>
        <div class="season-info">
            <span>本季名称：<b><?php echo e(!empty($seasonData['name']) ? $seasonData['name'] : '第' . $seasonNo . '季'); ?></b></span>
            <?php $sr = isset($seasonData['vote_average']) ? round((float)$seasonData['vote_average'], 1) : 0; if ($sr > 0): ?>
            <span>评分：<span class="rate"><span class="ic ic-star"></span><?php echo $sr; ?></span></span>
            <?php endif; ?>
            <?php $sy = !empty($seasonData['air_date']) ? substr($seasonData['air_date'], 0, 4) : ''; if ($sy): ?>
            <span>年份：<b><?php echo e($sy); ?></b></span>
            <?php endif; ?>
            <span>集数：<b><?php echo e(isset($seasonData['episodes']) ? count($seasonData['episodes']) : count($srcEps)); ?></b></span>
            <?php if (!empty($seasonData['overview'])): ?>
            <span class="season-overview-text"><?php echo e(mb_cut($seasonData['overview'], 150)); ?></span>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($srcEps): ?>
        <div class="ep-grid">
            <?php foreach ($srcEps as $i => $ep):
                $te = isset($tmdbEps[$i]) ? $tmdbEps[$i] : null;
                $still = $te ? tmdb_img($te['still_path'], 'w300') : '';
                $epName = $ep['name'];
                $teName = $te && !empty($te['name']) && $te['name'] !== "第{$i}集" ? $te['name'] : '';
            ?>
            <a class="ep-card" href="<?php echo e($playBase . '&ep=' . $i); ?>">
                <div class="ep-still">
                    <?php if ($still): ?>
                    <img loading="lazy" src="<?php echo e($still); ?>" alt="<?php echo e($epName); ?>">
                    <?php else: ?>
                    <div class="no-still"><span class="ic ic-film"></span><span><?php echo e($epName); ?></span></div>
                    <?php endif; ?>
                    <span class="ep-num"><?php echo e($epName); ?></span>
                    <span class="ep-play-badge"><span class="ic ic-play"></span></span>
                </div>
                <div class="ep-body">
                    <div class="ep-name" title="<?php echo e($teName !== '' ? $teName : $epName); ?>">
                        <?php echo e($teName !== '' ? $teName : $epName); ?>
                    </div>
                    <div class="ep-sub">
                        <span><?php echo e($te && !empty($te['air_date']) ? $te['air_date'] : ''); ?></span>
                        <?php $er = $te ? round((float)$te['vote_average'], 1) : 0; if ($er > 0): ?>
                        <span class="rate"><span class="ic ic-star"></span><?php echo $er; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <?php echo empty_state('当前季暂未匹配到播放资源，可尝试其他季或稍后再试'); ?>
        <?php endif; ?>
    </section>
    <?php endif; ?>

    <?php /* ---------- 演员 ---------- */
    if ($cast): ?>
    <section style="margin-top:36px" class="fade-up">
        <div class="row-head"><h2><?php echo $type === 'tv' ? '本季演员' : '演职人员'; ?></h2></div>
        <div class="cast-row">
            <?php foreach ($cast as $c): ?>
            <div class="cast-card">
                <?php if (!empty($c['profile_path'])): ?>
                <img class="cast-avatar" loading="lazy" src="<?php echo e(tmdb_img($c['profile_path'], 'w185')); ?>" alt="<?php echo e($c['name']); ?>">
                <?php else: ?>
                <div class="cast-letter"><?php echo e(letter_avatar($c['name'])); ?></div>
                <?php endif; ?>
                <div class="cast-name" title="<?php echo e($c['name']); ?>"><?php echo e($c['name']); ?></div>
                <div class="cast-role" title="<?php echo e(isset($c['character']) ? $c['character'] : ''); ?>"><?php echo e(isset($c['character']) ? $c['character'] : '—'); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endif; ?>

</div>
<?php render_footer(); ?>
