<?php
/**
 * Jay影视 - 用户认证 / 会话 / 封禁逻辑 / CSRF
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

/* ---------- CSRF ---------- */
function csrf_token()
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(function_exists('random_bytes') ? random_bytes(16) : md5(uniqid('', true)));
    }
    return $_SESSION['csrf'];
}

function check_csrf($token)
{
    return !empty($_SESSION['csrf']) && is_string($token) && hash_equals($_SESSION['csrf'], $token);
}

/** POST 请求统一 CSRF 校验（表单隐藏域 csrf 或请求头 X-CSRF） */
function csrf_guard()
{
    if (!is_post()) return;
    $token = isset($_POST['csrf']) ? $_POST['csrf'] : (isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '');
    if (!check_csrf($token)) {
        if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) || (isset($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false)) {
            json_out(array('ok' => 0, 'msg' => '令牌失效，请刷新页面重试'), 403);
        }
        die('令牌校验失败，请返回刷新页面后重试');
    }
}

/* ---------- 会话用户 ---------- */
/**
 * 当前登录用户（数组）或 null
 * 自动处理到期解封：ban_end 已过自动恢复正常
 */
function current_user()
{
    static $user = false;
    if ($user !== false) return $user;

    $user = null;
    if (!empty($_SESSION['uid'])) {
        $u = DB::row("SELECT * FROM users WHERE id = ?", array((int)$_SESSION['uid']));
        if ($u) {
            if ((int)$u['status'] === 0) {
                $banEnd   = $u['ban_end'] ? strtotime($u['ban_end']) : 0;
                $banStart = $u['ban_start'] ? strtotime($u['ban_start']) : 0;
                $now      = time();
                if ($banEnd && $banEnd <= $now) {
                    // 封禁到期，自动解除
                    DB::exec("UPDATE users SET status = 1, ban_reason = NULL, ban_start = NULL, ban_end = NULL WHERE id = ?", array((int)$u['id']));
                    $u['status'] = 1; $u['ban_reason'] = null; $u['ban_start'] = null; $u['ban_end'] = null;
                } elseif ($banStart && $banStart > $now) {
                    // 预约封禁尚未生效
                } else {
                    $u['banned'] = true;
                }
            }
            $user = $u;
        }
    }
    return $user;
}

function is_logged_in() { return current_user() !== null; }

/** 用户当前是否处于封禁状态 */
function user_banned($user)
{
    if (!$user || (int)$user['status'] !== 0) return false;
    $now = time();
    $banStart = $user['ban_start'] ? strtotime($user['ban_start']) : 0;
    $banEnd   = $user['ban_end'] ? strtotime($user['ban_end']) : 0;
    if ($banStart && $banStart > $now) return false;   // 未到开始时间
    if ($banEnd && $banEnd <= $now) return false;      // 已到期（current_user 已自动解封）
    return true;
}

function require_login_json()
{
    $u = current_user();
    if (!$u) json_out(array('ok' => 0, 'need_login' => 1, 'msg' => '请先登录'), 401);
    if (user_banned($u)) json_out(array('ok' => 0, 'msg' => '账号处于封禁状态，无法操作'), 403);
    return $u;
}

/** 页面级：未登录跳转登录页（带弹窗提示类型） */
function require_login_page($notice = '')
{
    $u = current_user();
    if (!$u) {
        $url = 'login.php' . ($notice ? '?notice=' . urlencode($notice) : '');
        if (!empty($_SERVER['REQUEST_URI'])) {
            $_SESSION['login_back'] = strtok($_SERVER['REQUEST_URI'], '?') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        }
        redirect($url);
    }
    if (user_banned($u)) {
        return $u; // 由页面自行展示封禁提示
    }
    return $u;
}

function login_user($userId)
{
    $_SESSION['uid'] = (int)$userId;
    session_regenerate_id(true);
}

function logout_user()
{
    unset($_SESSION['uid']);
}
