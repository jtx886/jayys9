&lt;?php
$pageTitle = '分类';
require_once __DIR__ . '/includes/header.php';

$type = $_GET['type'] ?? 'movie';
$genre = isset($_GET['genre']) ? intval($_GET['genre']) : 0;
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;

$typeNames = [
    'movie' =&gt; '电影',
    'tv' =&gt; '电视剧',
    'anime' =&gt; '动漫',
    'variety' =&gt; '综艺'
];

$currentTypeName = $typeNames[$type] ?? '电影';

$genres = [
    ['id' =&gt; 0, 'name' =&gt; '全部'],
    ['id' =&gt; 28, 'name' =&gt; '动作'],
    ['id' =&gt; 12, 'name' =&gt; '冒险'],
    ['id' =&gt; 16, 'name' =&gt; '动画'],
    ['id' =&gt; 35, 'name' =&gt; '喜剧'],
    ['id' =&gt; 80, 'name' =&gt; '犯罪'],
    ['id' =&gt; 99, 'name' =&gt; '纪录片'],
    ['id' =&gt; 18, 'name' =&gt; '剧情'],
    ['id' =&gt; 10751, 'name' =&gt; '家庭'],
    ['id' =&gt; 14, 'name' =&gt; '奇幻'],
    ['id' =&gt; 36, 'name' =&gt; '历史'],
    ['id' =&gt; 27, 'name' =&gt; '恐怖'],
    ['id' =&gt; 10402, 'name' =&gt; '音乐'],
    ['id' =&gt; 9648, 'name' =&gt; '悬疑'],
    ['id' =&gt; 10749, 'name' =&gt; '爱情'],
    ['id' =&gt; 878, 'name' =&gt; '科幻'],
    ['id' =&gt; 53, 'name' =&gt; '惊悚'],
    ['id' =&gt; 10752, 'name' =&gt; '战争'],
    ['id' =&gt; 37, 'name' =&gt; '西部']
];

$items = [];
$totalPages = 1;

$apiKey = getSetting('tmdb_api_key', '');
if ($apiKey) {
    $endpoint = $type === 'movie' ? '/discover/movie' : '/discover/tv';
    
    if ($type === 'anime') {
        $endpoint = '/discover/tv';
        $params = [
            'page' =&gt; $page,
            'with_genres' =&gt; '16',
            'sort_by' =&gt; 'popularity.desc',
            'with_original_language' =&gt; 'ja'
        ];
    } elseif ($type === 'variety') {
        $endpoint = '/discover/tv';
        $params = [
            'page' =&gt; $page,
            'with_genres' =&gt; '10764,10767',
            'sort_by' =&gt; 'popularity.desc'
        ];
    } else {
        $params = [
            'page' =&gt; $page,
            'sort_by' =&gt; 'popularity.desc'
        ];
        if ($genre &gt; 0) {
            $params['with_genres'] = $genre;
        }
    }
    
    $data = tmdbRequest($endpoint, $params);
    if ($data &amp;&amp; isset($data['results'])) {
        $items = $data['results'];
        $totalPages = min($data['total_pages'], 20);
    }
}
?&gt;

&lt;div class="container"&gt;
    &lt;div class="category-tabs" style="margin-bottom: 24px;"&gt;
        &lt;?php foreach ($typeNames as $t =&gt; $name): ?&gt;
            &lt;a href="?type=&lt;?php echo $t; ?&gt;" class="category-tab &lt;?php echo $type === $t ? 'active' : ''; ?&gt;"&gt;&lt;?php echo $name; ?&gt;&lt;/a&gt;
        &lt;?php endforeach; ?&gt;
    &lt;/div&gt;
    
    &lt;?php if ($type === 'movie' || $type === 'tv'): ?&gt;
    &lt;div class="category-tabs" style="margin-bottom: 30px;"&gt;
        &lt;?php foreach ($genres as $g): ?&gt;
            &lt;a href="?type=&lt;?php echo $type; ?&gt;&amp;genre=&lt;?php echo $g['id']; ?&gt;" class="category-tab &lt;?php echo $genre === $g['id'] ? 'active' : ''; ?&gt;"&gt;&lt;?php echo $g['name']; ?&gt;&lt;/a&gt;
        &lt;?php endforeach; ?&gt;
    &lt;/div&gt;
    &lt;?php endif; ?&gt;

    &lt;h2 class="section-title" style="margin-bottom: 24px;"&gt;&lt;?php echo $currentTypeName; ?&gt;&lt;/h2&gt;

    &lt;?php if (!empty($items)): ?&gt;
    &lt;div class="media-grid" style="margin-bottom: 40px;"&gt;
        &lt;?php foreach ($items as $item): 
            $isMovie = $type === 'movie';
            $title = $isMovie ? ($item['title'] ?? '') : ($item['name'] ?? '');
            $year = $isMovie ? substr($item['release_date'] ?? '', 0, 4) : substr($item['first_air_date'] ?? '', 0, 4);
            $poster = !empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path'] : '';
            $rating = number_format($item['vote_average'] ?? 0, 1);
            $mediaType = ($type === 'movie') ? 'movie' : 'tv';
        ?&gt;
        &lt;div class="card media-card" onclick="window.location.href='/detail.php?id=&lt;?php echo $item['id']; ?&gt;&amp;type=&lt;?php echo $mediaType; ?&gt;'"&gt;
            &lt;div class="media-poster"&gt;
                &lt;?php if ($poster): ?&gt;
                    &lt;img src="&lt;?php echo $poster; ?&gt;" alt="&lt;?php echo htmlspecialchars($title); ?&gt;" loading="lazy"&gt;
                &lt;?php else: ?&gt;
                    &lt;div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--bg-card);color:var(--text-muted);font-size:14px;"&gt;暂无图片&lt;/div&gt;
                &lt;?php endif; ?&gt;
                &lt;span class="media-badge rating-badge"&gt;★ &lt;?php echo $rating; ?&gt;&lt;/span&gt;
                &lt;div class="play-btn-overlay"&gt;&lt;/div&gt;
            &lt;/div&gt;
            &lt;div class="media-info"&gt;
                &lt;div class="media-title"&gt;&lt;?php echo htmlspecialchars($title); ?&gt;&lt;/div&gt;
                &lt;div class="media-meta"&gt;&lt;?php echo $year ?: '未知'; ?&gt;&lt;/div&gt;
            &lt;/div&gt;
        &lt;/div&gt;
        &lt;?php endforeach; ?&gt;
    &lt;/div&gt;

    &lt;?php if ($totalPages &gt; 1): ?&gt;
    &lt;div style="display:flex;justify-content:center;gap:8px;margin-top:30px;"&gt;
        &lt;?php if ($page &gt; 1): ?&gt;
            &lt;a href="?type=&lt;?php echo $type; ?&gt;&amp;genre=&lt;?php echo $genre; ?&gt;&amp;page=&lt;?php echo $page-1; ?&gt;" class="btn btn-secondary" style="padding:10px 20px;"&gt;上一页&lt;/a&gt;
        &lt;?php endif; ?&gt;
        &lt;span style="padding:10px 20px;color:var(--text-secondary);"&gt;&lt;?php echo $page; ?&gt; / &lt;?php echo $totalPages; ?&gt;&lt;/span&gt;
        &lt;?php if ($page &lt; $totalPages): ?&gt;
            &lt;a href="?type=&lt;?php echo $type; ?&gt;&amp;genre=&lt;?php echo $genre; ?&gt;&amp;page=&lt;?php echo $page+1; ?&gt;" class="btn btn-primary" style="padding:10px 20px;"&gt;下一页&lt;/a&gt;
        &lt;?php endif; ?&gt;
    &lt;/div&gt;
    &lt;?php endif; ?&gt;
    &lt;?php else: ?&gt;
    &lt;div style="text-align:center;padding:60px 20px;color:var(--text-muted);"&gt;
        &lt;div style="font-size:50px;margin-bottom:16px;"&gt;📭&lt;/div&gt;
        &lt;p&gt;暂无内容，请配置TMDB API密钥&lt;/p&gt;
    &lt;/div&gt;
    &lt;?php endif; ?&gt;
&lt;/div&gt;

&lt;?php require_once __DIR__ . '/includes/footer.php'; ?&gt;
