<?php
/**
 * PONNU — Database.php
 * PDO ラッパー（シングルトン）
 *
 * 特徴:
 * - utf8mb4 接続、EMULATE_PREPARES=false、ERRMODE_EXCEPTION
 * - fetchOne / fetchAll / execute / lastInsertId ヘルパー
 */

declare(strict_types=1);

class Database
{
    private static ?Database $instance = null;
    private PDO $pdo;

    private function __construct()
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            DB_HOST,
            DB_PORT,
            DB_NAME,
            DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        $this->pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    /**
     * シングルトンインスタンスを取得
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 1行取得。結果なしは false を返す。
     *
     * @param  string  $sql    プリペアドSQL
     * @param  array   $params バインドパラメータ
     * @return array|false
     */
    public function fetchOne(string $sql, array $params = []): array|false
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetch();
    }

    /**
     * 複数行取得
     *
     * @param  string  $sql
     * @param  array   $params
     * @return array[]
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    /**
     * INSERT / UPDATE / DELETE を実行し影響行数を返す
     *
     * @param  string  $sql
     * @param  array   $params
     * @return int 影響行数
     */
    public function execute(string $sql, array $params = []): int
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /**
     * 最後にINSERTした行のID
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }

    /**
     * トランザクション開始
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * コミット
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * ロールバック
     */
    public function rollback(): bool
    {
        return $this->pdo->rollBack();
    }

    /**
     * 生のPDOオブジェクトを取得（高度な用途向け）
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    // シングルトンのクローン・デシリアライズを禁止
    private function __clone() {}
}
