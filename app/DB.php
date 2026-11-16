<?php
declare(strict_types=1);
namespace App;
final class DB {
    private static ?\mysqli $connection = null;
    public static function connection(): \mysqli {
        if (!self::$connection) {
            mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
            self::$connection = new \mysqli(env('DB_HOST','db'),env('DB_USERNAME'),env('DB_PASSWORD'),env('DB_DATABASE'),(int)env('DB_PORT','3306'));
            self::$connection->set_charset('utf8mb4');
            self::$connection->query("SET time_zone='+07:00'");
        }
        return self::$connection;
    }
    public static function run(string $sql, array $params=[]): \mysqli_result|bool { return self::connection()->execute_query($sql,$params); }
    public static function all(string $sql,array $params=[]): array { return self::run($sql,$params)->fetch_all(MYSQLI_ASSOC); }
    public static function one(string $sql,array $params=[]): ?array { return self::run($sql,$params)->fetch_assoc(); }
    public static function id(): int { return (int)self::connection()->insert_id; }
    private static int $depth = 0;
    public static function transaction(callable $fn): mixed {
        $db=self::connection();$level=self::$depth++;$savepoint='lms_nested_'.$level;
        try {
            if($level===0)$db->begin_transaction();else $db->query('SAVEPOINT '.$savepoint);
            $value=$fn();
            if($level===0)$db->commit();else $db->query('RELEASE SAVEPOINT '.$savepoint);
            return $value;
        } catch(\Throwable $e) {
            if($level===0)$db->rollback();else $db->query('ROLLBACK TO SAVEPOINT '.$savepoint);
            throw $e;
        } finally {self::$depth--;}
    }
}
