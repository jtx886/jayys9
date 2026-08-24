<?php
/**
 * Jay影视 - 全站配置文件
 * 适配 InfinityFree 免费主机 / PHP 7.4 - 8.x / MySQL
 *
 * ===== InfinityFree 部署说明 =====
 * 1. 将本项目整体上传到 htdocs 目录（可放子目录）
 * 2. InfinityFree 控制台 -> MySQL Databases 创建数据库后，
 *    用面板给出的信息替换下方 DB_* 常量，例如：
 *    DB_HOST = 'sqlXXX.infinityfree.com'  （XXX为你的编号）
 *    DB_NAME = 'if0_12345678_jaymovie'
 *    DB_USER = 'if0_12345678'
 *    DB_PASS = '你的数据库密码'
 * 3. 访问 /install.php 完成安装（自动建表 + 初始化默认数据）
 * 4. 安装完成后强烈建议删除 install.php
 */

/* ================= 数据库配置 ================= */
define('DB_HOST', '127.0.0.1');      // InfinityFree: sqlXXX.infinityfree.com
define('DB_NAME', 'jay_movie');      // InfinityFree: if0_12345678_xxx
define('DB_USER', 'jay');            // InfinityFree: if0_12345678
define('DB_PASS', 'jay123456');
define('DB_PORT', 3306);
define('DB_PREFIX', '');             // InfinityFree 无需前缀，如主机强制前缀可在此配置

/* ================= SMTP 邮件配置（固定） ================= */
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

/* ================= TMDB 元数据 API ================= */
/* 默认 Key（可在后台【网站设置】中覆盖修改） */
define('TMDB_API_KEY', '3fd2be6f0c70a2a598f084ddfb75487c');
define('TMDB_LANG', 'zh-CN');
define('TMDB_IMG', 'https://image.tmdb.org/t/p/');

/* ================= 播放器与播放源 ================= */
/* 解析播放器外壳：真实 m3u8 直链 urlencode 后拼接在其 url= 参数后 */
define('PLAYER_SHELL', 'https://svip.ffzyplay.com/?url=');
/* 默认播放源（安装时写入数据库，可在后台播放源管理中维护） */
define('DEFAULT_SOURCE_NAME', '非凡影视');
define('DEFAULT_SOURCE_API', 'https://api.yyzy-tv.vip/inc/apijson.php');

/* ================= 站点常量 ================= */
define('APP_NAME', 'Jay影视');
define('APP_VERSION', '1.0.0');
define('APP_ROOT', __DIR__);
define('UPLOAD_DIR', __DIR__ . '/uploads');
date_default_timezone_set('Asia/Shanghai');
