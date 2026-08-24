<?php
/**
 * Jay影视 - 个人中心
 * 我的收藏（可删除） / 观看历史（播放秒记录，可删除） / 账号设置（头像上传、修改密码）
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/layout.php';

$user = require_login_page('fav');
$uid = (int)$user['id'];
$tab = get_param('tab', 'fav');
if (!in_array($tab, array('fav', 'hist', 'set'), true)) $tab = 'fav';

$msg = ''; $msgType = 'ok';

/* ---------- 头像上传 ---------- */
if (is_post() && post('act') === 'avatar') {
    csrf_guard();
    if (!isset($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
        $msg = '请选择要上传的头像图片'; $msgType = 'err';
    } elseif ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
        $msg = '头像大小不能超过 2MB'; $msgType = 'err';
    } else {
        $info = @getimagesize($_FILES['avatar']['tmp_name']);
        $allow = array(IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_GIF => 'gif', IMAGETYPE_WEBP => 'webp');
        if (!$info || !isset($allow[$info[2]])) {
            $msg = '仅支持 JPG / PNG / GIF / WEBP 图片格式'; $msgType = 'err';
        } else {
            $dir = UPLOAD_DIR . '/avatars';
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            $name = 'u' . $uid . '_' . time() . '.' . $allow[$info[2]];
            if (move_uploaded_file($_FILES['avatar']['tmp_name'], $dir . '/' . $name)) {
                /* 删除旧头像 */
                if (!empty($user['avatar'])) {
                    $old = APP_ROOT . '/' . ltrim($user['avatar'], '/');
                    if (strpos($old, realpath($dir)) === 0 && file_exists($old)) @unlink($old);
                }
                DB::exec("UPDATE users SET avatar = ? WHERE id = ?", array('uploads/avatars/' . $name, $uid));
                redirect('profile.php?tab=set&saved=avatar');
            }
            $msg = '头像上传失败，请重试'; $msgType = 'err';
        }
    }
}

/* ---------- 修改密码 ---------- */
if (is_post() && post('act') === 'passwd') {
    csrf_guard();
    $oldp = post('old_password'); $newp = post('new_password'); $newp2 = post('new_password2');
    if (!password_verify($oldp, $user['password'])) {
        $msg = '原密码错误'; $msgType = 'err';
    } elseif (strlen($newp) < 6 || strlen($newp) > 32) {
        $msg = '新密码长度需为 6-32 位'; $msgType = 'err';
    } elseif ($newp !== $newp2) {
        $msg = '两次输入的新密码不一致'; $msgType = 'err';
    } else {
        DB::exec("UPDATE users SET password = ? WHERE id = ?", array(password_hash($newp, PASSWORD_DEFAULT), $uid));
        redirect('profile.php?tab=set&saved=pwd');
    }
}

/* ---------- 数据 ---------- */
$favs = DB::all("SELECT * FROM favorites WHERE user_id = ? ORDER BY id DESC LIMIT 60", array($uid));
$history = DB::all("SELECT * FROM watch_history WHERE user_id = ? ORDER BY updated_at DESC LIMIT 60", array($uid));

if (get_param('saved') === 'avatar') { $msg = '头像更新成功'; }
if (get_param('saved') === 'pwd') { $msg = '密码修改成功'; }

render_header(array('title' => '个人中心'));
?>
<div class="container">

    <div class="profile-head fade-up">
        <div class="profile-avatar-wrap">
            <?php if (!empty($user['avatar']) && file_exists(APP_ROOT . '/' . ltrim($user['avatar'], '/'))): ?>
            <img class="avatar" src="<?php echo e($user['avatar']); ?>" alt="">
            <?php else: ?>
            <span class="letter-avatar"><?php echo e(letter_avatar($user['username'])); ?></span>
            <?php endif; ?>
            <label class="avatar-edit" for="avatarInput" title="上传头像"><span class="ic ic-upload"></span></label>
        </div>
        <div class="profile-info">
            <div class="profile-name"><?php echo e($user['username']); ?><?php echo (int)$user['is_admin'] === 1 ? '<span class="badge-dev">开发者</span>' : ''; ?>
                <?php if (user_banned($user)): ?><span class="badge badge-red">已封禁</span><?php endif; ?>
            </div>
            <div class="profile-meta">
                <span><span class="ic ic-mail"></span> <?php echo e($user['email']); ?></span>
                <span><span class="ic ic-cal"></span> 注册于 <?php echo dt($user['created_at']); ?></span>
                <span><span class="ic ic-heart"></span> 收藏 <?php echo count($favs); ?></span>
                <span><span class="ic ic-clock"></span> 历史 <?php echo count($history); ?></span>
            </div>
        </div>
    </div>

    <div class="section-tabs fade-up">
        <a class="tab-btn<?php echo $tab === 'fav' ? ' active' : ''; ?>" href="profile.php?tab=fav">我的收藏</a>
        <a class="tab-btn<?php echo $tab === 'hist' ? ' active' : ''; ?>" href="profile.php?tab=hist">观看历史</a>
        <a class="tab-btn<?php echo $tab === 'set' ? ' active' : ''; ?>" href="profile.php?tab=set">账号设置</a>
    </div>

    <?php if ($msg): ?>
    <div class="auth-alert <?php echo $msgType; ?>" style="margin-bottom:18px"><?php echo e($msg); ?></div>
    <?php endif; ?>

    <?php if ($tab === 'fav'): ?>
    <section class="fade-up">
        <?php if ($favs): ?>
        <div class="fav-grid">
            <?php foreach ($favs as $f): ?>
            <div class="fav-cell">
                <button class="fav-del" data-delfav="<?php echo (int)$f['tmdb_id']; ?>" data-type="<?php echo e($f['media_type']); ?>" title="删除收藏" type="button"><span class="ic ic-trash"></span></button>
                <a class="mcard" href="detail.php?id=<?php echo (int)$f['tmdb_id']; ?>&type=<?php echo e($f['media_type']); ?>">
                    <div class="mcard-poster">
                        <?php if (!empty($f['poster'])): ?>
                        <img loading="lazy" src="<?php echo e($f['poster']); ?>" alt="<?php echo e($f['title']); ?>">
                        <?php else: ?>
                        <div class="poster-empty"><span class="ic ic-film"></span></div>
                        <?php endif; ?>
                        <span class="mcard-type"><?php echo $f['media_type'] === 'movie' ? '电影' : '剧集'; ?></span>
                    </div>
                    <div class="mcard-title"><?php echo e($f['title']); ?></div>
                    <div class="mcard-sub"><?php echo dt($f['created_at']); ?> 收藏</div>
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <?php echo empty_state('暂无收藏，去首页发现喜欢的影视吧', 'ic-heart'); ?>
        <?php endif; ?>
    </section>

    <?php elseif ($tab === 'hist'): ?>
    <section class="fade-up">
        <?php if ($history): ?>
        <?php foreach ($history as $h): ?>
        <div class="hist-item">
            <?php if (!empty($h['poster'])): ?>
            <img class="hist-poster" src="<?php echo e($h['poster']); ?>" alt="">
            <?php else: ?>
            <div class="hist-poster"></div>
            <?php endif; ?>
            <div class="hist-main">
                <a class="hist-title" href="detail.php?id=<?php echo (int)$h['tmdb_id']; ?>&type=<?php echo e($h['media_type']); ?>"><?php echo e($h['title']); ?></a>
                <div class="hist-sub">
                    <span><span class="ic ic-film"></span> <?php echo e($h['episode'] !== '' ? $h['episode'] : '正片'); ?></span>
                    <span><span class="ic ic-clock"></span> <?php echo dt($h['updated_at']); ?></span>
                </div>
                <div class="hist-progress">
                    <?php if ((int)$h['duration'] > 0): ?>
                    <div class="progress-bar"><i style="width:<?php echo min(100, round($h['position'] / $h['duration'] * 100)); ?>%"></i></div>
                    <span><?php echo fmt_duration($h['position']); ?> / <?php echo fmt_duration($h['duration']); ?></span>
                    <?php else: ?>
                    <span>已观看 <?php echo fmt_duration($h['position']); ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <a class="btn btn-accent btn-sm" href="play.php?id=<?php echo (int)$h['tmdb_id']; ?>&type=<?php echo e($h['media_type']); ?>"><span class="ic ic-play"></span>继续观看</a>
            <button class="icon-btn" data-delhist="<?php echo (int)$h['id']; ?>" title="删除记录" type="button"><span class="ic ic-trash"></span></button>
        </div>
        <?php endforeach; ?>
        <?php else: ?>
        <?php echo empty_state('暂无观看历史，快去看一部喜欢的影视吧', 'ic-clock'); ?>
        <?php endif; ?>
    </section>

    <?php else: ?>
    <section class="fade-up" style="max-width:560px">
        <div class="fb-card">
            <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:8px"><span class="ic ic-user" style="color:var(--accent-h)"></span>上传自定义头像</h3>
            <form method="post" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="act" value="avatar">
                <div style="display:flex;gap:18px;align-items:center;margin-bottom:16px">
                    <?php if (!empty($user['avatar']) && file_exists(APP_ROOT . '/' . ltrim($user['avatar'], '/'))): ?>
                    <img id="avatarPreview" class="avatar" style="width:76px;height:76px" src="<?php echo e($user['avatar']); ?>" alt="">
                    <?php else: ?>
                    <span id="avatarPreviewWrap" class="letter-avatar" style="width:76px;height:76px;font-size:26px"><?php echo e(letter_avatar($user['username'])); ?></span>
                    <img id="avatarPreview" class="avatar" style="width:76px;height:76px;display:none" alt="">
                    <?php endif; ?>
                    <div style="flex:1">
                        <div class="input-group">
                            <input class="form-input" type="file" id="avatarInput" name="avatar" accept="image/jpeg,image/png,image/gif,image/webp" style="padding:8px">
                            <button class="btn btn-accent" type="submit"><span class="ic ic-upload"></span>上传</button>
                        </div>
                        <p class="form-hint" id="avatarFileName">支持 JPG/PNG/GIF/WEBP，不超过 2MB</p>
                    </div>
                </div>
            </form>
        </div>

        <div class="fb-card">
            <h3 style="font-size:16px;font-weight:800;margin-bottom:16px;display:flex;align-items:center;gap:8px"><span class="ic ic-shield" style="color:var(--accent-h)"></span>修改密码</h3>
            <form method="post">
                <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">
                <input type="hidden" name="act" value="passwd">
                <div class="form-item">
                    <label class="form-label">原密码</label>
                    <input class="form-input" type="password" name="old_password" required>
                </div>
                <div class="form-item">
                    <label class="form-label">新密码</label>
                    <input class="form-input" type="password" name="new_password" placeholder="6-32位" required>
                </div>
                <div class="form-item">
                    <label class="form-label">确认新密码</label>
                    <input class="form-input" type="password" name="new_password2" required>
                </div>
                <button class="btn btn-accent" type="submit"><span class="ic ic-check"></span>保存新密码</button>
            </form>
        </div>
    </section>
    <?php endif; ?>
</div>
<script>
/* 头像即时预览（含原图模式） */
document.addEventListener('DOMContentLoaded', function () {
    var input = document.getElementById('avatarInput');
    if (!input) return;
    input.addEventListener('change', function () {
        var f = this.files && this.files[0];
        if (!f) return;
        var url = URL.createObjectURL(f);
        var img = document.getElementById('avatarPreview');
        var wrap = document.getElementById('avatarPreviewWrap');
        if (img) { img.src = url; img.style.display = ''; }
        if (wrap) wrap.style.display = 'none';
    });
});
</script>
<?php render_footer(); ?>
