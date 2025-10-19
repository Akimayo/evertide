<?php

declare(strict_types=1);
require_once(__DIR__ . '/DaoAccessible.php');
require_once(__DIR__ . '/../database.php');

use chillerlan\Settings\SettingsContainerAbstract;

enum LinkStatus: int
{
    case NOT_LINKED = -1;
    case SUCCESS = 0;
    case PRELOADED = 1;
    case TIMED_OUT = 2;
    case ERROR = 3;
    case UNREACHABLE = 65;
    case BLOCKED = 66;
}
class Instance extends SettingsContainerAbstract implements DaoAccessible
{
    protected ?int $id = null;
    protected string $domain;
    protected string $link;
    protected string $primary;
    protected string $secondary;
    protected bool $valid_link = true;
    protected string $first_link_date;
    protected ?string $last_link_date;
    protected LinkStatus $last_link_status;
    protected Device $from_device;
    protected ?string $last_fetch_date;
    protected bool $blocked = false;

    public static function raw(
        ?int $id,
        string $domain,
        string $link,
        string $primary,
        string $secondary,
        bool $valid_link,
        string $first_link_date,
        ?string $last_link_date,
        int $last_link_status,
        Device $from_device,
        ?string $last_fetch_date,
        bool $blocked
    ): static {
        $inst = new self();
        $inst->id = $id;
        $inst->domain = $domain;
        $inst->link = $link;
        $inst->primary = $primary;
        $inst->secondary = $secondary;
        $inst->valid_link = $valid_link;
        $inst->first_link_date = $first_link_date;
        $inst->last_link_date = $last_link_date;
        $inst->last_link_status = LinkStatus::from($last_link_status);
        $inst->from_device = $from_device;
        $inst->last_fetch_date = $last_fetch_date;
        $inst->blocked = $blocked;
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
        return $this->domain;
    }
    public function getPrimaryColor(): string
    {
        return $this->primary;
    }
    public function getSecondaryColor(): string
    {
        return $this->secondary;
    }
    public function isLinkValid(): bool
    {
        return $this->valid_link;
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
    public function getLastFetchDate(): ?string
    {
        return $this->last_fetch_date;
    }
    public function isBlocked(): bool
    {
        return $this->blocked;
    }

    /** @return InstanceDAO */
    public function getAccessObject(Database $db): DAO
    {
        return new InstanceDAO(
            db: $db,
            id: $this->id,
            domain: $this->domain,
            link: $this->link,
            primary: $this->primary,
            secondary: $this->secondary,
            valid_link: $this->valid_link,
            first_link_date: $this->first_link_date,
            last_link_date: $this->last_link_date,
            last_link_status: $this->last_link_status,
            from_device: $this->from_device->getAccessObject($db),
            last_fetch_date: $this->last_fetch_date,
            blocked: $this->blocked
        );
    }
}
class LocalInstance extends Instance
{
    protected ?string $display; // This is only available for the local instance as we need the $domain variable to be a safe file path. Other instances can have anything in $domain.
    protected bool $open = false;

    public function getDisplayName(): string
    {
        return $this->display ?? $this->domain;
    }
    public function isOpen(): bool
    {
        return $this->open;
    }
    private ?bool $validation_result = null;
    public function isLinkValid(): bool
    {
        if (is_null($this->validation_result)) {
            $_normalize_url = require(__DIR__ . '/../functions/normalize_url.php');
            $this->validation_result = $_normalize_url($this->link)['valid_link'];
        }
        return $this->validation_result;
    }

    public function getAccessObject(Database $db): DAO
    {
        throw new Exception('Cannot create an InstanceDAO for the local instance');
    }
}
class InstanceDAO extends Instance implements DAO
{
    private Database $db;
    public function __construct(
        Database $db,
        int $id,
        string $domain,
        string $link,
        string $primary,
        string $secondary,
        bool $valid_link,
        string $first_link_date,
        ?string $last_link_date,
        LinkStatus $last_link_status,
        DeviceDAO $from_device,
        ?string $last_fetch_date,
        bool $blocked
    ) {
        $this->db = $db;
        $this->id = $id;
        $this->domain = $domain;
        $this->link = $link;
        $this->primary = $primary;
        $this->secondary = $secondary;
        $this->valid_link = $valid_link;
        $this->first_link_date = $first_link_date;
        $this->last_link_date = $last_link_date;
        $this->last_link_status = $last_link_status;
        $this->from_device = $from_device;
        $this->last_fetch_date = $last_fetch_date;
        $this->blocked = $blocked;
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
            'UPDATE Instance SET domain = :D, `primary` = :P, secondary = :S, last_link_date = :L, last_link_status = :T, from_device = :V WHERE id = :I;',
            ['D' => $domain, 'P' => $primary, 'S' => $secondary, 'L' => $date, 'T' => $status->value, 'I' => $this->id, 'V' => $this->from_device?->getId()]
        )) {
            $this->domain = $domain;
            $this->primary = $primary;
            $this->secondary = $secondary;
            $this->last_link_date = $date;
            $this->last_link_status = $status;
            return $this;
        } else throw new Exception('Updating Instance.domain, Instance.primary, Instance.secondary, Instance.last_link_date and Instance.last_link_status failed');
    }
    public function updateFetchDate(): self
    {
        $date = date('Y-m-d H:i:s');
        if ($this->db->update('UPDATE Instance SET last_fetch_date = :D WHERE id = :I;', ['D' => $date, 'I' => $this->id])) {
            $this->last_fetch_date = $date;
            return $this;
        } else throw new Exception('Updating Instance.last_fetch_date failed');
    }
    public function resetFetchDate(): self
    {
        if ($this->db->update('UPDATE Instance SET last_fetch_date = NULL WHERE id = :I;', ['I' => $this->id])) {
            $this->last_fetch_date = null;
            return $this;
        } else throw new Exception('Updating Instance.last_fetch_date failed');
    }

    public static function get(Database $db, int|string $key): Instance
    {
        $data = $db->select(
            'SELECT i.domain, i.link, i.`primary`, i.secondary, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.blocked, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.id = :I;',
            ['I' => $key]
        );
        if ($data)
            return Instance::raw(
                id: $key,
                domain: $data['domain'],
                link: $data['link'],
                primary: $data['primary'],
                secondary: $data['secondary'],
                valid_link: boolval($data['valid_link']),
                first_link_date: $data['first_link_date'],
                last_link_date: $data['last_link_date'],
                last_link_status: $data['last_link_status'],
                from_device: new Device(
                    id: $data['from_device'],
                    name: $data['name'],
                    first_login: $data['first_login'],
                    last_login: $data['last_login']
                ),
                last_fetch_date: $data['last_fetch_date'],
                blocked: boolval($data['blocked'])
            );
        else throw new Exception('Instance with the given id does not exist');
    }
    public static function getByAddress(Database $db, string $link_url): Instance
    {
        $data = $db->select(
            'SELECT i.id AS instance_id, i.domain, i.`primary`, i.secondary, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.blocked, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.link = :L;',
            ['L' => $link_url]
        );
        if ($data)
            return Instance::raw(
                id: $data['instance_id'],
                domain: $data['domain'],
                link: $link_url,
                primary: $data['primary'],
                secondary: $data['secondary'],
                valid_link: boolval($data['valid_link']),
                first_link_date: $data['first_link_date'],
                last_link_date: $data['last_link_date'],
                last_link_status: $data['last_link_status'],
                from_device: new Device(
                    id: $data['from_device'],
                    name: $data['name'],
                    first_login: $data['first_login'],
                    last_login: $data['last_login']
                ),
                last_fetch_date: $data['last_fetch_date'],
                blocked: boolval($data['blocked'])
            );
        else throw new Exception('Instance with the given link does not exist');
    }
    /** @return Instance[] */
    public static function getAll(Database $db, bool $only_valid_links = false): array
    {
        $data = $db->selectAll('SELECT i.id, i.domain, i.link, i.`primary`, i.secondary, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.blocked, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device' . ($only_valid_links ? ' WHERE i.valid_link = 1' : '') . ';');
        return array_map(function (array $row) {
            return Instance::raw(
                id: $row['id'],
                domain: $row['domain'],
                link: $row['link'],
                primary: $row['primary'],
                secondary: $row['secondary'],
                valid_link: boolval($row['valid_link']),
                first_link_date: $row['first_link_date'],
                last_link_date: $row['last_link_date'],
                last_link_status: $row['last_link_status'],
                from_device: new Device(
                    id: $row['from_device'],
                    name: $row['name'],
                    first_login: $row['first_login'],
                    last_login: $row['last_login']
                ),
                last_fetch_date: $row['last_fetch_date'],
                blocked: boolval($row['blocked'])
            );
        }, $data);
    }
    public static function create(Database $db, string $domain, string $link, string $primary, string $secondary, bool $valid_link, ?LinkStatus $status = null): Instance
    {
        $device = DeviceDAO::getCurrent($db) ?? DeviceDAO::getRemote($db);
        $date = date('Y-m-d H:i:s');
        $id = $db->insert(
            'INSERT INTO Instance (domain, link, `primary`, secondary, valid_link, first_link_date, from_device) VALUES (:D, :N, :P, :S, :V, :L, :F);',
            ['D' => $domain, 'N' => $link, 'P' => $primary, 'S' => $secondary, 'V' => $valid_link, 'L' => $date, 'F' => $device->getId()],
            'Instance'
        );
        return Instance::raw(
            id: $id,
            domain: $domain,
            link: $link,
            primary: $primary,
            secondary: $secondary,
            valid_link: $valid_link,
            first_link_date: $date,
            last_link_date: null,
            last_link_status: $status->value ?? -1,
            from_device: $device,
            last_fetch_date: null,
            blocked: false
        );
    }
}
