<?php
/**
 * 管理后台 - 反馈管理（查看全部用户反馈，支持回复/删除）
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

$msg = ''; $msgType = 'ok';

if (is_post()) {
    csrf_guard();
    $act = post('act');
    if ($act === 'del') {
        $id = (int)post('id');
        DB::exec("DELETE FROM feedback WHERE id = ?", array($id));
        DB::exec("DELETE FROM feedback_replies WHERE feedback_id = ?", array($id));
        DB::exec("DELETE FROM feedback_likes WHERE feedback_id = ?", array($id));
        $msg = '反馈及全部回复已删除';
    }
    if ($act === 'del_reply') {
        DB::exec("DELETE FROM feedback_replies WHERE id = ?", array((int)post('rid')));
        $msg = '回复已删除';
    }
    if ($act === 'reply') {
        $fid = (int)post('fid');
        $content = trim(post('content'));
        if ($content === '') {
            $msg = '回复内容不能为空'; $msgType = 'err';
        } else {
            DB::insert("INSERT INTO feedback_replies (feedback_id, user_id, content, is_admin, created_at) VALUES (?,?,?,1,?)",
                array($fid, (int)$admin['id'], $content, now_str()));
            $msg = '官方回复已发布（将在反馈详情页优先展示）';
        }
    }
    if ($msg !== '' && isset($_POST['back'])) {
        redirect('feedback_view.php?id=' . (int)post('back') . ($msgType === 'err' ? '&err=' . urlencode($msg) : ''));
    }
}

$kw = get_param('kw');
$where = '1=1'; $params = array();
if ($kw !== '') {
    $where = "f.title LIKE ? OR u.username LIKE ?";
    $like = '%' . $kw . '%';
    $params = array($like, $like);
}
$list = DB::all(
    "SELECT f.*, u.username, u.avatar, u.is_admin,
        (SELECT COUNT(*) FROM feedback_replies r WHERE r.feedback_id = f.id) AS reply_count,
        (SELECT COUNT(*) FROM feedback_likes l WHERE l.feedback_id = f.id) AS like_count
     FROM feedback f JOIN users u ON u.id = f.user_id
     WHERE {$where} ORDER BY f.id DESC LIMIT 200", $params
);

admin_header(array('title' => '反馈管理', 'active' => 'feedback'));
?>
<?php if ($msg): ?><div class="auth-alert <?php echo $msgType; ?>" style="margin-bottom:18px"><?php echo e($msg); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head">
        <h2><span class="ic ic-chat"></span>全部反馈（<?php echo count($list); ?>）</h2>
        <form method="get" class="toolbar">
            <input class="form-input" type="text" name="kw" placeholder="搜索标题 / 用户名" value="<?php echo e($kw); ?>">
            <button class="btn btn-accent btn-sm" type="submit"><span class="ic ic-search"></span>搜索</button>
        </form>
    </div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>反馈</th><th>反馈人</th><th>回复数</th><th>点赞</th><th>时间</th><th>操作</th></tr></thead>
            <tbody>
            <?php if ($list): foreach ($list as $f): ?>
            <tr>
                <td style="max-width:360px">
                    <a href="feedback_view.php?id=<?php echo (int)$f['id']; ?>" style="color:var(--accent-h);font-weight:700"><?php echo e(mb_cut($f['title'], 26)); ?></a>
                    <div style="font-size:12.5px;color:var(--sub);margin-top:4px"><?php echo e(mb_cut($f['content'], 46)); ?></div>
                </td>
                <td><div class="u-cell"><?php echo user_avatar_html($f, 30); ?><span><?php echo admin_name_badged($f['username'], $f['is_admin']); ?></span></div></td>
                <td><?php echo (int)$f['reply_count']; ?></td>
                <td><?php echo (int)$f['like_count']; ?></td>
                <td style="color:var(--sub)"><?php echo dt($f['created_at']); ?></td>
                <td><div class="atable-actions">
                    <a class="btn btn-ghost btn-sm" href="feedback_view.php?id=<?php echo (int)$f['id']; ?>"><span class="ic ic-chat"></span>查看/回复</a>
                    <form method="post" style="display:inline" data-confirm="删除该反馈及其全部回复、点赞？">
                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="act" value="del">
                        <input type="hidden" name="id" value="<?php echo (int)$f['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit"><span class="ic ic-trash"></span>删除</button>
                    </form>
                </div></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="6" style="text-align:center;color:var(--sub);padding:30px">暂无反馈</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php admin_footer(); ?>
