<?php
/**
 * Jay影视 - 分类浏览页（电影 / 电视剧 / 综艺 / 动漫，TMDB 数据）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$t = get_param('t', 'movie');
if (!in_array($t, array('movie', 'tv', 'variety', 'anime'), true)) $t = 'movie';
$page = max(1, (int)get_param('p', 1));
$sort = get_param('sort', 'popular');
if (!in_array($sort, array('popular', 'top', 'new'), true)) $sort = 'popular';

$cfg = category_config($t);
$data = category_fetch($t, $page, $sort);
$items = isset($data['results']) ? $data['results'] : array();
$totalPages = isset($data['total_pages']) ? min(500, (int)$data['total_pages']) : 1;
$totalResults = isset($data['total_results']) ? (int)$data['total_results'] : 0;

$sortNames = array('popular' => '最热门', 'top' => '高分榜', 'new' => '最新');

render_header(array('title' => $cfg['name'], 'active' => $t));
?>
<div class="container">
    <div class="page-head fade-up">
        <h1><?php echo e($cfg['name']); ?></h1>
        <p>共 <?php echo number_format($totalResults); ?> 部作品 · 数据来自 TMDB</p>
    </div>

    <div class="section-tabs fade-up">
        <?php
        $base = 'category.php?t=' . $t;
        foreach ($sortNames as $k => $name):
            $url = $base . '&sort=' . $k;
            if ($page > 1) $url .= '&p=' . $page;
        ?>
        <a class="tab-btn<?php echo $sort === $k ? ' active' : ''; ?>" href="<?php echo e($url); ?>"><?php echo e($name); ?></a>
        <?php endforeach; ?>
    </div>

    <?php if ($items): ?>
    <div class="grid-media fade-up">
        <?php foreach ($items as $it) echo media_card($it); ?>
    </div>
    <?php echo render_pagination($base . '&sort=' . $sort, $page, $totalPages); ?>
    <?php else: ?>
    <?php echo empty_state('暂无数据，请稍后刷新重试'); ?>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
