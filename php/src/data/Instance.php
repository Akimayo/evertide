<?php

declare(strict_types=1);
require_once(__DIR__ . '/DaoAccessible.php');
require_once(__DIR__ . '/../database.php');

use chillerlan\Settings\SettingsContainerAbstract;

class Instance extends SettingsContainerAbstract implements DaoAccessible
{
    protected ?int $id = null;
    protected string $domain;
    protected string $link;
    protected string $primary;
    protected string $secondary;

    public static function raw(?int $id, string $domain, string $link, string $primary, string $secondary): static
    {
        $inst = new self();
        $inst->id = $id;
        $inst->domain = $domain;
        $inst->link = $link;
        $inst->primary = $primary;
        $inst->secondary = $secondary;
        return $inst;
    }

    public function getId(): ?int
    {
        return $this->id;
    }
    public function getDomainName(): string
    {
        return $this->domain;
    }
    public function getPrimaryColor(): string
    {
        return $this->primary;
    }
    public function getSeconaryColor(): string
    {
        return $this->secondary;
    }
    public function getLink(): string
    {
        return $this->link;
    }

    public function getAccessObject(Database $db): DAO
    {
        if ($this->id === null) throw new Exception('Cannot create an InstanceDAO for the local instance');
        return new InstanceDAO(
            db: $db,
            id: $this->id,
            domain: $this->domain,
            link: $this->link,
            primary: $this->primary,
            secondary: $this->secondary
        );
    }
}
class InstanceDAO extends Instance implements DAO
{
    private Database $db;
    public function __construct(Database $db, int $id, string $domain, string $link, string $primary, string $secondary)
    {
        $this->db = $db;
        parent::__construct($domain, $link, $primary, $secondary);
    }

    public static function get(Database $db, int|string $key): Instance
    {
        throw new Exception('not implemented'); // FIXME:
    }
    /** @return Instance[] */
    public static function getAll(Database $db): array
    {
        throw new Exception('not implemented'); // FIXME:
    }
}
