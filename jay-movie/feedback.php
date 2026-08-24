<?php
/**
 * Jay影视 - 反馈中心
 * 普通用户提交反馈；全部用户可浏览、点赞、回复
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = current_user();
$error = '';

if (is_post() && post('act') === 'new') {
    csrf_guard();
    if (!$user) {
        redirect('login.php?notice=fav');
    }
    if (user_banned($user)) {
        $error = '账号封禁期间无法提交反馈';
    } else {
        $title = mb_cut(post('title'), 100);
        $content = trim(post('content'));
        if ($title === '' || $content === '') {
            $error = '标题和内容不能为空';
        } elseif (mb_strlen($content, 'UTF-8') > 3000) {
            $error = '内容过长（最多3000字）';
        } else {
            DB::insert("INSERT INTO feedback (user_id, title, content, is_public, created_at) VALUES (?,?,?,1,?)",
                array((int)$user['id'], $title, $content, now_str()));
            redirect('feedback.php?posted=1');
        }
    }
}

$page = max(1, (int)get_param('p', 1));
$perPage = 10;
$offset = ($page - 1) * $perPage;
$total = (int)DB::one("SELECT COUNT(*) FROM feedback");
$list = DB::all(
    "SELECT f.*, u.username, u.avatar, u.is_admin,
        (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.id) AS reply_count,
        (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id = f.id) AS like_count
     FROM feedback f JOIN users u ON u.id = f.user_id
     ORDER BY f.id DESC LIMIT {$offset}, {$perPage}"
);
$totalPages = max(1, (int)ceil($total / $perPage));

/* 当前用户点赞集合 */
$myLikes = array();
if ($user) {
    foreach (DB::all("SELECT feedback_id FROM feedback_likes WHERE user_id = ?", array((int)$user['id'])) as $r) {
        $myLikes[(int)$r['feedback_id']] = true;
    }
}

render_header(array('title' => '反馈中心', 'active' => 'feedback'));
?>
<div class="container">
    <div class="page-head fade-up">
        <h1>反馈中心</h1>
        <p>遇到问题或有好建议？欢迎留言反馈，全部用户可公开浏览、点赞与回复</p>
    </div>

    <div class="fb-layout">
        <div>
            <?php if (isset($_GET['posted'])): ?>
            <div class="auth-alert ok" style="margin-bottom:18px">反馈提交成功，感谢您的支持！</div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="auth-alert err" style="margin-bottom:18px"><?php echo e($error); ?></div>
            <?php endif; ?>

            <?php if ($list): foreach ($list as $f): ?>
            <div class="fb-card fade-up">
                <div class="fb-head">
                    <?php echo user_avatar_html($f, 40); ?>
                    <div>
                        <div class="fb-author"><?php echo e($f['username']); ?><?php echo (int)$f['is_admin'] === 1 ? '<span class="badge-dev">开发者</span>' : ''; ?></div>
                        <div class="fb-time"><?php echo dt($f['created_at']); ?></div>
                    </div>
                </div>
                <a href="feedback_view.php?id=<?php echo (int)$f['id']; ?>" style="color:inherit;display:block">
                    <h3 class="fb-title"><?php echo e($f['title']); ?></h3>
                    <div class="fb-content"><?php echo e(mb_cut($f['content'], 160)); ?></div>
                </a>
                <div class="fb-foot">
                    <button class="fb-action<?php echo isset($myLikes[(int)$f['id']]) ? ' liked' : ''; ?>" data-like="<?php echo (int)$f['id']; ?>" type="button">
                        <span class="ic ic-heart<?php echo isset($myLikes[(int)$f['id']]) ? ' on' : ''; ?>"></span>
                        有用 <span class="like-count"><?php echo (int)$f['like_count']; ?></span>
                    </button>
                    <a class="fb-action" href="feedback_view.php?id=<?php echo (int)$f['id']; ?>">
                        <span class="ic ic-chat"></span>回复 <span><?php echo (int)$f['reply_count']; ?></span>
                    </a>
                    <span class="fb-stats"><span class="ic ic-eye"></span> 公开反馈</span>
                </div>
            </div>
            <?php endforeach; else: ?>
            <?php echo empty_state('还没有反馈，来抢第一个沙发吧', 'ic-chat'); ?>
            <?php endif; ?>

            <?php echo render_pagination('feedback.php', $page, $totalPages); ?>
        </div>

        <aside>
            <div class="fb-card fade-up" style="position:sticky;top:84px">
                <h3 style="font-size:16px;font-weight:800;margin-bottom:14px;display:flex;align-items:center;gap:8px">
                    <span class="ic ic-edit" style="color:var(--accent-h)"></span>发布新反馈
                </h3>
                <?php if ($user && !user_banned($user)): ?>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                    <input type="hidden" name="act" value="new">
                    <div class="form-item">
                        <input class="form-input" type="text" name="title" placeholder="反馈标题（一句话概括）" maxlength="100" required>
                    </div>
                    <div class="form-item">
                        <textarea class="form-textarea" name="content" placeholder="详细描述您遇到的问题或建议…" required></textarea>
                    </div>
                    <button class="btn btn-accent btn-block" type="submit"><span class="ic ic-send"></span>提交反馈</button>
                </form>
                <?php else: ?>
                <p style="color:var(--sub);font-size:13.5px;line-height:2">
                    <?php if ($user): ?>账号封禁期间暂时无法提交反馈。<?php else: ?>登录后即可提交反馈、点赞与回复。<br><br>
                    <a class="btn btn-accent btn-block" href="login.php"><span class="ic ic-user"></span>立即登录</a><?php endif; ?>
                </p>
                <?php endif; ?>
            </div>
        </aside>
    </div>
</div>
<?php render_footer(); ?>
