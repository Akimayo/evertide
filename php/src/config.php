<?php

declare(strict_types=1);

use chillerlan\Settings\SettingsContainerAbstract;
use chillerlan\Settings\SettingsContainerInterface;

abstract class SettingsContainerCustomAbstract extends SettingsContainerAbstract
{
    /**
     * @inheritdoc
     */
    public function fromIterable(iterable $properties): static
    {
        $r = new ReflectionClass(static::class);
        foreach ($properties as $key => $value) {
            if (
                is_array($value) &&
                ($classProp = $r->getProperty($key)) &&
                ($propType = $classProp->getType()) &&
                ($class = new ReflectionClass($propType->getName()))->isSubclassOf(SettingsContainerInterface::class)
            ) {
                $this->__set($key, $class->newInstanceArgs(['properties' => $value]));
            } else $this->__set($key, $value);
        }

        return $this;
    }
}
final class Config__Database extends SettingsContainerCustomAbstract
{
    // Common
    public string $type;
    // SQLite
    public string $name;
    // MySQL
    public string $hostname;
    public string $database;
    public string $username;
    public string $password;
}
final class Config extends SettingsContainerCustomAbstract
{
    public PDO $db;
    protected Config__Database $database;

    public function __construct()
    {
        // Load config file
        $cfg_array = spyc_load_file(__DIR__ . '/../opt/config.yml');
        parent::__construct($cfg_array);

        // Build PDO connection
        switch ($this->database->type) {
            case 'sqlite':
                $this->db = new PDO('sqlite:' . __DIR__ . '/../opt/' . $this->database->name);
                break;
            case 'mysql':
                $this->db = new PDO('mysql:host=' . $this->database->hostname . ';dbname=' . $this->database->database, $this->database->username, $this->database->password);
                break;
        }
    }

    function __toString(): string
    {
        return '';
    }
    function __serialize(): array
    {
        return [];
    }
    function jsonSerialize(): array
    {
        return [];
    }
}
