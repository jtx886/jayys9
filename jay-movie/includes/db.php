<?php
/**
 * Jay影视 - MySQL 数据库访问层（mysqli + 预处理语句）
 * 兼容 PHP 7.4 - 8.x（关闭异常模式，统一手工错误处理）
 */
if (!defined('APP_ROOT')) { http_response_code(403); exit('Forbidden'); }

class DB
{
    /** @var mysqli|null */
    private static $conn = null;

    /** @return mysqli */
    public static function conn()
    {
        if (self::$conn === null) {
            mysqli_report(MYSQLI_REPORT_OFF);
            $conn = @mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME, (int)DB_PORT);
            if (!$conn) {
                die('<div style="font-family:sans-serif;padding:40px;color:#eee;background:#111;">
                    <h2>数据库连接失败</h2><p>' . htmlspecialchars(mysqli_connect_error()) . '</p>
                    <p>请检查 config.php 中的数据库配置是否正确。</p></div>');
            }
            mysqli_set_charset($conn, 'utf8mb4');
            self::$conn = $conn;
        }
        return self::$conn;
    }

    private static function prepare($sql, $params)
    {
        $stmt = mysqli_prepare(self::conn(), $sql);
        if (!$stmt) {
            die('SQL Prepare Error: ' . htmlspecialchars(mysqli_error(self::conn())));
        }
        if (!empty($params)) {
            $types = '';
            foreach ($params as $p) {
                if (is_int($p))      $types .= 'i';
                elseif (is_float($p)) $types .= 'd';
                else                 $types .= 's';
            }
            $stmt->bind_param($types, ...$params);
        }
        return $stmt;
    }

    /** 返回全部行 */
    public static function all($sql, $params = array())
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $rows = array();
        if ($res) { while ($r = $res->fetch_assoc()) { $rows[] = $r; } }
        $stmt->close();
        return $rows;
    }

    /** 返回单行 */
    public static function row($sql, $params = array())
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $row  = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        return $row;
    }

    /** 返回第一行第一列 */
    public static function one($sql, $params = array())
    {
        $stmt = self::prepare($sql, $params);
        $stmt->execute();
        $res  = $stmt->get_result();
        $val  = $res ? $res->fetch_row() : null;
        $stmt->close();
        return $val ? $val[0] : null;
    }

    /** 执行写操作，返回受影响行数 */
    public static function exec($sql, $params = array())
    {
        $stmt = self::prepare($sql, $params);
        $ok   = $stmt->execute();
        $aff  = $stmt->affected_rows;
        $stmt->close();
        return $ok ? $aff : false;
    }

    /** 执行插入，返回自增ID */
    public static function insert($sql, $params = array())
    {
        $stmt = self::prepare($sql, $params);
        $ok   = $stmt->execute();
        $id   = $stmt->insert_id;
        $stmt->close();
        return $ok ? (int)$id : false;
    }

    public static function esc($s)
    {
        return mysqli_real_escape_string(self::conn(), (string)$s);
    }
}
