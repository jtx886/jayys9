<?php
/**
 * Jay影视 - 搜索页（TMDB multi 搜索）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$q = get_param('q');
$page = max(1, (int)get_param('p', 1));

$items = array(); $totalPages = 1; $totalResults = 0;
if ($q !== '') {
    $data = tmdb_search($q, $page);
    if (isset($data['results'])) {
        foreach ($data['results'] as $it) {
            if ((int)$it['id'] <= 0) continue;
            if (!in_array(tmdb_media_type($it), array('movie', 'tv'), true)) continue; // 过滤人物
            $items[] = $it;
        }
        $totalPages = min(500, (int)$data['total_pages']);
        $totalResults = (int)$data['total_results'];
    }
}

render_header(array('title' => $q !== '' ? '搜索：' . $q : '搜索', 'active' => ''));
?>
<div class="container">
    <div class="page-head fade-up">
        <h1><?php echo $q !== '' ? '“' . e($q) . '” 的搜索结果' : '影视搜索'; ?></h1>
        <p><?php echo $q !== '' ? '共找到 ' . number_format($totalResults) . ' 条相关结果' : '使用顶部搜索框搜索电影、剧集、综艺、动漫'; ?></p>
    </div>

    <?php if ($q !== '' && $items): ?>
    <div class="grid-media fade-up">
        <?php foreach ($items as $it) echo media_card($it); ?>
    </div>
    <?php echo render_pagination('search.php?q=' . urlencode($q), $page, $totalPages); ?>
    <?php elseif ($q !== ''): ?>
    <?php echo empty_state('未找到与“' . $q . '”相关的影视内容'); ?>
    <?php else: ?>
    <?php
    $trending = tmdb_trending('all', 'week');
    echo media_row('本周热门推荐', isset($trending['results']) ? array_slice($trending['results'], 0, 14) : array());
    ?>
    <?php endif; ?>
</div>
<?php render_footer(); ?>
