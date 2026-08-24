&lt;?php
require_once __DIR__ . '/init.php';

$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?&gt;
&lt;!DOCTYPE html&gt;
&lt;html lang="zh-CN"&gt;
&lt;head&gt;
&lt;meta charset="UTF-8"&gt;
&lt;meta name="viewport" content="width=device-width, initial-scale=1.0"&gt;
&lt;title&gt;&lt;?php echo isset($pageTitle) ? $pageTitle . ' - ' : ''; ?&gt;Jay影视&lt;/title&gt;
&lt;link rel="stylesheet" href="/assets/css/style.css"&gt;
&lt;style&gt;
:root {
    --primary: &lt;?php echo getThemeColor(); ?&gt;;
    --primary-dark: &lt;?php echo getThemeColor(); ?&gt;;
}
&lt;/style&gt;
&lt;/head&gt;
&lt;body&gt;

&lt;nav class="navbar"&gt;
    &lt;div class="nav-container"&gt;
        &lt;a href="/index.php" class="logo"&gt;
            &lt;span class="logo-icon"&gt;&lt;/span&gt;
            Jay影视
        &lt;/a&gt;
        
        &lt;ul class="nav-links"&gt;
            &lt;li&gt;&lt;a href="/index.php" class="&lt;?php echo $currentPage === 'index' ? 'active' : ''; ?&gt;"&gt;首页&lt;/a&gt;&lt;/li&gt;
            &lt;li&gt;&lt;a href="/category.php?type=movie" class="&lt;?php echo isset($_GET['type']) &amp;&amp; $_GET['type'] === 'movie' ? 'active' : ''; ?&gt;"&gt;电影&lt;/a&gt;&lt;/li&gt;
            &lt;li&gt;&lt;a href="/category.php?type=tv" class="&lt;?php echo isset($_GET['type']) &amp;&amp; $_GET['type'] === 'tv' ? 'active' : ''; ?&gt;"&gt;电视剧&lt;/a&gt;&lt;/li&gt;
            &lt;li&gt;&lt;a href="/category.php?type=anime"&gt;动漫&lt;/a&gt;&lt;/li&gt;
            &lt;li&gt;&lt;a href="/category.php?type=variety"&gt;综艺&lt;/a&gt;&lt;/li&gt;
            &lt;li&gt;&lt;a href="/feedback.php" class="&lt;?php echo $currentPage === 'feedback' ? 'active' : ''; ?&gt;"&gt;反馈&lt;/a&gt;&lt;/li&gt;
        &lt;/ul&gt;
        
        &lt;div class="nav-right"&gt;
            &lt;div class="search-box"&gt;
                &lt;span class="search-icon"&gt;&lt;/span&gt;
                &lt;input type="text" id="navSearch" placeholder="搜索电影、电视剧、动漫..."&gt;
            &lt;/div&gt;
            
            &lt;?php if (isLoggedIn()): ?&gt;
                &lt;?php if (isAdmin()): ?&gt;
                    &lt;a href="/admin/index.php" class="nav-btn nav-btn-login"&gt;管理后台&lt;/a&gt;
                &lt;?php endif; ?&gt;
                &lt;div class="user-menu"&gt;
                    &lt;a href="/profile.php" class="user-avatar"&gt;
                        &lt;?php if (!empty($_SESSION['user_avatar'])): ?&gt;
                            &lt;img src="&lt;?php echo $_SESSION['user_avatar']; ?&gt;" alt="avatar"&gt;
                        &lt;?php else: ?&gt;
                            &lt;?php echo mb_substr($_SESSION['username'], 0, 1); ?&gt;
                        &lt;?php endif; ?&gt;
                    &lt;/a&gt;
                &lt;/div&gt;
            &lt;?php else: ?&gt;
                &lt;a href="/login.php" class="nav-btn nav-btn-login"&gt;登录&lt;/a&gt;
                &lt;a href="/register.php" class="nav-btn nav-btn-register"&gt;注册&lt;/a&gt;
            &lt;?php endif; ?&gt;
            
            &lt;button class="mobile-menu-btn" onclick="toggleMobileMenu()"&gt;
                &lt;span&gt;&lt;/span&gt;
                &lt;span&gt;&lt;/span&gt;
                &lt;span&gt;&lt;/span&gt;
            &lt;/button&gt;
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/nav&gt;

&lt;!-- 移动端菜单 --&gt;
&lt;div class="mobile-menu" id="mobileMenu" style="display:none;position:fixed;top:70px;left:0;right:0;background:rgba(10,10,15,0.98);backdrop-filter:blur(20px);z-index:999;padding:20px;border-bottom:1px solid var(--border);"&gt;
    &lt;div style="margin-bottom:16px;"&gt;
        &lt;input type="text" id="mobileSearchInput" placeholder="搜索..." style="width:100%;padding:12px 16px;background:var(--bg-input);border:1px solid var(--border);border-radius:10px;color:white;font-size:14px;"&gt;
    &lt;/div&gt;
    &lt;div style="display:flex;flex-direction:column;gap:4px;"&gt;
        &lt;a href="/index.php" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;首页&lt;/a&gt;
        &lt;a href="/category.php?type=movie" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;电影&lt;/a&gt;
        &lt;a href="/category.php?type=tv" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;电视剧&lt;/a&gt;
        &lt;a href="/category.php?type=anime" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;动漫&lt;/a&gt;
        &lt;a href="/category.php?type=variety" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;综艺&lt;/a&gt;
        &lt;a href="/feedback.php" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;反馈&lt;/a&gt;
        &lt;?php if (isLoggedIn()): ?&gt;
            &lt;a href="/profile.php" style="padding:12px 16px;border-radius:8px;color:var(--text-secondary);"&gt;个人中心&lt;/a&gt;
            &lt;a href="/logout.php" style="padding:12px 16px;border-radius:8px;color:var(--danger);"&gt;退出登录&lt;/a&gt;
        &lt;?php else: ?&gt;
            &lt;a href="/login.php" style="padding:12px 16px;border-radius:8px;color:var(--primary-light);"&gt;登录&lt;/a&gt;
            &lt;a href="/register.php" style="padding:12px 16px;border-radius:8px;color:var(--primary-light);"&gt;注册&lt;/a&gt;
        &lt;?php endif; ?&gt;
    &lt;/div&gt;
&lt;/div&gt;

&lt;div class="main-content"&gt;
