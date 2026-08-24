<?php
/**
 * 管理后台 - 仪表盘
 * 统计 + 最新注册用户 + 最新反馈 + 【观看历史】【用户收藏】两个独立模块（支持筛选指定用户）
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

/* ---------- 统计 ---------- */
$statUsers  = (int)DB::one("SELECT COUNT(*) FROM users");
$statFb     = (int)DB::one("SELECT COUNT(*) FROM feedback");
$statHist   = (int)DB::one("SELECT COUNT(*) FROM watch_history");
$statFav    = (int)DB::one("SELECT COUNT(*) FROM favorites");
$statToday  = (int)DB::one("SELECT COUNT(*) FROM users WHERE created_at > ?", array(date('Y-m-d 00:00:00')));
$statMail   = (int)DB::one("SELECT COUNT(*) FROM mail_log WHERE status = 1 AND created_at > ?", array(date('Y-m-d 00:00:00')));

$latestUsers = DB::all("SELECT * FROM users ORDER BY id DESC LIMIT 6");
$latestFb = DB::all(
    "SELECT f.*, u.username, u.avatar, u.is_admin,
        (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.id) AS reply_count
     FROM feedback f JOIN users u ON u.id = f.user_id ORDER BY f.id DESC LIMIT 5"
);

/* ---------- 用户筛选（两个模块独立） ---------- */
$allUsers = DB::all("SELECT id, username, email FROM users ORDER BY id DESC LIMIT 500");
$huid = (int)get_param('huid', 0);
$fuid = (int)get_param('fuid', 0);

$histWhere = $huid > 0 ? "WHERE h.user_id = " . $huid : '';
$history = DB::all(
    "SELECT h.*, u.username FROM watch_history h JOIN users u ON u.id = h.user_id {$histWhere}
     ORDER BY h.updated_at DESC LIMIT 30"
);
$favWhere = $fuid > 0 ? "WHERE f.user_id = " . $fuid : '';
$favorites = DB::all(
    "SELECT f.*, u.username FROM favorites f JOIN users u ON u.id = f.user_id {$favWhere}
     ORDER BY f.id DESC LIMIT 30"
);

admin_header(array('title' => '仪表盘', 'active' => 'dashboard'));
?>

<div class="stat-grid fade-up">
    <div class="stat-card"><div class="stat-icon"><span class="ic ic-user"></span></div><div><div class="stat-num"><?php echo $statUsers; ?></div><div class="stat-label">注册用户（今日 +<?php echo $statToday; ?>）</div></div></div>
    <div class="stat-card"><div class="stat-icon"><span class="ic ic-chat"></span></div><div><div class="stat-num"><?php echo $statFb; ?></div><div class="stat-label">用户反馈</div></div></div>
    <div class="stat-card"><div class="stat-icon"><span class="ic ic-clock"></span></div><div><div class="stat-num"><?php echo $statHist; ?></div><div class="stat-label">观看历史记录</div></div></div>
    <div class="stat-card"><div class="stat-icon"><span class="ic ic-heart"></span></div><div><div class="stat-num"><?php echo $statFav; ?></div><div class="stat-label">用户收藏</div></div></div>
    <div class="stat-card"><div class="stat-icon"><span class="ic ic-mail"></span></div><div><div class="stat-num"><?php echo $statMail; ?></div><div class="stat-label">今日发送邮件</div></div></div>
</div>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-user"></span>最新注册用户</h2><a class="btn btn-ghost btn-sm" href="users.php">全部用户</a></div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>用户</th><th>邮箱</th><th>注册时间</th><th>状态</th></tr></thead>
            <tbody>
            <?php foreach ($latestUsers as $u): ?>
            <tr>
                <td><div class="u-cell"><?php echo user_avatar_html($u, 32); ?><span><?php echo admin_name_badged($u['username'], $u['is_admin']); ?></span></div></td>
                <td style="color:var(--sub)"><?php echo e($u['email']); ?></td>
                <td style="color:var(--sub)"><?php echo dt($u['created_at']); ?></td>
                <td><?php echo user_banned($u) ? '<span class="badge badge-red">已封禁</span>' : '<span class="badge badge-green">正常</span>'; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-chat"></span>最新反馈</h2><a class="btn btn-ghost btn-sm" href="feedback.php">反馈管理</a></div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>标题</th><th>反馈人</th><th>回复数</th><th>时间</th></tr></thead>
            <tbody>
            <?php foreach ($latestFb as $f): ?>
            <tr>
                <td><a href="feedback_view.php?id=<?php echo (int)$f['id']; ?>" style="color:var(--accent-h)"><?php echo e(mb_cut($f['title'], 30)); ?></a></td>
                <td><div class="u-cell"><?php echo user_avatar_html($f, 28); ?><span><?php echo admin_name_badged($f['username'], $f['is_admin']); ?></span></div></td>
                <td><?php echo (int)$f['reply_count']; ?></td>
                <td style="color:var(--sub)"><?php echo dt($f['created_at']); ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="panel fade-up">
    <div class="panel-head">
        <h2><span class="ic ic-clock"></span>观看历史</h2>
        <form method="get" class="toolbar">
            <select class="form-select" name="huid" onchange="this.form.submit()">
                <option value="0">筛选：全部用户</option>
                <?php foreach ($allUsers as $au): ?>
                <option value="<?php echo (int)$au['id']; ?>"<?php echo $huid === (int)$au['id'] ? ' selected' : ''; ?>><?php echo e($au['username']); ?>（<?php echo e($au['email']); ?>）</option>
                <?php endforeach; ?>
            </select>
            <?php if ($fuid > 0): ?><input type="hidden" name="fuid" value="<?php echo $fuid; ?>"><?php endif; ?>
        </form>
    </div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>用户</th><th>影片</th><th>集数</th><th>播放进度</th><th>最近观看</th></tr></thead>
            <tbody>
            <?php if ($history): foreach ($history as $h): ?>
            <tr>
                <td><?php echo e($h['username']); ?></td>
                <td><?php echo e(mb_cut($h['title'], 20)); ?></td>
                <td style="color:var(--sub)"><?php echo e($h['episode'] !== '' ? $h['episode'] : '正片'); ?></td>
                <td>
                    <?php if ((int)$h['duration'] > 0): ?>
                    <div class="progress-bar" style="width:120px;display:inline-block;vertical-align:middle"><i style="width:<?php echo min(100, round($h['position'] / $h['duration'] * 100)); ?>%"></i></div>
                    <span style="font-size:12px;color:var(--sub)"><?php echo fmt_duration($h['position']); ?>/<?php echo fmt_duration($h['duration']); ?></span>
                    <?php else: ?>
                    <span style="color:var(--sub)"><?php echo fmt_duration($h['position']); ?></span>
                    <?php endif; ?>
                </td>
                <td style="color:var(--sub)"><?php echo dt($h['updated_at']); ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--sub);padding:30px">暂无观看记录</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<div class="panel fade-up">
    <div class="panel-head">
        <h2><span class="ic ic-heart"></span>用户收藏</h2>
        <form method="get" class="toolbar">
            <select class="form-select" name="fuid" onchange="this.form.submit()">
                <option value="0">筛选：全部用户</option>
                <?php foreach ($allUsers as $au): ?>
                <option value="<?php echo (int)$au['id']; ?>"<?php echo $fuid === (int)$au['id'] ? ' selected' : ''; ?>><?php echo e($au['username']); ?>（<?php echo e($au['email']); ?>）</option>
                <?php endforeach; ?>
            </select>
            <?php if ($huid > 0): ?><input type="hidden" name="huid" value="<?php echo $huid; ?>"><?php endif; ?>
        </form>
    </div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>用户</th><th>影片</th><th>类型</th><th>收藏时间</th></tr></thead>
            <tbody>
            <?php if ($favorites): foreach ($favorites as $f): ?>
            <tr>
                <td><?php echo e($f['username']); ?></td>
                <td>
                    <div class="u-cell">
                        <?php if (!empty($f['poster'])): ?><img class="avatar" style="width:30px;height:42px;border-radius:6px;object-fit:cover" src="<?php echo e($f['poster']); ?>" alt=""><?php endif; ?>
                        <span><?php echo e(mb_cut($f['title'], 24)); ?></span>
                    </div>
                </td>
                <td style="color:var(--sub)"><?php echo $f['media_type'] === 'movie' ? '电影' : '剧集'; ?></td>
                <td style="color:var(--sub)"><?php echo dt($f['created_at']); ?></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="4" style="text-align:center;color:var(--sub);padding:30px">暂无收藏记录</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>

<?php admin_footer(); ?>
