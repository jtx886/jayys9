<?php
/**
 * Jay影视 - 反馈详情页
 * 回复排序：提问者内容下方优先展示管理员回复，管理员回复下方展示普通用户回复
 * 回复总数 > 3 自动折叠，点击展开加载全部回复
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = current_user();
$id = (int)get_param('id', 0);

$f = DB::row(
    "SELECT f.*, u.username, u.avatar, u.is_admin,
        (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id = f.id) AS like_count
     FROM feedback f JOIN users u ON u.id = f.user_id WHERE f.id = ?",
    array($id)
);
if (!$f) {
    render_header(array('title' => '反馈不存在'));
    echo empty_state('反馈不存在或已被删除');
    render_footer();
    exit;
}

$replies = DB::all(
    "SELECT r.*, u.username, u.avatar, u.is_admin
     FROM feedback_replies r JOIN users u ON u.id = r.user_id
     WHERE r.feedback_id = ?
     ORDER BY r.is_admin DESC, r.created_at ASC",
    array($id)
);

$myLiked = $user ? (bool)DB::one("SELECT id FROM feedback_likes WHERE feedback_id = ? AND user_id = ?", array($id, (int)$user['id'])) : false;

/* 折叠：总数>3 只显示前3条，其余折叠 */
$COLLAPSE_AT = 3;
$totalReplies = count($replies);
$collapsed = $totalReplies > $COLLAPSE_AT;
$visibleReplies = $collapsed ? array_slice($replies, 0, $COLLAPSE_AT) : $replies;
$hiddenReplies = $collapsed ? array_slice($replies, $COLLAPSE_AT) : array();

function render_reply_item($r)
{
    $isAdmin = (int)$r['is_admin'] === 1;
    echo '<div class="reply-item">'
        . '<div style="flex:none">' . user_avatar_html($r, 34) . '</div>'
        . '<div class="reply-main">'
        . '<div class="reply-head"><span class="reply-author">' . e($r['username'])
        . ($isAdmin ? '<span class="badge-dev">开发者</span>' : '')
        . '</span><span>' . dt($r['created_at']) . '</span>'
        . ($isAdmin ? '<span class="badge badge-red">官方回复</span>' : '')
        . '</div>'
        . '<div class="reply-content">' . e($r['content']) . '</div>'
        . '</div></div>';
}

render_header(array('title' => $f['title'], 'active' => 'feedback'));
?>
<div class="container" style="max-width:900px">
    <div class="fb-card fade-up">
        <div class="fb-head">
            <?php echo user_avatar_html($f, 44); ?>
            <div>
                <div class="fb-author"><?php echo e($f['username']); ?><?php echo (int)$f['is_admin'] === 1 ? '<span class="badge-dev">开发者</span>' : ''; ?></div>
                <div class="fb-time">发布于 <?php echo dt($f['created_at']); ?></div>
            </div>
        </div>
        <h2 class="fb-title" style="font-size:19px"><?php echo e($f['title']); ?></h2>
        <div class="fb-content"><?php echo e($f['content']); ?></div>
        <div class="fb-foot">
            <button class="fb-action<?php echo $myLiked ? ' liked' : ''; ?>" data-like="<?php echo (int)$f['id']; ?>" type="button">
                <span class="ic ic-heart<?php echo $myLiked ? ' on' : ''; ?>"></span>
                有用 <span class="like-count"><?php echo (int)$f['like_count']; ?></span>
            </button>
            <span class="fb-stats">共 <?php echo $totalReplies; ?> 条回复</span>
            <a class="fb-action" href="feedback.php"><span class="ic ic-arrow-l"></span>返回列表</a>
        </div>

        <?php if ($totalReplies > 0): ?>
        <div class="reply-list">
            <div style="font-size:13px;color:var(--sub);margin-bottom:4px;display:flex;align-items:center;gap:7px">
                <span class="ic ic-chat"></span>全部回复（管理员回复优先展示）
            </div>
            <?php foreach ($visibleReplies as $r) render_reply_item($r); ?>

            <?php if ($collapsed): ?>
            <div class="reply-hidden" id="hiddenReplies">
                <?php foreach ($hiddenReplies as $r) render_reply_item($r); ?>
            </div>
            <button class="reply-toggle" data-target="hiddenReplies" type="button">
                展开全部 <?php echo $totalReplies; ?> 条回复（还有 <?php echo count($hiddenReplies); ?> 条）
                <span class="ic ic-arrow-d"></span>
            </button>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if ($user && !user_banned($user)): ?>
        <form class="reply-form" data-reply="<?php echo (int)$f['id']; ?>">
            <?php echo user_avatar_html($user, 34); ?>
            <input class="form-input" type="text" name="content" placeholder="友善回复，理性讨论…" maxlength="1000">
            <button class="btn btn-accent" type="submit"><span class="ic ic-send"></span>回复</button>
        </form>
        <?php elseif (!$user): ?>
        <div style="margin-top:18px;text-align:center;padding:16px;border:1px dashed var(--border-h);border-radius:12px">
            <a href="login.php?notice=fav" style="color:var(--accent-h);font-size:14px;font-weight:600">登录后参与回复与点赞</a>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php render_footer(); ?>
