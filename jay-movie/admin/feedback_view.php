<?php
/**
 * 管理后台 - 反馈详情 / 官方回复
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

/* ---------- POST 处理：官方回复 / 删除回复 ---------- */
if (is_post()) {
    csrf_guard();
    $act = post('act');

    if ($act === 'reply') {
        $fid  = (int)post('fid');
        $text = trim(post('content'));
        if ($fid <= 0 || !DB::one("SELECT id FROM feedback WHERE id = ?", array($fid))) {
            redirect('feedback_view.php?id=' . $fid . '&err=' . urlencode('反馈不存在'));
        }
        if ($text === '') redirect('feedback_view.php?id=' . $fid . '&err=' . urlencode('回复内容不能为空'));
        if (mb_strlen($text, 'UTF-8') > 1000) redirect('feedback_view.php?id=' . $fid . '&err=' . urlencode('回复内容过长'));
        DB::insert("INSERT INTO feedback_replies (feedback_id, user_id, content, is_admin, created_at) VALUES (?,?,?,1,?)",
            array($fid, (int)$admin['id'], $text, now_str()));
        redirect('feedback_view.php?id=' . $fid);
    }

    if ($act === 'del_reply') {
        $rid = (int)post('rid');
        $row = DB::row("SELECT id, feedback_id FROM feedback_replies WHERE id = ?", array($rid));
        if ($row) {
            DB::exec("DELETE FROM feedback_replies WHERE id = ?", array($rid));
            redirect('feedback_view.php?id=' . (int)$row['feedback_id']);
        }
        redirect('feedback.php');
    }
}

$id = (int)get_param('id', 0);
$f = DB::row(
    "SELECT f.*, u.username, u.avatar, u.is_admin,
        (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id = f.id) AS like_count
     FROM feedback f JOIN users u ON u.id = f.user_id WHERE f.id = ?",
    array($id)
);
if (!$f) redirect('feedback.php');

$err = get_param('err');

/* 回复列表：管理员优先 */
$replies = DB::all(
    "SELECT r.*, u.username, u.avatar, u.is_admin
     FROM feedback_replies r JOIN users u ON u.id = r.user_id
     WHERE r.feedback_id = ? ORDER BY r.is_admin DESC, r.created_at ASC",
    array($id)
);

admin_header(array('title' => '反馈详情', 'active' => 'feedback'));
?>
<?php if ($err): ?><div class="auth-alert err" style="margin-bottom:18px"><?php echo e($err); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head">
        <h2><span class="ic ic-chat"></span><?php echo e($f['title']); ?></h2>
        <a class="btn btn-ghost btn-sm" href="feedback.php"><span class="ic ic-arrow-l"></span>返回列表</a>
    </div>
    <div class="panel-body">
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px">
            <?php echo user_avatar_html($f, 40); ?>
            <div>
                <div class="fb-author"><?php echo admin_name_badged($f['username'], $f['is_admin']); ?></div>
                <div class="fb-time"><?php echo dt($f['created_at']); ?> · <?php echo (int)$f['like_count']; ?> 人点赞</div>
            </div>
        </div>
        <div style="font-size:14px;color:#c3cad8;line-height:2;white-space:pre-wrap;word-break:break-word;background:var(--bg2);border:1px solid var(--border);border-radius:12px;padding:16px 18px"><?php echo e($f['content']); ?></div>

        <h3 style="font-size:14.5px;margin:24px 0 10px;display:flex;align-items:center;gap:8px">
            <span class="ic ic-chat" style="color:var(--accent-h)"></span>回复（<?php echo count($replies); ?> 条 · 管理员回复优先展示）
        </h3>

        <?php foreach ($replies as $r): ?>
        <div class="reply-item" style="padding:14px 0;border-top:1px dashed var(--border)">
            <div style="flex:none"><?php echo user_avatar_html($r, 34); ?></div>
            <div class="reply-main">
                <div class="reply-head">
                    <span class="reply-author"><?php echo admin_name_badged($r['username'], $r['is_admin']); ?></span>
                    <span><?php echo dt($r['created_at']); ?></span>
                    <?php if ((int)$r['is_admin'] === 1): ?><span class="badge badge-red">官方回复</span><?php endif; ?>
                </div>
                <div class="reply-content"><?php echo e($r['content']); ?></div>
            </div>
            <form method="post" data-confirm="删除该条回复？">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="act" value="del_reply">
                <input type="hidden" name="rid" value="<?php echo (int)$r['id']; ?>">
                <input type="hidden" name="back" value="<?php echo $id; ?>">
                <button class="icon-btn" type="submit" title="删除回复"><span class="ic ic-trash"></span></button>
            </form>
        </div>
        <?php endforeach; ?>
        <?php if (!$replies): ?><p style="color:var(--sub);font-size:13.5px;padding:14px 0">暂无回复</p><?php endif; ?>

        <form method="post" style="margin-top:22px;display:flex;gap:10px">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="act" value="reply">
            <input type="hidden" name="fid" value="<?php echo $id; ?>">
            <input class="form-input" type="text" name="content" placeholder="以官方身份回复（将优先展示在提问者下方）…" maxlength="1000" required style="flex:1">
            <button class="btn btn-accent" type="submit"><span class="ic ic-send"></span>官方回复</button>
        </form>
    </div>
</div>
<?php admin_footer(); ?>
