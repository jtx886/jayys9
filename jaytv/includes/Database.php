&lt;?php
class Database {
    private static $instance = null;
    private $pdo;

    private function __construct($host, $dbname, $username, $password, $charset = 'utf8mb4') {
        try {
            $dsn = "mysql:host=$host;dbname=$dbname;charset=$charset";
            $options = [
                PDO::ATTR_ERRMODE            =&gt; PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE =&gt; PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   =&gt; false,
            ];
            $this-&gt;pdo = new PDO($dsn, $username, $password, $options);
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e-&gt;getMessage());
        }
    }

    public static function getInstance($host = null, $dbname = null, $username = null, $password = null) {
        if (self::$instance === null) {
            if ($host &amp;&amp; $dbname &amp;&amp; $username) {
                self::$instance = new self($host, $dbname, $username, $password);
            } else {
                $config = require __DIR__ . '/../config.php';
                self::$instance = new self(
                    $config['db_host'],
                    $config['db_name'],
                    $config['db_user'],
                    $config['db_pass']
                );
            }
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this-&gt;pdo;
    }

    public function query($sql, $params = []) {
        $stmt = $this-&gt;pdo-&gt;prepare($sql);
        $stmt-&gt;execute($params);
        return $stmt;
    }

    public function fetchAll($sql, $params = []) {
        return $this-&gt;query($sql, $params)-&gt;fetchAll();
    }

    public function fetch($sql, $params = []) {
        return $this-&gt;query($sql, $params)-&gt;fetch();
    }

    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $sql = "INSERT INTO $table ($columns) VALUES ($placeholders)";
        $this-&gt;query($sql, $data);
        return $this-&gt;pdo-&gt;lastInsertId();
    }

    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        foreach (array_keys($data) as $key) {
            $set[] = "$key = :$key";
        }
        $setStr = implode(', ', $set);
        $sql = "UPDATE $table SET $setStr WHERE $where";
        $params = array_merge($data, $whereParams);
        return $this-&gt;query($sql, $params)-&gt;rowCount();
    }

    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this-&gt;query($sql, $params)-&gt;rowCount();
    }
}
