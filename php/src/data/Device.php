<?php

declare(strict_types=1);
require_once(__DIR__ . '/DaoAccessible.php');

class Device implements DaoAccessible
{
    protected int $id;
    protected string $name;
    protected string $first_login;
    protected string $last_login;

    public function __construct(int $id, string $name, string $first_login, string $last_login)
    {
        $this->id = $id;
        $this->name = $name;
        $this->first_login = $first_login;
        $this->last_login = $last_login;
    }
    public function getAccessObject(Database $db): DAO
    {
        return new DeviceDAO($db, $this->id, $this->name, $this->first_login, $this->last_login);
    }
    public function getId(): int
    {
        return $this->id;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getFirstLogin(): string
    {
        return $this->first_login;
    }
    public function getLastLogin(): string
    {
        return $this->last_login;
    }
}
class DeviceDAO extends Device implements DAO
{
    private Database $db;
    public function __construct(Database $db, int $id, string $name, string $first_login, string $last_login)
    {
        parent::__construct($id, $name, $first_login, $last_login);
        $this->db = $db;
    }
    public function updateLastLogin(): self
    {
        $date = date('Y-m-d H:i:s');
        if ($this->db->update('UPDATE Device SET last_login = :L;', ['L' => $date])) {
            $this->last_login = $date;
            return $this;
        } else throw new Exception('Updating Device.last_login failed');
    }
    public static function get(Database $db, int|string $key): Device
    {
        $data = $db->select('SELECT id, name, first_login, last_login FROM Device WHERE cookie = :C;', ['C' => $key]);
        if ($data !== false)
            return new Device(
                id: $data['id'],
                name: $data['name'],
                first_login: $data['first_login'],
                last_login: $data['last_login']
            );
        else throw new Exception('Device with the given cookie code does not exist');
    }
    public static function getCurrent(Database $db): ?Device
    {
        if (!isset($_COOKIE['evertide'])) return null;
        $parts = explode(';', $_COOKIE['evertide']);
        return self::get($db, $parts[1]);
    }
    /** @return Device[] */
    public static function getAll(Database $db): array
    {
        $data = $db->selectAll('SELECT id, name, first_login, last_login FROM Device;');
        if ($data !== false)
            return array_map(function (array $row) {
                return new Device(
                    id: $row['id'],
                    name: $row['name'],
                    first_login: $row['first_login'],
                    last_login: $row['last_login']
                );
            }, $data);
        else throw new Exception('Device with the given cookie code does not exist');
    }
    public static function create(Database $db, string $name, string $cookie_code): Device
    {
        $date = date('Y-m-d H:i:s');
        $id = $db->insert(
            'INSERT INTO Device (name, first_login, last_login, cookie) VALUES (:N, :F, :L, :C);',
            ['N' => $name, 'F' => $date, 'L' => $date, 'C' => $cookie_code],
            'Device.id'
        );
        if ($id !== false)
            return new Device(
                id: $id,
                name: $name,
                first_login: $date,
                last_login: $date
            );
        else throw new Exception('Creating Device failed');
    }
}
