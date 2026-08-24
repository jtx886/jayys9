&lt;?php
$pageTitle = '首页';
require_once __DIR__ . '/includes/header.php';

$apiKey = getSetting('tmdb_api_key', '');
$slides = [];
$trendingMovies = [];
$trendingTv = [];
$popularMovies = [];

if ($apiKey) {
    $trendingAll = tmdbRequest('/trending/all/week');
    if ($trendingAll &amp;&amp; isset($trendingAll['results'])) {
        $slides = array_slice($trendingAll['results'], 0, 5);
    }
    
    $movies = tmdbRequest('/movie/popular');
    if ($movies &amp;&amp; isset($movies['results'])) {
        $popularMovies = array_slice($movies['results'], 0, 12);
    }
    
    $tv = tmdbRequest('/tv/popular');
    if ($tv &amp;&amp; isset($tv['results'])) {
        $trendingTv = array_slice($tv['results'], 0, 12);
    }
}
?&gt;

&lt;div class="container"&gt;
    &lt;?php if (!empty($slides)): ?&gt;
    &lt;div class="hero-slider" id="heroSlider"&gt;
        &lt;?php foreach ($slides as $index =&gt; $item): 
            $isMovie = isset($item['title']);
            $title = $isMovie ? $item['title'] : $item['name'];
            $year = $isMovie ? substr($item['release_date'] ?? '', 0, 4) : substr($item['first_air_date'] ?? '', 0, 4);
            $rating = number_format($item['vote_average'], 1);
            $backdrop = 'https://image.tmdb.org/t/p/original' . ($item['backdrop_path'] ?? '');
            $poster = 'https://image.tmdb.org/t/p/w500' . ($item['poster_path'] ?? '');
            $id = $item['id'];
            $type = $isMovie ? 'movie' : 'tv';
        ?&gt;
        &lt;div class="hero-slide &lt;?php echo $index === 0 ? 'active' : ''; ?&gt;"&gt;
            &lt;div class="hero-bg" style="background-image: url('&lt;?php echo $backdrop; ?&gt;');"&gt;&lt;/div&gt;
            &lt;div class="hero-content"&gt;
                &lt;span class="hero-tag"&gt;&lt;?php echo $isMovie ? '正在热映' : '热播剧集'; ?&gt;&lt;/span&gt;
                &lt;h1 class="hero-title"&gt;&lt;?php echo htmlspecialchars($title); ?&gt;&lt;/h1&gt;
                &lt;div class="hero-meta"&gt;
                    &lt;span style="color:#f59e0b;"&gt;★ &lt;?php echo $rating; ?&gt;&lt;/span&gt;
                    &lt;span&gt;&lt;?php echo $year ?: '未知'; ?&gt;&lt;/span&gt;
                &lt;/div&gt;
                &lt;p class="hero-desc"&gt;&lt;?php echo htmlspecialchars($item['overview'] ?? '暂无简介'); ?&gt;&lt;/p&gt;
                &lt;div class="hero-buttons"&gt;
                    &lt;a href="/play.php?id=&lt;?php echo $id; ?&gt;&amp;type=&lt;?php echo $type; ?&gt;" class="btn btn-primary"&gt;
                        ▶ 立即播放
                    &lt;/a&gt;
                    &lt;a href="/detail.php?id=&lt;?php echo $id; ?&gt;&amp;type=&lt;?php echo $type; ?&gt;" class="btn btn-secondary"&gt;
                        + 详情
                    &lt;/a&gt;
                &lt;/div&gt;
            &lt;/div&gt;
        &lt;/div&gt;
        &lt;?php endforeach; ?&gt;
        
        &lt;div class="slider-dots"&gt;
            &lt;?php foreach ($slides as $index =&gt; $item): ?&gt;
            &lt;div class="slider-dot &lt;?php echo $index === 0 ? 'active' : ''; ?&gt;" data-index="&lt;?php echo $index; ?&gt;"&gt;&lt;/div&gt;
            &lt;?php endforeach; ?&gt;
        &lt;/div&gt;
    &lt;/div&gt;
    &lt;?php else: ?&gt;
    &lt;div style="text-align:center; padding:80px 20px;"&gt;
        &lt;div style="font-size:60px; margin-bottom:20px;"&gt;🎬&lt;/div&gt;
        &lt;h2 style="font-size:24px; margin-bottom:10px;"&gt;欢迎使用Jay影视&lt;/h2&gt;
        &lt;p style="color:var(--text-muted);"&gt;请先在管理后台配置TMDB API密钥&lt;/p&gt;
        &lt;?php if (isAdmin()): ?&gt;
            &lt;a href="/admin/settings.php" class="btn btn-primary" style="margin-top:20px;"&gt;前往配置&lt;/a&gt;
        &lt;?php endif; ?&gt;
    &lt;/div&gt;
    &lt;?php endif; ?&gt;

    &lt;?php if (!empty($popularMovies)): ?&gt;
    &lt;section style="margin-bottom: 40px;"&gt;
        &lt;h2 class="section-title"&gt;🔥 热门电影&lt;/h2&gt;
        &lt;div class="media-grid"&gt;
            &lt;?php foreach ($popularMovies as $movie): 
                $poster = 'https://image.tmdb.org/t/p/w500' . $movie['poster_path'];
                $title = $movie['title'];
                $year = substr($movie['release_date'] ?? '', 0, 4);
                $rating = number_format($movie['vote_average'], 1);
            ?&gt;
            &lt;div class="card media-card" onclick="window.location.href='/detail.php?id=&lt;?php echo $movie['id']; ?&gt;&amp;type=movie'"&gt;
                &lt;div class="media-poster"&gt;
                    &lt;img src="&lt;?php echo $poster; ?&gt;" alt="&lt;?php echo htmlspecialchars($title); ?&gt;" loading="lazy"&gt;
                    &lt;span class="media-badge rating-badge"&gt;★ &lt;?php echo $rating; ?&gt;&lt;/span&gt;
                    &lt;div class="play-btn-overlay"&gt;&lt;/div&gt;
                &lt;/div&gt;
                &lt;div class="media-info"&gt;
                    &lt;div class="media-title"&gt;&lt;?php echo htmlspecialchars($title); ?&gt;&lt;/div&gt;
                    &lt;div class="media-meta"&gt;&lt;?php echo $year ?: '未知'; ?&gt; · 电影&lt;/div&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;?php endforeach; ?&gt;
        &lt;/div&gt;
    &lt;/section&gt;
    &lt;?php endif; ?&gt;

    &lt;?php if (!empty($trendingTv)): ?&gt;
    &lt;section style="margin-bottom: 40px;"&gt;
        &lt;h2 class="section-title"&gt;📺 热门剧集&lt;/h2&gt;
        &lt;div class="media-grid"&gt;
            &lt;?php foreach ($trendingTv as $show): 
                $poster = 'https://image.tmdb.org/t/p/w500' . $show['poster_path'];
                $title = $show['name'];
                $year = substr($show['first_air_date'] ?? '', 0, 4);
                $rating = number_format($show['vote_average'], 1);
            ?&gt;
            &lt;div class="card media-card" onclick="window.location.href='/detail.php?id=&lt;?php echo $show['id']; ?&gt;&amp;type=tv'"&gt;
                &lt;div class="media-poster"&gt;
                    &lt;img src="&lt;?php echo $poster; ?&gt;" alt="&lt;?php echo htmlspecialchars($title); ?&gt;" loading="lazy"&gt;
                    &lt;span class="media-badge rating-badge"&gt;★ &lt;?php echo $rating; ?&gt;&lt;/span&gt;
                    &lt;div class="play-btn-overlay"&gt;&lt;/div&gt;
                &lt;/div&gt;
                &lt;div class="media-info"&gt;
                    &lt;div class="media-title"&gt;&lt;?php echo htmlspecialchars($title); ?&gt;&lt;/div&gt;
                    &lt;div class="media-meta"&gt;&lt;?php echo $year ?: '未知'; ?&gt; · 电视剧&lt;/div&gt;
                &lt;/div&gt;
            &lt;/div&gt;
            &lt;?php endforeach; ?&gt;
        &lt;/div&gt;
    &lt;/section&gt;
    &lt;?php endif; ?&gt;
&lt;/div&gt;

&lt;script&gt;
&lt;?php if (count($slides) &gt; 1): ?&gt;
let currentSlide = 0;
const slides = document.querySelectorAll('.hero-slide');
const dots = document.querySelectorAll('.slider-dot');
const totalSlides = slides.length;

function goToSlide(index) {
    slides.forEach(s =&gt; s.classList.remove('active'));
    dots.forEach(d =&gt; d.classList.remove('active'));
    slides[index].classList.add('active');
    dots[index].classList.add('active');
    currentSlide = index;
}

dots.forEach((dot, i) =&gt; {
    dot.addEventListener('click', () =&gt; goToSlide(i));
});

setInterval(() =&gt; {
    currentSlide = (currentSlide + 1) % totalSlides;
    goToSlide(currentSlide);
}, 5000);
&lt;?php endif; ?&gt;
&lt;/script&gt;

&lt;?php require_once __DIR__ . '/includes/footer.php'; ?&gt;
