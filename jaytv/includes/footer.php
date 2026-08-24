&lt;/div&gt;&lt;!-- end main-content --&gt;

&lt;!-- 移动端底部导航 --&gt;
&lt;?php if (true): ?&gt;
&lt;nav class="mobile-nav"&gt;
    &lt;div class="mobile-nav-items"&gt;
        &lt;a href="/index.php" class="mobile-nav-item &lt;?php echo basename($_SERVER['PHP_SELF']) === 'index.php' ? 'active' : ''; ?&gt;"&gt;
            &lt;span class="mobile-nav-icon icon-home"&gt;&lt;/span&gt;
            &lt;span&gt;首页&lt;/span&gt;
        &lt;/a&gt;
        &lt;a href="/search.php" class="mobile-nav-item &lt;?php echo basename($_SERVER['PHP_SELF']) === 'search.php' ? 'active' : ''; ?&gt;"&gt;
            &lt;span class="mobile-nav-icon icon-search"&gt;&lt;/span&gt;
            &lt;span&gt;搜索&lt;/span&gt;
        &lt;/a&gt;
        &lt;a href="/profile.php" class="mobile-nav-item &lt;?php echo basename($_SERVER['PHP_SELF']) === 'profile.php' ? 'active' : ''; ?&gt;"&gt;
            &lt;span class="mobile-nav-icon icon-user"&gt;&lt;/span&gt;
            &lt;span&gt;我的&lt;/span&gt;
        &lt;/a&gt;
    &lt;/div&gt;
&lt;/nav&gt;
&lt;?php endif; ?&gt;

&lt;footer class="footer"&gt;
    &lt;div class="footer-content"&gt;
        &lt;div class="footer-logo"&gt;🎬 Jay影视&lt;/div&gt;
        &lt;p class="footer-text"&gt;免费在线影视平台，提供高清电影、电视剧、动漫、综艺在线观看&lt;/p&gt;
        &lt;div class="footer-links"&gt;
            &lt;a href="/index.php"&gt;首页&lt;/a&gt;
            &lt;a href="/feedback.php"&gt;意见反馈&lt;/a&gt;
            &lt;?php if (isAdmin()): ?&gt;
                &lt;a href="/admin/index.php"&gt;管理后台&lt;/a&gt;
            &lt;?php endif; ?&gt;
        &lt;/div&gt;
        &lt;div class="footer-bottom"&gt;
            &amp;copy; &lt;?php echo date('Y'); ?&gt; Jay影视. All Rights Reserved.
        &lt;/div&gt;
    &lt;/div&gt;
&lt;/footer&gt;

&lt;script&gt;
function toggleMobileMenu() {
    const menu = document.getElementById('mobileMenu');
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

document.getElementById('navSearch')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' &amp;&amp; this.value.trim()) {
        window.location.href = '/search.php?q=' + encodeURIComponent(this.value.trim());
    }
});

document.getElementById('mobileSearchInput')?.addEventListener('keypress', function(e) {
    if (e.key === 'Enter' &amp;&amp; this.value.trim()) {
        window.location.href = '/search.php?q=' + encodeURIComponent(this.value.trim());
    }
});

&lt;?php if (basename($_SERVER['PHP_SELF']) === 'index.php'): ?&gt;
// 公告弹窗
const lastAnnouncement = localStorage.getItem('last_announcement_id');
const currentAnnouncement = &lt;?php 
    $db = Database::getInstance();
    $ann = $db-&gt;fetch("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
    echo $ann ? $ann['id'] : 0;
?&gt;;
const annContent = &lt;?php echo $ann ? json_encode($ann['content']) : 'null'; ?&gt;;
const annTitle = &lt;?php echo $ann ? json_encode($ann['title']) : '""'; ?&gt;;

if (annContent &amp;&amp; lastAnnouncement != currentAnnouncement) {
    setTimeout(() =&gt; {
        showAnnouncement(annTitle, annContent, currentAnnouncement);
    }, 500);
}

function showAnnouncement(title, content, id) {
    const overlay = document.createElement('div');
    overlay.className = 'modal-overlay announcement-modal show';
    overlay.innerHTML = `
        &lt;div class="modal"&gt;
            &lt;div class="announcement-title"&gt;📢 ${title}&lt;/div&gt;
            &lt;div class="announcement-content"&gt;${content}&lt;/div&gt;
            &lt;label class="announcement-checkbox"&gt;
                &lt;span class="checkbox-custom" id="dontShowAgain"&gt;&lt;/span&gt;
                不再提示
            &lt;/label&gt;
            &lt;button class="btn btn-primary" style="width:100%" onclick="closeAnnouncement(${id})"&gt;我知道了&lt;/button&gt;
        &lt;/div&gt;
    `;
    document.body.appendChild(overlay);
    
    document.getElementById('dontShowAgain').addEventListener('click', function() {
        this.classList.toggle('checked');
    });
}

function closeAnnouncement(id) {
    const checked = document.getElementById('dontShowAgain').classList.contains('checked');
    if (checked) {
        localStorage.setItem('last_announcement_id', id);
    }
    document.querySelector('.announcement-modal').remove();
}
&lt;?php endif; ?&gt;

// 全局消息提示
function showToast(msg, type = 'info') {
    const toast = document.createElement('div');
    toast.style.cssText = `
        position: fixed; top: 90px; left: 50%; transform: translateX(-50%) translateY(-20px);
        padding: 12px 24px; background: ${type === 'error' ? 'rgba(239,68,68,0.95)' : type === 'success' ? 'rgba(16,185,129,0.95)' : 'rgba(124,58,237,0.95)'};
        color: white; border-radius: 10px; z-index: 9999; font-weight: 500;
        opacity: 0; transition: all 0.3s ease; backdrop-filter: blur(10px);
    `;
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(() =&gt; {
        toast.style.opacity = '1';
        toast.style.transform = 'translateX(-50%) translateY(0)';
    }, 10);
    setTimeout(() =&gt; {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(-50%) translateY(-20px)';
        setTimeout(() =&gt; toast.remove(), 300);
    }, 3000);
}
&lt;/script&gt;
&lt;/body&gt;
&lt;/html&gt;
