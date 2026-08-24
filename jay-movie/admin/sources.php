<?php
/**
 * 管理后台 - 播放源管理
 * 新增 / 编辑 / 删除播放源，设置默认播放源（接口需为苹果CMS JSON 格式）
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

$msg = ''; $msgType = 'ok';

if (is_post()) {
    csrf_guard();
    $act = post('act');

    if ($act === 'add') {
        $name = mb_cut(post('name'), 50);
        $api  = trim(post('api_url'));
        if ($name === '' || !preg_match('#^https?://#i', $api)) {
            $msg = '请填写名称和以 http(s):// 开头的接口地址'; $msgType = 'err';
        } else {
            DB::insert("INSERT INTO play_sources (name, api_url, is_default, sort, status, created_at) VALUES (?,?,0,0,1,?)",
                array($name, $api, now_str()));
            $msg = '播放源「' . $name . '」添加成功';
        }
    }

    if ($act === 'edit') {
        $id = (int)post('id');
        $name = mb_cut(post('name'), 50);
        $api  = trim(post('api_url'));
        $sort = (int)post('sort');
        $status = post('status') === '1' ? 1 : 0;
        if ($name === '' || !preg_match('#^https?://#i', $api)) {
            $msg = '名称与接口地址不能为空'; $msgType = 'err';
        } else {
            DB::exec("UPDATE play_sources SET name = ?, api_url = ?, sort = ?, status = ? WHERE id = ?", array($name, $api, $sort, $status, $id));
            $msg = '播放源已更新';
        }
    }

    if ($act === 'del') {
        $id = (int)post('id');
        $src = DB::row("SELECT * FROM play_sources WHERE id = ?", array($id));
        if ($src) {
            if ((int)$src['is_default'] === 1) {
                $msg = '默认播放源不可删除，请先将其他源设为默认'; $msgType = 'err';
            } else {
                DB::exec("DELETE FROM play_sources WHERE id = ?", array($id));
                $msg = '播放源已删除';
            }
        }
    }

    if ($act === 'default') {
        $id = (int)post('id');
        if (DB::one("SELECT id FROM play_sources WHERE id = ? AND status = 1", array($id))) {
            DB::exec("UPDATE play_sources SET is_default = 0");
            DB::exec("UPDATE play_sources SET is_default = 1 WHERE id = ?", array($id));
            $msg = '默认播放源已切换';
        } else {
            $msg = '播放源不存在或已停用'; $msgType = 'err';
        }
    }

    /* 清理播放源搜索缓存 */
    DB::exec("DELETE FROM media_cache WHERE cache_key LIKE 'srcsearch:%'");
}

$sources = DB::all("SELECT * FROM play_sources ORDER BY is_default DESC, sort ASC, id ASC");
$defaultId = 0;
foreach ($sources as $s) { if ((int)$s['is_default'] === 1) { $defaultId = (int)$s['id']; break; } }

admin_header(array('title' => '播放源管理', 'active' => 'sources'));
?>
<?php if ($msg): ?><div class="auth-alert <?php echo $msgType; ?>" style="margin-bottom:18px"><?php echo e($msg); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-film"></span>新增播放源</h2></div>
    <div class="panel-body">
        <form method="post" class="form-grid">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
            <input type="hidden" name="act" value="add">
            <div class="form-item"><label class="form-label">播放源名称</label>
                <input class="form-input" type="text" name="name" placeholder="例如：非凡影视" required></div>
            <div class="form-item"><label class="form-label">接口地址（苹果CMS JSON 格式，支持 ac=detail&wd= 搜索）</label>
                <input class="form-input" type="text" name="api_url" placeholder="https://api.example.com/inc/apijson.php" required></div>
            <div class="span2"><button class="btn btn-accent" type="submit"><span class="ic ic-plus"></span>添加播放源</button></div>
        </form>
    </div>
</div>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-list"></span>源列表（默认源用于全站播放资源匹配）</h2></div>
    <div class="panel-body p0"><div class="adata-wrap">
        <table class="adata">
            <thead><tr><th>名称</th><th>接口地址</th><th>排序</th><th>状态</th><th>默认</th><th>操作</th></tr></thead>
            <tbody>
            <?php foreach ($sources as $s): ?>
            <tr>
                <td><b><?php echo e($s['name']); ?></b></td>
                <td style="color:var(--sub);max-width:340px;word-break:break-all;font-size:12.5px"><?php echo e($s['api_url']); ?></td>
                <td><?php echo (int)$s['sort']; ?></td>
                <td><?php echo (int)$s['status'] === 1 ? '<span class="badge badge-green">启用</span>' : '<span class="badge badge-gray">停用</span>'; ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                        <input type="hidden" name="act" value="default">
                        <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                        <button class="btn btn-sm <?php echo (int)$s['is_default'] === 1 ? 'btn-accent' : 'btn-ghost'; ?>" type="submit" <?php echo (int)$s['is_default'] === 1 ? 'disabled' : ''; ?>>
                            <?php echo (int)$s['is_default'] === 1 ? '默认源' : '设为默认'; ?>
                        </button>
                    </form>
                </td>
                <td>
                    <div class="atable-actions">
                        <button class="btn btn-ghost btn-sm" type="button" data-open="srcModal<?php echo (int)$s['id']; ?>"><span class="ic ic-edit"></span>编辑</button>
                        <?php if ((int)$s['is_default'] !== 1): ?>
                        <form method="post" style="display:inline" data-confirm="确定删除该播放源吗？">
                            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                            <input type="hidden" name="act" value="del">
                            <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                            <button class="btn btn-danger btn-sm" type="submit"><span class="ic ic-trash"></span>删除</button>
                        </form>
                        <?php endif; ?>
                    </div>

                    <div class="modal-mask" id="srcModal<?php echo (int)$s['id']; ?>">
                        <div class="modal">
                            <div class="modal-head">
                                <h3><span class="ic ic-edit"></span>编辑播放源</h3>
                                <button class="modal-close" data-close="srcModal<?php echo (int)$s['id']; ?>" type="button"><span class="ic ic-close"></span></button>
                            </div>
                            <form method="post">
                                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                                <input type="hidden" name="act" value="edit">
                                <input type="hidden" name="id" value="<?php echo (int)$s['id']; ?>">
                                <div class="modal-body">
                                    <div class="form-item"><label class="form-label">名称</label>
                                        <input class="form-input" type="text" name="name" value="<?php echo e($s['name']); ?>" required></div>
                                    <div class="form-item"><label class="form-label">接口地址</label>
                                        <input class="form-input" type="text" name="api_url" value="<?php echo e($s['api_url']); ?>" required></div>
                                    <div class="form-item"><label class="form-label">排序（越小越靠前）</label>
                                        <input class="form-input" type="number" name="sort" value="<?php echo (int)$s['sort']; ?>"></div>
                                    <div class="form-item"><label class="form-label">状态</label>
                                        <select class="form-select" name="status">
                                            <option value="1"<?php echo (int)$s['status'] === 1 ? ' selected' : ''; ?>>启用</option>
                                            <option value="0"<?php echo (int)$s['status'] === 0 ? ' selected' : ''; ?>>停用</option>
                                        </select></div>
                                </div>
                                <div class="modal-foot">
                                    <button class="btn btn-ghost" type="button" data-close="srcModal<?php echo (int)$s['id']; ?>">取消</button>
                                    <button class="btn btn-accent" type="submit">保存修改</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div></div>
</div>
<?php admin_footer(); ?>
