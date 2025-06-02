<?php

declare(strict_types=1);
require_once(__DIR__ . '/DaoAccessible.php');
require_once(__DIR__ . '/../database.php');

use chillerlan\Settings\SettingsContainerAbstract;

enum LinkStatus: string
{
    case NOT_LINKED = -1;
}
class Instance extends SettingsContainerAbstract implements DaoAccessible
{
    protected ?int $id = null;
    protected string $domain;
    protected ?string $display; // This is only available for the local instance as we need the $domain variable to be a safe file path. Other instances can have anything in $domain.
    protected string $link;
    protected string $primary;
    protected string $secondary;
    protected string $first_link_date;
    protected ?string $last_link_date;
    protected LinkStatus $last_link_status;
    protected Device $from_device;

    public static function raw(?int $id, string $domain, string $link, string $primary, string $secondary, string $first_link_date, ?string $last_link_date, int $last_link_status, Device $from_device): static
    {
        $inst = new self();
        $inst->id = $id;
        $inst->domain = $domain;
        $inst->link = $link;
        $inst->primary = $primary;
        $inst->secondary = $secondary;
        $inst->first_link_date = $first_link_date;
        $inst->last_link_date = $last_link_date;
        $inst->last_link_status = LinkStatus::from($last_link_status);
        $inst->from_device = $from_device;
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
    public function getDisplayName(): string
    {
        return $this->display ?? $this->domain;
    }
    public function getPrimaryColor(): string
    {
        return $this->primary;
    }
    public function getSecondaryColor(): string
    {
        return $this->secondary;
    }
    public function getLink(): string
    {
        return $this->link;
    }
    public function getFirstLinkDate(): string
    {
        return $this->first_link_date;
    }
    public function getLastLinkDate(): ?string
    {
        return $this->last_link_date;
    }
    public function getLastLinkStatus(): LinkStatus
    {
        return $this->last_link_status;
    }
    public function getAuthorDevice(): Device
    {
        return $this->from_device;
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
            secondary: $this->secondary,
            first_link_date: $this->first_link_date,
            last_link_date: $this->last_link_date,
            last_link_status: $this->last_link_status,
            from_device: $this->from_device->getAccessObject($db)
        );
    }
}
class InstanceDAO extends Instance implements DAO
{
    private Database $db;
    public function __construct(Database $db, int $id, string $domain, string $link, string $primary, string $secondary, string $first_link_date, ?string $last_link_date, LinkStatus $last_link_status, DeviceDAO $from_device)
    {
        $this->db = $db;
        $this->id = $id;
        $this->domain = $domain;
        $this->link = $link;
        $this->primary = $primary;
        $this->secondary = $secondary;
        $this->first_link_date = $first_link_date;
        $this->last_link_date = $last_link_date;
        $this->last_link_status = $last_link_status;
        $this->from_device = $from_device;
    }

    /** @return DeviceDAO */
    public function getAuthorDevice(): Device
    {
        return $this->from_device;
    }

    public function updateLinkStatus(LinkStatus $status): self
    {
        $date = date('Y-m-d H:i:s');
        if ($this->db->update(
            'UPDATE Instance SET last_link_date = :D, last_link_status = :S WHERE id = :I;',
            ['D' => $date, 'S' => $status->value, 'I' => $this->id]
        )) {
            $this->last_link_date = $date;
            $this->last_link_status = $status;
            return $this;
        } else throw new Exception('Updating Instance.last_link_date and Instance.last_link_status failed');
    }
    public function updateInstance(string $domain, string $primary, string $secondary, LinkStatus $status): self
    {
        $date = date('Y-m-d H:i:s');
        if ($this->db->update(
            'UPDATE Instance SET domain = :D, `primary` = :P, secondary = :S, last_link_date = :L, last_link_status = :T WHERE id = :I;',
            ['D' => $domain, 'P' => $primary, 'S' => $secondary, 'L' => $date, 'T' => $status->value, 'I' => $this->id]
        )) {
            $this->domain = $domain;
            $this->primary = $primary;
            $this->secondary = $secondary;
            $this->last_link_date = $date;
            $this->last_link_status = $status;
            return $this;
        } else throw new Exception('Updating Instance.domain, Instance.primary, Instance.secondary, Instance.last_link_date and Instance.last_link_status failed');
    }

    public static function get(Database $db, int|string $key): Instance
    {
        $data = $db->select(
            'SELECT i.domain, i.link, i.`primary`, i.secondary, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.id = :I;',
            ['I' => $key]
        );
        if ($data)
            return Instance::raw(
                id: $key,
                domain: $data['domain'],
                link: $data['link'],
                primary: $data['primary'],
                secondary: $data['secondary'],
                first_link_date: $data['first_link_date'],
                last_link_date: $data['last_link_date'],
                last_link_status: $data['last_link_status'],
                from_device: new Device(
                    id: $data['from_device'],
                    name: $data['name'],
                    first_login: $data['first_login'],
                    last_login: $data['last_login']
                )
            );
        else throw new Exception('Instance with the given id does not exist');
    }
    /** @return Instance[] */
    public static function getAll(Database $db): array
    {
        $data = $db->select('SELECT i.id, i.link, i.`primary`, i.secondary, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device;');
        return array_map(function (array $row) {
            return Instance::raw(
                id: $row['id'],
                domain: $row['domain'],
                link: $row['link'],
                primary: $row['primary'],
                secondary: $row['secondary'],
                first_link_date: $row['first_link_date'],
                last_link_date: $row['last_link_date'],
                last_link_status: $row['last_link_status'],
                from_device: new Device(
                    id: $row['from_device'],
                    name: $row['name'],
                    first_login: $row['first_login'],
                    last_login: $row['last_login']
                )
            );
        }, $data);
    }
    public static function create(Database $db, string $domain, string $link, string $primary, string $secondary): Instance
    {
        $device = DeviceDAO::getCurrent($db);
        $date = date('Y-m-d H:i:s');
        $id = $db->insert(
            'INSERT INTO Instance (domain, link, `primary`, secondary, first_link_date, from_device) VALUES (:D, :L, :P, :S, :L, :F);',
            ['D' => $domain, 'L' => $link, 'P' => $primary, 'S' => $secondary, 'L' => $date, 'F' => $device->getId()],
            'Instance'
        );
        return Instance::raw(
            id: $id,
            domain: $domain,
            link: $link,
            primary: $primary,
            secondary: $secondary,
            first_link_date: $date,
            last_link_date: null,
            last_link_status: -1,
            from_device: $device
        );
    }
}
