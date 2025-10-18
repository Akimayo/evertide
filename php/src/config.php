<?php

declare(strict_types=1);
require_once(__DIR__ . '/data/Instance.php');

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
    public string $type = "sqlite";
    // SQLite
    public string $name = "evertide.sqlite";
    // MySQL
    public string $hostname;
    public string $database;
    public string $username;
    public string $password;
}
final class Config extends SettingsContainerCustomAbstract
{
    // Functional variables
    private static ?Config $singleton = null;
    public static function get_config(): static
    {
        if (is_null(self::$singleton)) self::$singleton = new self();
        return self::$singleton;
    }
    public PDO $db;
    private string $opt;
    private string $opt_common;
    public function get_data_location(): string
    {
        return $this->opt;
    }
    public function get_common_data_location(): string
    {
        return $this->opt_common;
    }
    public function get_cookie_name(): string
    {
        return 'evertide@' . preg_replace('/[.\/]+/', '_', $this->instance->getDomainName());
    }

    // Variables filled by SettingsContainer
    protected Config__Database $database;
    public LocalInstance $instance;

    private function __construct()
    {
        $opt_location = dirname(__DIR__) . '/opt/';
        $this->opt_common = $opt_location;
        if (($instance_location = getenv('EVERTIDE_INSTANCE'))) $opt_location .= preg_replace('/[\/<>:"\\|?\*]+/', '_', $instance_location) . '/';
        $this->opt = $opt_location;
        if (!file_exists($opt_location)) throw new Exception("Configuration for this instance does not exist");

        $this->database = new Config__Database(); // Blank, in case instance uses default SQLite

        // Load config file
        $cfg_array = spyc_load_file($opt_location . 'config.yml');
        parent::__construct($cfg_array);

        // Build PDO connection
        switch ($this->database->type) {
            case 'sqlite':
                $this->db = new PDO('sqlite:' . $opt_location . $this->database->name);
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
