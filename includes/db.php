<?php
/**
 * لایه اتصال به پایگاه داده (PDO)
 * از MySQL/MariaDB و SQLite پشتیبانی می‌کند.
 */

class DB
{
    /** @var PDO|null */
    private static $pdo = null;
    /** @var string mysql|sqlite */
    private static $driver = 'mysql';
    /** @var string پیام خطای اتصال در صورت fallback */
    private static $fallbackNote = '';

    public static function driver()
    {
        self::pdo();
        return self::$driver;
    }

    public static function fallbackNote()
    {
        return self::$fallbackNote;
    }

    /** @return PDO */
    public static function pdo()
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }
        $cfg = config('db');
        $want = $cfg['driver'] ?? 'mysql';

        if ($want === 'sqlite') {
            self::$pdo = self::connectSqlite($cfg);
            self::$driver = 'sqlite';
            return self::$pdo;
        }

        try {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                $cfg['host'] ?? '127.0.0.1',
                (int)($cfg['port'] ?? 3306),
                $cfg['dbname'] ?? 'accounting',
                $cfg['charset'] ?? 'utf8mb4'
            );
            self::$pdo = new PDO($dsn, $cfg['username'] ?? 'root', $cfg['password'] ?? '', [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            self::$driver = 'mysql';
        } catch (PDOException $e) {
            if (!empty($cfg['allow_sqlite_fallback'])) {
                self::$pdo = self::connectSqlite($cfg);
                self::$driver = 'sqlite';
                self::$fallbackNote = 'اتصال به MySQL ممکن نبود (' . $e->getMessage() . ')؛ از SQLite استفاده شد.';
            } else {
                throw $e;
            }
        }
        return self::$pdo;
    }

    private static function connectSqlite($cfg)
    {
        $path = $cfg['sqlite_path'] ?? (dirname(__DIR__) . '/database/accounting.sqlite');
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $pdo = new PDO('sqlite:' . $path, null, null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA journal_mode = WAL');
        return $pdo;
    }

    /** اجرای کوئری با پارامترها */
    public static function run($sql, $params = [])
    {
        $stmt = self::pdo()->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /** همه سطرها */
    public static function all($sql, $params = [])
    {
        return self::run($sql, $params)->fetchAll();
    }

    /** یک سطر */
    public static function one($sql, $params = [])
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /** یک مقدار (ستون اول سطر اول) */
    public static function value($sql, $params = [], $default = null)
    {
        $row = self::run($sql, $params)->fetch(PDO::FETCH_NUM);
        if ($row === false || $row === null) {
            return $default;
        }
        return $row[0];
    }

    /** درج سطر؛ شناسه ایجادشده را برمی‌گرداند */
    public static function insert($table, array $data)
    {
        $cols = array_keys($data);
        $sql = 'INSERT INTO ' . $table . ' (`' . implode('`,`', $cols) . '`) VALUES ('
             . implode(',', array_fill(0, count($cols), '?')) . ')';
        self::run($sql, array_values($data));
        return (int)self::pdo()->lastInsertId();
    }

    /** بروزرسانی سطرها */
    public static function update($table, array $data, $where, array $whereParams = [])
    {
        $sets = [];
        $values = [];
        foreach ($data as $col => $val) {
            $sets[] = "`$col` = ?";
            $values[] = $val;
        }
        $sql = 'UPDATE ' . $table . ' SET ' . implode(', ', $sets) . ' WHERE ' . $where;
        $stmt = self::run($sql, array_merge($values, $whereParams));
        return $stmt->rowCount();
    }

    /** حذف سطرها */
    public static function delete($table, $where, array $params = [])
    {
        return self::run('DELETE FROM ' . $table . ' WHERE ' . $where, $params)->rowCount();
    }

    public static function begin()
    {
        self::pdo()->beginTransaction();
    }

    public static function commit()
    {
        self::pdo()->commit();
    }

    public static function rollBack()
    {
        if (self::pdo()->inTransaction()) {
            self::pdo()->rollBack();
        }
    }
}
