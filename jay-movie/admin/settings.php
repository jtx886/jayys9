<?php
/**
 * 管理后台 - 网站设置
 * 自定义全站主题颜色（CSS 变量全站注入）、站点名称、TMDB API Key
 */
require_once __DIR__ . '/includes.php';
$admin = require_admin();

$msg = '';
$presets = array('#e50914', '#ff5722', '#f59e0b', '#10b981', '#06b6d4', '#3b82f6', '#6366f1', '#8b5cf6', '#ec4899');

if (is_post()) {
    csrf_guard();
    $color = strtoupper(post('theme_color'));
    if (!preg_match('/^#[0-9A-F]{6}$/', $color)) $color = '#e50914';
    $siteName = mb_cut(post('site_name'), 30);
    $tmdbKey = trim(post('tmdb_key'));

    set_setting('theme_color', $color);
    if ($siteName !== '') set_setting('site_name', $siteName);
    set_setting('tmdb_key', $tmdbKey);
    $msg = '设置已保存：主题色已全站生效';
}

$curColor = theme_color();
$curName = site_name();
$curKey = get_setting('tmdb_key', TMDB_API_KEY);

admin_header(array('title' => '网站设置', 'active' => 'settings'));
?>
<?php if ($msg): ?><div class="auth-alert ok" style="margin-bottom:18px"><?php echo e($msg); ?></div><?php endif; ?>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-crown"></span>全站主题设置</h2></div>
    <div class="panel-body">
        <form method="post">
            <input type="hidden" name="csrf" value="<?php echo e(csrf_token()); ?>">

            <div class="form-item">
                <label class="form-label">全站主题颜色（按钮 / 高亮 / 徽标 / 渐变等全站元素即时换色）</label>
                <div class="color-input-wrap">
                    <input type="color" name="theme_color" id="themeColor" value="<?php echo e($curColor); ?>">
                    <input class="form-input" type="text" id="themeColorText" value="<?php echo e($curColor); ?>" style="width:120px" maxlength="7">
                    <span style="width:70px;height:34px;border-radius:10px;display:inline-block;background:linear-gradient(135deg,<?php echo e($curColor); ?>,<?php echo e(shade_hex($curColor, -35)); ?>);border:1px solid var(--border-h)" id="colorPreview"></span>
                </div>
                <div class="color-presets">
                    <?php foreach ($presets as $p): ?>
                    <button class="color-preset<?php echo strtoupper($p) === $curColor ? ' sel' : ''; ?>" type="button"
                        style="background:<?php echo $p; ?>" data-color="<?php echo $p; ?>" title="<?php echo $p; ?>"></button>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-grid">
                <div class="form-item"><label class="form-label">站点名称</label>
                    <input class="form-input" type="text" name="site_name" value="<?php echo e($curName); ?>" required></div>
                <div class="form-item"><label class="form-label">TMDB API Key（影视元数据来源）</label>
                    <input class="form-input" type="text" name="tmdb_key" value="<?php echo e($curKey); ?>" placeholder="themoviedb.org 申请的 v3 Key">
                    <p class="form-hint">留空则使用 config.php 中的默认 Key</p></div>
            </div>

            <button class="btn btn-accent btn-lg" type="submit"><span class="ic ic-check"></span>保存设置</button>
        </form>
    </div>
</div>

<div class="panel fade-up">
    <div class="panel-head"><h2><span class="ic ic-eye"></span>主题预览</h2></div>
    <div class="panel-body" style="display:flex;gap:14px;flex-wrap:wrap;align-items:center">
        <button class="btn btn-accent" type="button">主要按钮</button>
        <button class="btn btn-ghost" type="button">次要按钮</button>
        <span class="badge badge-green">正常</span>
        <span class="badge-dev">开发者</span>
        <span class="tab-btn active">选项卡</span>
        <span style="color:var(--gold);display:inline-flex;align-items:center;gap:5px"><span class="ic ic-star"></span>8.7</span>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var colorInput = document.getElementById('themeColor');
    var textInput = document.getElementById('themeColorText');
    var preview = document.getElementById('colorPreview');
    function sync(v) {
        colorInput.value = v; textInput.value = v.toUpperCase();
        preview.style.background = 'linear-gradient(135deg,' + v + ',' + v + ')';
        document.querySelectorAll('.color-preset').forEach(function (b) {
            b.classList.toggle('sel', b.getAttribute('data-color').toUpperCase() === v.toUpperCase());
        });
    }
    colorInput.addEventListener('input', function () { sync(this.value); });
    textInput.addEventListener('input', function () {
        var v = this.value.trim();
        if (/^#[0-9a-fA-F]{6}$/.test(v)) sync(v);
    });
    document.querySelectorAll('.color-preset').forEach(function (b) {
        b.addEventListener('click', function () { sync(this.getAttribute('data-color')); });
    });
});
</script>
<?php admin_footer(); ?>
