<?php

declare(strict_types=1);
require_once(__DIR__ . '/config.php');
interface Database
{
    function update(string $query, array $params = []): bool;
    function insert(string $query, array $params = [], ?string $rowIdName = null): int|false;
    function delete(string $query, array $params = []): bool;
    function select(string $query, array $params = []): array|false;
    function selectAll(string $query, array $params = []): array|false;
    function begin(): bool;
    function commit(): bool;
    function rollback(): bool;
}
class ReadOnlyDatabase implements Database
{
    protected PDO $db;
    protected Handler $handler;
    public function __construct(Handler $handler)
    {
        $this->db = Config::get_config()->db;
        $this->handler = $handler;
    }
    public function select(string $query, array $params = []): array|false
    {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) return false;
        if ($stmt->execute($params)) {
            $result = $stmt->fetch();
            return $result;
        } else return false;
    }
    public function selectAll(string $query, array $params = []): array|false
    {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) return false;
        if ($stmt->execute($params)) {
            $result = $stmt->fetchAll();
            return $result;
        } else return false;
    }
    public function update(string $query, array $params = []): bool
    {
        return false; // Not available in readonly
    }
    public function delete(string $query, array $params = []): bool
    {
        return false; // Not available in readonly
    }
    public function insert(string $query, array $params = [], ?string $rowIdName = null): int|false
    {
        return false; // Not available in readonly
    }
    public function begin(): bool
    {
        return $this->db->beginTransaction();
    }
    public function commit(): bool
    {
        return $this->db->commit();
    }
    public function rollback(): bool
    {
        return $this->db->rollBack();
    }
}
class ReadWriteDatabase extends ReadOnlyDatabase
{
    public function __construct(Handler $handler)
    {
        parent::__construct($handler);
    }
    public function update(string $query, array $params = []): bool
    {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) return false;
        return $stmt->execute($params);
    }
    public function delete(string $query, array $params = []): bool
    {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) return false;
        return $stmt->execute($params);
    }
    public function insert(string $query, array $params = [], ?string $rowIdName = null): int|false
    {
        $stmt = $this->db->prepare($query);
        if ($stmt === false) return false;
        if ($stmt->execute($params)) {
            $id = $this->db->lastInsertId($rowIdName);
            if ($id !== false) return intval($id);
            else return false;
        } else return false;
    }
}
final class ServerDatabase extends ReadWriteDatabase
{
    public function __construct(Handler $handler)
    {
        parent::__construct($handler);
        $data_location = Config::get_config()->get_data_location();
        $last_migration = file_exists($data_location . 'last_migration') ? intval(file_get_contents($data_location . 'last_migration')) : -1;
        $waiting = array_filter(glob(Config::get_config()->get_common_data_location() . 'migrations/*.sql'), function (string $file) use ($last_migration) {
            return intval(basename($file, '.sql')) > $last_migration;
        });
        if (count($waiting) > 0)
            $this->migrate($waiting);
    }

    private function migrate(array $migrations): void
    {
        $data_location = Config::get_config()->get_data_location();
        foreach ($migrations as $m) {
            if ($this->db->exec(file_get_contents($m)) !== false) file_put_contents($data_location . 'last_migration', basename($m, '.sql'));
            else return;
        }
    }
}
