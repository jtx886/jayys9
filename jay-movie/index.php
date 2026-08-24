<?php
/**
 * Jay影视 - 首页（TMDB 数据 + 公告弹窗仅在此页显示）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

/* 公告弹窗仅首页展示（Cookie 版本控制） */
$announcement = get_active_announcement();

/* TMDB 数据 */
$trending = tmdb_trending('all', 'day');
$heroItems = array();
if (!empty($trending['results'])) {
    foreach ($trending['results'] as $it) {
        if (!empty($it['backdrop_path']) && !empty($it['poster_path'])) $heroItems[] = $it;
        if (count($heroItems) >= 7) break;
    }
}
$hero = isset($heroItems[0]) ? $heroItems[0] : null;
$heroItemsRest = array_slice($heroItems, 1, 6);

$popularMovies = tmdb_popular('movie');
$popularTv = tmdb_popular('tv');
$variety = tmdb_discover('tv', array('with_genres' => '10764,10767', 'sort_by' => 'popularity.desc'));
$anime = tmdb_discover('tv', array('with_genres' => '16', 'sort_by' => 'popularity.desc'));
$topRated = tmdb_top_rated('movie');

render_header(array('title' => '首页', 'active' => 'home'));
?>
<div class="container">

    <?php if ($hero): ?>
    <section class="hero fade-up">
        <div class="hero-img" style="background-image:url('<?php echo e(tmdb_img($hero['backdrop_path'], 'w1280')); ?>')"></div>
        <div class="hero-body">
            <span class="hero-tag">今日热门 · <?php echo e(tmdb_media_type($hero) === 'movie' ? '电影' : '剧集'); ?></span>
            <h1 class="hero-title"><?php echo e(tmdb_title($hero)); ?></h1>
            <div class="hero-meta">
                <?php $r = tmdb_rating($hero); if ($r > 0): ?>
                <span class="rate"><span class="ic ic-star"></span><?php echo $r; ?></span>
                <?php endif; ?>
                <?php $y = tmdb_year($hero); if ($y): ?><span><?php echo e($y); ?></span><?php endif; ?>
                <span><?php echo e(tmdb_media_type($hero) === 'movie' ? '电影' : '剧集'); ?></span>
                <?php if (!empty($hero['original_language'])): ?><span><?php echo e(strtoupper($hero['original_language'])); ?></span><?php endif; ?>
            </div>
            <p class="hero-desc"><?php echo e($hero['overview'] !== '' ? $hero['overview'] : '暂无简介'); ?></p>
            <div class="hero-actions">
                <a class="btn btn-accent btn-lg" href="detail.php?id=<?php echo (int)$hero['id']; ?>&type=<?php echo e(tmdb_media_type($hero)); ?>">
                    <span class="ic ic-play"></span>立即播放
                </a>
                <a class="btn btn-ghost btn-lg" href="detail.php?id=<?php echo (int)$hero['id']; ?>&type=<?php echo e(tmdb_media_type($hero)); ?>">
                    <span class="ic ic-plus"></span>查看详情
                </a>
            </div>
        </div>
    </section>
    <?php else: ?>
    <?php echo empty_state('TMDB 数据加载失败，请稍后刷新重试（可在后台检查 API Key 配置）'); ?>
    <?php endif; ?>

    <?php if ($heroItemsRest): ?>
    <section class="media-row fade-up" style="margin-top:34px">
        <div class="row-head"><h2>正在热映 trending</h2></div>
        <div class="row-scroll">
            <?php foreach ($heroItemsRest as $it) echo media_card($it); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php echo media_row('热门电影', isset($popularMovies['results']) ? array_slice($popularMovies['results'], 0, 12) : array(), 'category.php?t=movie'); ?>
    <?php echo media_row('热播剧集', isset($popularTv['results']) ? array_slice($popularTv['results'], 0, 12) : array(), 'category.php?t=tv'); ?>
    <?php echo media_row('热门综艺', isset($variety['results']) ? array_slice($variety['results'], 0, 12) : array(), 'category.php?t=variety'); ?>
    <?php echo media_row('人气动漫', isset($anime['results']) ? array_slice($anime['results'], 0, 12) : array(), 'category.php?t=anime'); ?>
    <?php echo media_row('高分佳作', isset($topRated['results']) ? array_slice($topRated['results'], 0, 12) : array(), 'category.php?t=movie&sort=top'); ?>

</div>
<?php
/* 公告弹窗（仅首页） */
render_announcement_popup();
render_footer();
