&lt;?php
$pageTitle = '搜索';
require_once __DIR__ . '/includes/header.php';

$q = trim($_GET['q'] ?? '');
$results = [];

if ($q) {
    $apiKey = getSetting('tmdb_api_key', '');
    if ($apiKey) {
        $data = tmdbRequest('/search/multi', [
            'query' =&gt; $q,
            'page' =&gt; 1,
            'include_adult' =&gt; false
        ]);
        if ($data &amp;&amp; isset($data['results'])) {
            $results = array_filter($data['results'], function($item) {
                return in_array($item['media_type'], ['movie', 'tv']);
            });
            $results = array_slice($results, 0, 24);
        }
    }
}
?&gt;

&lt;div class="container"&gt;
    &lt;div style="margin-bottom: 30px;"&gt;
        &lt;div style="display:flex;gap:12px;max-width:600px;"&gt;
            &lt;input type="text" id="searchInput" value="&lt;?php echo htmlspecialchars($q); ?&gt;" placeholder="搜索电影、电视剧..." class="form-input" style="flex:1;"&gt;
            &lt;button class="btn btn-primary" onclick="doSearch()"&gt;搜索&lt;/button&gt;
        &lt;/div&gt;
    &lt;/div&gt;

    &lt;?php if ($q): ?&gt;
        &lt;h2 class="section-title"&gt;搜索结果："&lt;?php echo htmlspecialchars($q); ?&gt;"&lt;/h2&gt;
        
        &lt;?php if (!empty($results)): ?&gt;
        &lt;div class="media-grid"&gt;
            &lt;?php foreach ($results as $item): 
                $isMovie = $item['media_type'] === 'movie';
                $title = $isMovie ? ($item['title'] ?? '') : ($item['name'] ?? '');
                $year = $isMovie ? substr($item['release_date'] ?? '', 0, 4) : substr($item['first_air_date'] ?? '', 0, 4);
                $poster = !empty($item['poster_path']) ? 'https://image.tmdb.org/t/p/w500' . $item['poster_path'] : '';
                $rating = number_format($item['vote_average'] ?? 0, 1);
                $typeLabel = $isMovie ? '电影' : '剧集';
            ?&gt;
            &lt;div class="card media-card" onclick="window.location.href='/detail.php?id=&lt;?php echo $item['id']; ?&gt;&amp;type=&lt;?php echo $item['media_type']; ?&gt;'"&gt;
                &lt;div class="media-poster"&gt;
                    &lt;?php if ($poster): ?&gt;
                        &lt;img src="&lt;?php echo $poster; ?&gt;" alt="&lt;?php echo htmlspecialchars($title); ?&gt;" loading="lazy"&gt;
                    &lt;?php else: ?&gt;
                        &lt;div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;background:var(--bg-card);color:var(--text-muted);font-size:14px;"&gt;暂无图片&lt;/div&gt;
                    &lt;?php endif; ?&gt;
                    &lt;span class="media-badge rating-badge"&gt;★ &lt;?php echo $rating; ?&gt;&lt;/span&gt;
                    &lt;span class="media-badge" style="top:10px;right:10px;left:auto;"&gt;&lt;?php echo $typeLabel; ?&gt;&lt;/span&gt;
                    &lt;div class="play-btn-overlay"&gt;&lt;/div&gt;
                &lt;/div&gt;
                &lt;div class="media-info"&gt;
                    &lt;div class="media-title"&gt;&lt;?php echo htmlspecialchars($title); ?&gt;&lt;/div&gt;
                    &lt;div class="media-meta"&gt;&lt;?php echo $year ?: '未知'; ?&gt;&lt;/div&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;?php endforeach; ?&gt;
        &lt;/div&gt;
        &lt;?php else: ?&gt;
        &lt;div style="text-align:center;padding:60px 20px;color:var(--text-muted);"&gt;
            &lt;div style="font-size:50px;margin-bottom:16px;"&gt;🔍&lt;/div&gt;
            &lt;p&gt;未找到相关结果&lt;/p&gt;
        &lt;/div&gt;
        &lt;?php endif; ?&gt;
    &lt;?php else: ?&gt;
    &lt;div style="text-align:center;padding:80px 20px;color:var(--text-muted);"&gt;
        &lt;div style="font-size:60px;margin-bottom:16px;"&gt;🎬&lt;/div&gt;
        &lt;p style="font-size:18px;"&gt;输入关键词搜索影视内容&lt;/p&gt;
    &lt;/div&gt;
    &lt;?php endif; ?&gt;
&lt;/div&gt;

&lt;script&gt;
function doSearch() {
    const q = document.getElementById('searchInput').value.trim();
    if (q) {
        window.location.href = '/search.php?q=' + encodeURIComponent(q);
    }
}

document.getElementById('searchInput').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') doSearch();
});
document.getElementById('searchInput').focus();
&lt;/script&gt;

&lt;?php require_once __DIR__ . '/includes/footer.php'; ?&gt;
