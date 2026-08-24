/* Jay影视 - 前端交互 */
(function () {
    'use strict';

    var CSRF = (document.querySelector('meta[name="csrf"]') || {}).content || '';

    /* ---------- 工具 ---------- */
    function $(sel, root) { return (root || document).querySelector(sel); }
    function $all(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

    function toast(msg, type) {
        type = type || 'info';
        var wrap = $('.toast-wrap');
        if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-wrap'; document.body.appendChild(wrap); }
        var icons = { ok: 'ic-check', err: 'ic-close', info: 'ic-megaphone' };
        var t = document.createElement('div');
        t.className = 'toast ' + type;
        t.innerHTML = '<span class="ic ' + (icons[type] || icons.info) + '"></span><span></span>';
        t.lastChild.textContent = msg;
        wrap.appendChild(t);
        setTimeout(function () {
            t.classList.add('out');
            setTimeout(function () { t.remove(); }, 320);
        }, 2600);
    }

    function ajax(method, url, data) {
        var opts = {
            method: method,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF': CSRF },
            credentials: 'same-origin'
        };
        if (data) {
            if (data instanceof FormData) { opts.body = data; }
            else {
                opts.headers['Content-Type'] = 'application/x-www-form-urlencoded;charset=UTF-8';
                var parts = [];
                Object.keys(data).forEach(function (k) { parts.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k])); });
                opts.body = parts.join('&');
            }
        }
        return fetch(url, opts).then(function (r) {
            return r.json().catch(function () { return { ok: 0, msg: '响应异常' }; });
        });
    }

    /* ---------- 移动端导航 ---------- */
    var navToggle = $('#navToggle'), mainNav = $('#mainNav');
    if (navToggle && mainNav) {
        navToggle.addEventListener('click', function () {
            var open = mainNav.classList.toggle('open');
            navToggle.classList.toggle('open', open);
        });
    }

    /* ---------- 弹窗通用 ---------- */
    function openModal(id) {
        var m = document.getElementById(id);
        if (m) { m.classList.add('show'); document.body.style.overflow = 'hidden'; }
    }
    function closeModal(id) {
        var m = document.getElementById(id);
        if (m) { m.classList.remove('show'); document.body.style.overflow = ''; }
    }
    document.addEventListener('click', function (e) {
        var opener = e.target.closest('[data-open]');
        if (opener) { openModal(opener.getAttribute('data-open')); }
        var closer = e.target.closest('[data-close]');
        if (closer) { closeModal(closer.getAttribute('data-close')); }
        if (e.target.classList && e.target.classList.contains('modal-mask')) { e.target.classList.remove('show'); document.body.style.overflow = ''; }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') { $all('.modal-mask.show').forEach(function (m) { m.classList.remove('show'); }); document.body.style.overflow = ''; }
    });
    window.JAY = { toast: toast, ajax: ajax, openModal: openModal, closeModal: closeModal };

    /* ---------- 首页公告弹窗（Cookie 版本控制） ---------- */
    var annModal = document.getElementById('annModal');
    if (annModal && window.JAY_ANN_VERSION) {
        setTimeout(function () { openModal('annModal'); }, 600);
        var annOk = document.getElementById('annOk');
        if (annOk) {
            annOk.addEventListener('click', function () {
                var noTip = document.getElementById('annNoTip');
                if (noTip && noTip.checked) {
                    var d = new Date();
                    d.setTime(d.getTime() + 365 * 864e5);
                    document.cookie = 'jay_ann_v=' + encodeURIComponent(window.JAY_ANN_VERSION) + ';path=/;expires=' + d.toGMTString();
                }
                closeModal('annModal');
            });
        }
    }

    /* ---------- 未登录播放拦截提示弹窗（登录页） ---------- */
    var loginNotice = document.getElementById('loginNoticeModal');
    if (loginNotice && loginNotice.getAttribute('data-auto') === '1') {
        setTimeout(function () { openModal('loginNoticeModal'); }, 350);
    }

    /* ---------- 收藏开关 ---------- */
    $all('[data-fav]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var self = this;
            ajax('POST', this.getAttribute('data-fav') || 'api/favorite.php', {
                tmdb: this.getAttribute('data-tmdb'),
                type: this.getAttribute('data-type'),
                title: this.getAttribute('data-title'),
                poster: this.getAttribute('data-poster')
            }).then(function (res) {
                if (res.need_login) { location.href = 'login.php?notice=fav'; return; }
                if (res.ok) {
                    self.classList.toggle('on', !!res.favorited);
                    toast(res.favorited ? '已加入收藏' : '已取消收藏', 'ok');
                    var n = $('.fav-count', self);
                    if (n) n.textContent = res.count;
                } else { toast(res.msg || '操作失败', 'err'); }
            });
        });
    });

    /* ---------- 反馈点赞 ---------- */
    $all('[data-like]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var self = this;
            ajax('POST', 'api/like.php', { fid: this.getAttribute('data-like') }).then(function (res) {
                if (res.need_login) { location.href = 'login.php?notice=fav'; return; }
                if (res.ok) {
                    self.classList.toggle('liked', !!res.liked);
                    var c = $('.like-count', self);
                    if (c) c.textContent = res.count;
                } else { toast(res.msg || '操作失败', 'err'); }
            });
        });
    });

    /* ---------- 回复折叠 / 展开 ---------- */
    $all('.reply-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var box = document.getElementById(this.getAttribute('data-target'));
            if (box) {
                box.classList.add('show');
                this.style.display = 'none';
            }
        });
    });

    /* ---------- 回复提交 ---------- */
    $all('form[data-reply]').forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            var input = form.querySelector('input[name=content]');
            var content = input.value.trim();
            if (!content) { toast('请输入回复内容', 'err'); return; }
            var submitBtn = form.querySelector('button[type=submit]');
            if (submitBtn) submitBtn.disabled = true;
            ajax('POST', 'api/reply.php', {
                fid: form.getAttribute('data-reply'),
                content: content
            }).then(function (res) {
                if (submitBtn) submitBtn.disabled = false;
                if (res.need_login) { location.href = 'login.php?notice=fav'; return; }
                if (res.ok) { toast('回复成功', 'ok'); setTimeout(function () { location.reload(); }, 600); }
                else { toast(res.msg || '回复失败', 'err'); }
            });
        });
    });

    /* ---------- 注册验证码 ---------- */
    var sendCodeBtn = document.getElementById('sendCodeBtn');
    if (sendCodeBtn) {
        sendCodeBtn.addEventListener('click', function () {
            var emailInput = document.getElementById('regEmail');
            var email = emailInput ? emailInput.value.trim() : '';
            if (!/^[^@\s]+@[^@\s]+\.[^@\s]+$/.test(email)) { toast('请先输入正确的邮箱地址', 'err'); emailInput && emailInput.focus(); return; }
            var self = this;
            self.disabled = true;
            self.textContent = '发送中…';
            ajax('POST', 'api/send_code.php', { email: email }).then(function (res) {
                if (res.ok) {
                    toast('验证码已发送，请查收邮箱（含垃圾箱）', 'ok');
                    var left = 60;
                    self.textContent = left + 's后重发';
                    var timer = setInterval(function () {
                        left--;
                        if (left <= 0) { clearInterval(timer); self.disabled = false; self.textContent = '获取验证码'; }
                        else { self.textContent = left + 's后重发'; }
                    }, 1000);
                } else {
                    self.disabled = false;
                    self.textContent = '获取验证码';
                    toast(res.msg || '发送失败', 'err');
                }
            });
        });
    }

    /* ---------- 个人中心：删除收藏 / 删除历史 ---------- */
    $all('[data-delfav]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault(); e.stopPropagation();
            var cell = this.closest('.fav-cell') || this.closest('.mcard');
            ajax('POST', 'api/favorite.php', { tmdb: this.getAttribute('data-delfav'), type: this.getAttribute('data-type'), act: 'del' })
                .then(function (res) {
                    if (res.ok) { toast('已删除收藏', 'ok'); if (cell) cell.remove(); }
                    else { toast(res.msg || '删除失败', 'err'); }
                });
        });
    });
    $all('[data-delhist]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            var row = this.closest('.hist-item');
            ajax('POST', 'api/progress.php', { act: 'del', id: this.getAttribute('data-delhist') })
                .then(function (res) {
                    if (res.ok) { toast('已删除记录', 'ok'); if (row) row.remove(); }
                    else { toast(res.msg || '删除失败', 'err'); }
                });
        });
    });

    /* ---------- 头像预览 ---------- */
    var avatarInput = document.getElementById('avatarInput');
    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            var file = this.files[0];
            var nameSpan = $('#avatarFileName');
            if (file) {
                if (file.size > 2 * 1024 * 1024) { toast('头像不能超过 2MB', 'err'); this.value = ''; if (nameSpan) nameSpan.textContent = '未选择文件'; return; }
                if (nameSpan) nameSpan.textContent = file.name;
                var preview = $('#avatarPreview');
                if (preview) { preview.src = URL.createObjectURL(file); }
            } else if (nameSpan) { nameSpan.textContent = '未选择文件'; }
        });
    }

    /* ---------- 播放页：观看进度计时 ---------- */
    var playTimerEl = document.getElementById('playPage');
    if (playTimerEl) {
        var startPos = parseInt(playTimerEl.getAttribute('data-start') || '0', 10);
        var duration = parseInt(playTimerEl.getAttribute('data-duration') || '0', 10);
        var tmdb = playTimerEl.getAttribute('data-tmdb');
        var mtype = playTimerEl.getAttribute('data-type');
        var title = playTimerEl.getAttribute('data-title');
        var poster = playTimerEl.getAttribute('data-poster');
        var episode = playTimerEl.getAttribute('data-episode');
        var watched = startPos;
        var lastSave = 0;
        var visible = !document.hidden;

        document.addEventListener('visibilitychange', function () { visible = !document.hidden; });

        function saveProgress(sync) {
            var data = {
                act: 'save', tmdb: tmdb, type: mtype, title: title, poster: poster,
                episode: episode, position: Math.round(watched), duration: Math.round(duration)
            };
            if (sync && navigator.sendBeacon) {
                var fd = new FormData();
                fd.append('csrf', CSRF);
                Object.keys(data).forEach(function (k) { fd.append(k, data[k]); });
                navigator.sendBeacon('api/progress.php', fd);
            } else {
                ajax('POST', 'api/progress.php', data);
            }
            var bar = $('#playProgressBar');
            if (bar && duration > 0) bar.style.width = Math.min(100, watched / duration * 100).toFixed(1) + '%';
            var timeEl = $('#playProgressText');
            if (timeEl) timeEl.textContent = fmtTime(watched) + (duration > 0 ? ' / ' + fmtTime(duration) : '');
        }

        function fmtTime(sec) {
            sec = Math.max(0, Math.round(sec));
            var h = Math.floor(sec / 3600), m = Math.floor((sec % 3600) / 60), s = sec % 60;
            return (h > 0 ? h + ':' : '') + (m < 10 && h > 0 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
        }

        playTimerEl.setAttribute('data-timer', '1');
        setInterval(function () {
            if (visible) {
                watched += 1;
                if (watched - lastSave >= 15) { lastSave = watched; saveProgress(false); }
            }
        }, 1000);
        window.addEventListener('beforeunload', function () { saveProgress(true); });
        window.addEventListener('pagehide', function () { saveProgress(true); });
        setTimeout(function () { saveProgress(false); }, 4000);
    }

    /* ---------- 表单确认 ---------- */
    document.addEventListener('submit', function (e) {
        var f = e.target;
        if (f.getAttribute('data-confirm') && !window.confirm(f.getAttribute('data-confirm'))) { e.preventDefault(); }
    });
    $all('[data-confirm-btn]').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            if (!window.confirm(this.getAttribute('data-confirm-btn'))) { e.preventDefault(); }
        });
    });
})();
