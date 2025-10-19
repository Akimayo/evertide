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
    protected string $first_link_date;
    protected ?string $last_link_date;
    protected LinkStatus $last_link_status;
    protected Device $from_device;
    protected ?string $last_fetch_date;
    protected string $last_edit_date;
    protected bool $blocked = false;
    protected ?string $sticker_path;
    protected ?string $sticker_link;
    protected bool $display_sticker = false;

    public static function raw(
        ?int $id,
        string $domain,
        string $link,
        string $primary,
        string $secondary,
        string $first_link_date,
        ?string $last_link_date,
        int $last_link_status,
        Device $from_device,
        ?string $last_fetch_date,
        string $last_edit_date,
        bool $blocked,
        ?string $sticker_path,
        ?string $sticker_link,
        bool $display_sticker
    ): static {
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
        $inst->last_fetch_date = $last_fetch_date;
        $inst->last_edit_date = $last_edit_date;
        $inst->blocked = $blocked;
        $inst->sticker_path = $sticker_path;
        $inst->sticker_link = $sticker_link;
        $inst->display_sticker = $display_sticker;
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
    public function getLastEditDate(): string
    {
        return $this->last_edit_date;
    }
    public function isBlocked(): bool
    {
        return $this->blocked;
    }
    public function getStickerPath(): ?string
    {
        return $this->sticker_path;
    }
    public function getStickerLink(): string
    {
        return $this->sticker_link ?? $this->link;
    }
    public function isStickerDisplayed(): bool
    {
        return !is_null($this->sticker_path) && $this->display_sticker;
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
            first_link_date: $this->first_link_date,
            last_link_date: $this->last_link_date,
            last_link_status: $this->last_link_status,
            from_device: $this->from_device->getAccessObject($db),
            last_fetch_date: $this->last_fetch_date,
            last_edit_date: $this->last_edit_date,
            blocked: $this->blocked,
            sticker_path: $this->sticker_path,
            sticker_link: $this->sticker_link,
            display_sticker: $this->display_sticker
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

    public function getLastEditDate(): string
    {
        throw new Exception('Cannot return edit date for local instance');
    }

    public function isOpen(): bool
    {
        return $this->open;
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
        string $first_link_date,
        ?string $last_link_date,
        LinkStatus $last_link_status,
        DeviceDAO $from_device,
        ?string $last_fetch_date,
        string $last_edit_date,
        bool $blocked,
        ?string $sticker_path,
        ?string $sticker_link,
        bool $display_sticker
    ) {
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
        $this->last_fetch_date = $last_fetch_date;
        $this->last_edit_date = $last_edit_date;
        $this->blocked = $blocked;
        $this->sticker_path = $sticker_path;
        $this->sticker_link = $sticker_link;
        $this->display_sticker = $display_sticker;
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
    public function updateInstance(string $domain, string $primary, string $secondary, ?string $sticker_path, ?string $sticker_link, LinkStatus $status): self
    {
        $date = date('Y-m-d H:i:s');
        $display_sticker_new = $this->display_sticker && $this->sticker_link == $sticker_link; // Hide sticker when link changes to prevent malicious link changes
        if ($this->db->update(
            'UPDATE Instance SET domain = :D, `primary` = :P, secondary = :S, sticker_path = :H, sticker_link = :N, display_sticker = :C, last_link_date = :L, last_link_status = :T, from_device = :V WHERE id = :I;',
            ['D' => $domain, 'P' => $primary, 'S' => $secondary, 'H' => $sticker_path, 'N' => $sticker_link, 'C' => $display_sticker_new, 'L' => $date, 'T' => $status->value, 'I' => $this->id, 'V' => $this->from_device?->getId()]
        )) {
            $this->domain = $domain;
            $this->primary = $primary;
            $this->secondary = $secondary;
            $this->sticker_path = $sticker_path;
            $this->sticker_link = $sticker_link;
            $this->display_sticker = $display_sticker_new;
            $this->last_link_date = $date;
            $this->last_link_status = $status;
            return $this;
        } else throw new Exception('Updating Instance.domain, Instance.primary, Instance.secondary, Instance.sticker_path, Instance.last_link_date and Instance.last_link_status failed');
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
    public function setStickerDisplay(bool $display): self
    {
        $date = date('Y-m-d H:i:s');
        if ($this->db->update('UPDATE Instance SET display_sticker = :C, last_edit_date = :L WHERE id = :I;', ['I' => $this->id, 'C' => $display, 'L' => $date])) {
            $this->display_sticker = $display;
            $this->last_edit_date = $date;
            return $this;
        } else throw new Exception('Updating Instance.display_sticker and Instance.last_edit_date failed');
    }

    public static function get(Database $db, int|string $key): Instance
    {
        $data = $db->select(
            'SELECT i.domain, i.link, i.`primary`, i.secondary, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.id = :I;',
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
                ),
                last_fetch_date: $data['last_fetch_date'],
                last_edit_date: $data['last_edit_date'],
                blocked: boolval($data['blocked']),
                sticker_path: $data['sticker_path'],
                sticker_link: $data['sticker_link'],
                display_sticker: boolval($data['display_sticker'])
            );
        else throw new Exception('Instance with the given id does not exist');
    }
    public static function getByAddress(Database $db, string $link_url): Instance
    {
        $data = $db->select(
            'SELECT i.id AS instance_id, i.domain, i.`primary`, i.secondary, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.link = :L;',
            ['L' => $link_url]
        );
        if ($data)
            return Instance::raw(
                id: $data['instance_id'],
                domain: $data['domain'],
                link: $link_url,
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
                ),
                last_fetch_date: $data['last_fetch_date'],
                last_edit_date: $data['last_edit_date'],
                blocked: boolval($data['blocked']),
                sticker_path: $data['sticker_path'],
                sticker_link: $data['sticker_link'],
                display_sticker: boolval($data['display_sticker'])
            );
        else throw new Exception('Instance with the given link does not exist');
    }
    /** @return Instance[] */
    public static function getAll(Database $db): array
    {
        $data = $db->selectAll('SELECT i.id, i.domain, i.link, i.`primary`, i.secondary, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device;');
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
                ),
                last_fetch_date: $row['last_fetch_date'],
                last_edit_date: $row['last_edit_date'],
                blocked: boolval($row['blocked']),
                sticker_path: $row['sticker_path'],
                sticker_link: $row['sticker_link'],
                display_sticker: boolval($row['display_sticker'])
            );
        }, $data);
    }
    public static function create(Database $db, string $domain, string $link, string $primary, string $secondary, ?string $sticker_path, ?string $sticker_link, ?LinkStatus $status = null): Instance
    {
        $device = DeviceDAO::getCurrent($db) ?? DeviceDAO::getRemote($db);
        $date = date('Y-m-d H:i:s');
        $id = $db->insert(
            'INSERT INTO Instance (domain, link, `primary`, secondary, sticker_path, sticker_link, first_link_date, from_device, last_edit_date) VALUES (:D, :N, :P, :S, :H, :N, :L, :F, :L);',
            ['D' => $domain, 'N' => $link, 'P' => $primary, 'S' => $secondary, 'H' => $sticker_path, 'N' => $sticker_link, 'L' => $date, 'F' => $device->getId()],
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
            last_link_status: $status->value ?? -1,
            from_device: $device,
            last_fetch_date: null,
            last_edit_date: $date,
            blocked: false,
            sticker_path: $sticker_path,
            sticker_link: $sticker_link,
            display_sticker: false
        );
    }
}
