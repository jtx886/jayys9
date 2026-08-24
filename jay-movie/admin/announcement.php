<?php
/**
 * 管理后台 - 网站公告
 * 自定义弹窗公告；公告仅首页弹窗显示；勾选“不再提示”存 Cookie；
 * 发布新公告后版本号变更，旧 Cookie 失效，首页重新弹窗
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

$msg = ''; $msgType = 'ok';

if (is_post()) {
    csrf_guard();
    $act = post('act');

    if ($act === 'publish') {
        $title = mb_cut(post('title'), 100);
        $content = trim(post('content'));
        $active = post('is_active') === '1' ? 1 : 0;
        if ($title === '' || $content === '') {
            $msg = '公告标题和内容不能为空'; $msgType = 'err';
        } else {
            DB::exec("UPDATE announcements SET is_active = 0 WHERE is_active = 1");
            DB::insert("INSERT INTO announcements (title, content, is_active, created_at) VALUES (?,?,?,?)",
                array($title, $content, $active, now_str()));
            if ($active) {
                /* 版本号更新 -> 用户端 Cookie 失效 -> 首页重新弹窗 */
                set_setting('ann_version', date('YmdHis') . mt_rand(100, 999));
            }
            $msg = $active ? '公告已发布，全站用户访问首页时将重新看到弹窗' : '公告已保存（未启用）';
        }
    }

    if ($act === 'activate') {
        $id = (int)post('id');
        if (DB::one("SELECT id FROM announcements WHERE id = ?", array($id))) {
            DB::exec("UPDATE announcements SET is_active = 0");
            DB::exec("UPDATE announcements SET is_active = 1 WHERE id = ?", array($id));
            set_setting('ann_version', date('YmdHis') . mt_rand(100, 999));
            $msg = '公告已启用，首页将重新弹窗展示';
        }
    }

    if ($act === 'deactivate') {
        DB::exec("UPDATE announcements SET is_active = 0");
        $msg = '已停用公告，首页不再弹窗';
    }

    if ($act === 'del') {
        $id = (int)post('id');
        DB::exec("DELETE FROM announcements WHERE id = ?", array($id));
        $msg = '公告已删除';
    }
}

$activeAnn = DB::row("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC LIMIT 1");
$list = DB::all("SELECT * FROM announcements ORDER BY id DESC LIMIT 30");
$annVersion = get_setting('ann_version', '');

admin_header(array('title' => '网站公告', 'active' => 'announcement'));
?>
<?php if ($msg): ?><div class="auth-alert <?php echo $msgType; ?>" style="margin-bottom:18px"><?php echo e($msg); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-megaphone"></span>发布新公告</h2>
        <span class="form-hint">当前公告版本：<?php echo e($annVersion); ?>（每次发布自动更新，旧 Cookie 立即失效）</span>
    </div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="act" value="publish">
            <div class="form-item"><label class="form-label">公告标题</label>
                <input class="form-input" type="text" name="title" placeholder="例如：站点升级维护通知" required></div>
            <div class="form-item"><label class="form-label">公告内容（仅首页弹窗展示，其他页面不显示；用户可勾选“不再提示”存入Cookie，新公告发布后失效重新弹出）</label>
                <textarea class="form-textarea" name="content" style="min-height:140px" placeholder="亲爱的用户：……" required></textarea></div>
            <div class="form-item" style="display:flex;align-items:center;gap:18px;flex-wrap:wrap">
                <label class="ann-check"><input type="checkbox" name="is_active" value="1" checked><i></i>立即启用（首页弹窗展示）</label>
                <button class="btn btn-accent" type="submit"><span class="ic ic-send"></span>发布公告</button>
            </div>
        </form>
    </div>
</div>

<?php if ($activeAnn): ?>
<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-bell"></span>当前生效公告</h2>
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="act" value="deactivate">
            <button class="btn btn-ghost btn-sm" type="submit">停用公告</button>
        </form>
    </div>
    <div class="panel-body">
        <h3 style="font-size:15px;margin-bottom:10px"><?php echo e($activeAnn['title']); ?></h3>
        <div class="ann-preview"><?php echo nl2br(e($activeAnn['content'])); ?></div>
    </div>
</div>
<?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-list"></span>公告历史</h2></div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>标题</th><th>内容摘要</th><th>状态</th><th>发布时间</th><th>操作</th></tr></thead>
            <tbody>
            <?php if ($list): foreach ($list as $a): ?>
            <tr>
                <td><?php echo e(mb_cut($a['title'], 24)); ?></td>
                <td style="color:var(--sub);max-width:280px"><?php echo e(mb_cut($a['content'], 40)); ?></td>
                <td><?php echo (int)$a['is_active'] === 1 ? '<span class="badge badge-green">生效中</span>' : '<span class="badge badge-gray">未启用</span>'; ?></td>
                <td style="color:var(--sub)"><?php echo dt($a['created_at']); ?></td>
                <td><div class="atable-actions">
                    <?php if ((int)$a['is_active'] !== 1): ?>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="act" value="activate">
                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                        <button class="btn btn-ghost btn-sm" type="submit">启用</button>
                    </form>
                    <?php endif; ?>
                    <form method="post" style="display:inline" data-confirm="确定删除该公告吗？">
                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="act" value="del">
                        <input type="hidden" name="id" value="<?php echo (int)$a['id']; ?>">
                        <button class="btn btn-danger btn-sm" type="submit"><span class="ic ic-trash"></span>删除</button>
                    </form>
                </div></td>
            </tr>
            <?php endforeach; else: ?>
            <tr><td colspan="5" style="text-align:center;color:var(--sub);padding:30px">暂无公告</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php admin_footer(); ?>
