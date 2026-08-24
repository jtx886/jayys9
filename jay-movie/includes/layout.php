<?php
/**
 * Jay影视 - 前端公共布局（导航 / 页脚 / 公告弹窗 / 通用组件）
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

/**
 * 输出页面头部
 * @param array $opts title/subtitle/active(nav键)/base(相对根路径)/body_class
 */
function render_header($opts = array())
{
    $title  = isset($opts['title']) ? $opts['title'] : site_name();
    $active = isset($opts['active']) ? $opts['active'] : '';
    $base   = isset($opts['base']) ? $opts['base'] : '';
    $user   = current_user();

    $navItems = array(
        'home'     => array('首页',   $base . 'index.php'),
        'movie'    => array('电影',   $base . 'category.php?t=movie'),
        'tv'       => array('电视剧', $base . 'category.php?t=tv'),
        'variety'  => array('综艺',   $base . 'category.php?t=variety'),
        'feedback' => array('反馈',   $base . 'feedback.php'),
        'anime'    => array('动漫',   $base . 'category.php?t=anime'),
    );

    $navHtml = '';
    foreach ($navItems as $k => $n) {
        $cls = 'nav-link' . ($active === $k ? ' active' : '');
        $navHtml .= '<a class="' . $cls . '" href="' . e($n[1]) . '">' . e($n[0]) . '</a>';
    }

    $userArea = '';
    if ($user) {
        $banned = user_banned($user);
        $uName = e($user['username']) . ((int)$user['is_admin'] === 1 ? '<span class="badge-dev">开发者</span>' : '');
        $userArea = '<div class="nav-user">'
            . '<a class="nav-user-head" href="' . $base . 'profile.php" title="个人中心">'
            . user_avatar_html($user, 34)
            . '<span class="nav-username">' . $uName . '</span>'
            . '</a>'
            . '<div class="nav-user-menu">'
            . '<a href="' . $base . 'profile.php">我的收藏</a>'
            . '<a href="' . $base . 'profile.php?tab=hist">观看历史</a>'
            . '<a href="' . $base . 'profile.php?tab=set">账号设置</a>'
            . ((int)$user['is_admin'] === 1 ? '<a href="' . $base . 'admin/">管理后台</a>' : '')
            . '<a class="logout-link" href="' . $base . 'logout.php">退出登录</a>'
            . '</div></div>';
    } else {
        $userArea = '<div class="nav-auth">'
            . '<a class="btn btn-ghost btn-sm" href="' . $base . 'login.php">登录</a>'
            . '<a class="btn btn-accent btn-sm" href="' . $base . 'register.php">注册</a>'
            . '</div>';
    }

    echo '<!DOCTYPE html><html lang="zh-CN"><head><meta charset="UTF-8">'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
        . '<meta name="csrf" content="' . csrf_token() . '">'
        . '<title>' . e($title) . ' - ' . e(site_name()) . '</title>'
        . theme_style_tag()
        . '<link rel="stylesheet" href="' . $base . 'assets/css/style.css?v=' . APP_VERSION . '">'
        . '</head><body class="' . (isset($opts['body_class']) ? $opts['body_class'] : '') . '">';

    echo '<header class="navbar"><div class="navbar-inner">'
        . '<a class="brand" href="' . $base . 'index.php"><span class="brand-logo"></span>' . e(site_name()) . '</a>'
        . '<button class="nav-toggle" id="navToggle" aria-label="菜单"><span></span></button>'
        . '<nav class="nav" id="mainNav">' . $navHtml . '</nav>'
        . '<form class="nav-search" action="' . $base . 'search.php" method="get">'
        . '<span class="ic ic-search"></span>'
        . '<input type="text" name="q" placeholder="搜索影视 / 剧集 / 综艺…" value="' . e(get_param('q')) . '" autocomplete="off">'
        . '</form>'
        . $userArea
        . '</div></header>';
}

function render_footer($base = '')
{
    echo '<footer class="footer"><div class="footer-inner">'
        . '<div class="footer-brand"><span class="brand-logo"></span>' . e(site_name()) . '</div>'
        . '<p>数据来源 TMDB · 播放资源来自第三方采集接口 · 本站不存储任何视频文件</p>'
        . '<p class="footer-links"><a href="' . $base . 'feedback.php">反馈中心</a><a href="' . $base . 'admin/login.php">管理后台</a></p>'
        . '<p class="copy">© ' . date('Y') . ' ' . e(site_name()) . ' · PHP + MySQL · v' . APP_VERSION . '</p>'
        . '</div></footer>';
    echo '<script src="' . $base . 'assets/js/main.js?v=' . APP_VERSION . '"></script></body></html>';
}

/* ================= 通用组件 ================= */

/** 媒体海报卡片 */
function media_card($item, $base = '')
{
    $type = tmdb_media_type($item);
    $id   = isset($item['id']) ? (int)$item['id'] : 0;
    if ($id <= 0) return '';
    $title = tmdb_title($item);
    $year  = tmdb_year($item);
    $rate  = tmdb_rating($item);
    $poster = tmdb_img(isset($item['poster_path']) ? $item['poster_path'] : '', 'w342');
    $url = $base . 'detail.php?id=' . $id . '&type=' . $type;

    $posterHtml = $poster !== ''
        ? '<img loading="lazy" src="' . e($poster) . '" alt="' . e($title) . '">'
        : '<div class="poster-empty"><span class="ic ic-film"></span></div>';

    return '<a class="mcard" href="' . e($url) . '">'
        . '<div class="mcard-poster">' . $posterHtml
        . ($rate > 0 ? '<span class="mcard-rate"><span class="ic ic-star"></span>' . $rate . '</span>' : '')
        . '<span class="mcard-type">' . ($type === 'movie' ? '电影' : '剧集') . '</span>'
        . '</div>'
        . '<div class="mcard-title" title="' . e($title) . '">' . e($title) . '</div>'
        . '<div class="mcard-sub">' . e($year !== '' ? $year : '—') . '</div>'
        . '</a>';
}

/** 横向滚动行 */
function media_row($title, $items, $moreUrl = '', $base = '')
{
    $cards = '';
    if (is_array($items)) {
        foreach ($items as $it) { $cards .= media_card($it, $base); }
    }
    if ($cards === '') return '';
    $more = $moreUrl !== '' ? '<a class="row-more" href="' . e($moreUrl) . '">查看更多<span class="ic ic-arrow-r"></span></a>' : '';
    return '<section class="media-row fade-up"><div class="row-head"><h2>' . e($title) . '</h2>' . $more . '</div>'
        . '<div class="row-scroll">' . $cards . '</div></section>';
}

/** 分页 */
function render_pagination($base, $page, $totalPages)
{
    $page = (int)$page; $totalPages = (int)$totalPages;
    if ($totalPages <= 1) return '';
    $sep = strpos($base, '?') !== false ? '&' : '?';
    $html = '<div class="pagination">';
    if ($page > 1) $html .= '<a class="page-btn" href="' . e($base . $sep . 'p=' . ($page - 1)) . '"><span class="ic ic-arrow-l"></span>上一页</a>';
    $start = max(1, $page - 2); $end = min($totalPages, $page + 2);
    if ($start > 1) { $html .= '<a class="page-btn" href="' . e($base . $sep . 'p=1') . '">1</a>'; if ($start > 2) $html .= '<span class="page-ellipsis">…</span>'; }
    for ($i = $start; $i <= $end; $i++) {
        $html .= '<a class="page-btn' . ($i === $page ? ' current' : '') . '" href="' . e($base . $sep . 'p=' . $i) . '">' . $i . '</a>';
    }
    if ($end < $totalPages) { if ($end < $totalPages - 1) $html .= '<span class="page-ellipsis">…</span>'; $html .= '<a class="page-btn" href="' . e($base . $sep . 'p=' . $totalPages) . '">' . $totalPages . '</a>'; }
    if ($page < $totalPages) $html .= '<a class="page-btn" href="' . e($base . $sep . 'p=' . ($page + 1)) . '">下一页<span class="ic ic-arrow-r"></span></a>';
    return $html . '</div>';
}

/** 获取当前生效公告（仅首页调用） */
function get_active_announcement()
{
    return DB::row("SELECT a.* FROM announcements a WHERE a.is_active = 1 ORDER BY a.id DESC LIMIT 1");
}

/** 公告弹窗（仅首页渲染，配合 Cookie 版本判断） */
function render_announcement_popup()
{
    $ann = get_active_announcement();
    if (!$ann) return;
    $version = get_setting('ann_version', '1');
    $hidden = isset($_COOKIE['jay_ann_v']) && $_COOKIE['jay_ann_v'] === $version;
    if ($hidden) return;

    echo '<div class="modal-mask ann-modal' . ($hidden ? ' hide' : '') . '" id="annModal">'
        . '<div class="modal">'
        . '<div class="modal-head"><h3><span class="ic ic-megaphone"></span>' . e($ann['title']) . '</h3>'
        . '<button class="modal-close" data-close="annModal"><span class="ic ic-close"></span></button></div>'
        . '<div class="modal-body ann-content">' . nl2br(e($ann['content'])) . '</div>'
        . '<div class="modal-foot ann-foot">'
        . '<label class="ann-check"><input type="checkbox" id="annNoTip"><i></i>不再提示</label>'
        . '<button class="btn btn-accent" id="annOk">我知道了</button>'
        . '</div></div></div>';
    echo '<script>window.JAY_ANN_VERSION = ' . json_encode($version) . ';</script>';
}

/** 空状态 */
function empty_state($text, $icon = 'ic-film')
{
    return '<div class="empty-state"><span class="ic ' . e($icon) . '"></span><p>' . e($text) . '</p></div>';
}
