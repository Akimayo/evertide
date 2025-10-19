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
enum RichDisplayInstanceType: int
{
    // In rich display mode, render the remote instance as...
    case TRAIN = 0; // a ground vehicle
    case SHIP = 1; // a naval vehicle
    case PLANE = 2; // an aerial vehicle
    case NEIGHBOUR = 3; // a neighbouring settlement
    case MERCHANT = 4; // a merchant stall
    case CIRCUS = 5; // an entertainment establishment
    case WIZARD = 6; // a magical construct
}
enum RichDisplayInstanceEra: int
{
    case PREHISTORIC = 0;
    case ROMAN = 1;
    case MEDIEVAL = 2;
    case PRESENT = 5;
    case FUTURE = 10;
}
class Instance extends SettingsContainerAbstract implements DaoAccessible
{
    protected ?int $id = null;
    protected string $domain;
    protected string $link;
    protected string $primary;
    protected string $secondary;
    protected RichDisplayInstanceType $render = RichDisplayInstanceType::NEIGHBOUR;
    protected bool $valid_link = true;
    protected string $first_link_date;
    protected ?string $last_link_date;
    protected LinkStatus $last_link_status;
    protected Device $from_device;
    protected ?string $last_fetch_date;
    protected string $last_edit_date;
    protected bool $blocked = false;
    protected ?string $sticker_path = null;
    protected ?string $sticker_link = null;
    protected bool $display_sticker = false;
    protected ?string $public_key = null;
    protected ?string $private_key = null;

    public static function raw(
        ?int $id,
        string $domain,
        string $link,
        string $primary,
        string $secondary,
        RichDisplayInstanceType $render,
        bool $valid_link,
        string $first_link_date,
        ?string $last_link_date,
        int $last_link_status,
        Device $from_device,
        ?string $last_fetch_date,
        string $last_edit_date,
        bool $blocked,
        ?string $sticker_path,
        ?string $sticker_link,
        bool $display_sticker,
        ?string $public_key = null,
        ?string $private_key = null
    ): static {
        $inst = new self();
        $inst->id = $id;
        $inst->domain = $domain;
        $inst->link = $link;
        $inst->primary = $primary;
        $inst->secondary = $secondary;
        $inst->render = $render;
        $inst->valid_link = $valid_link;
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
        $inst->public_key = $public_key;
        $inst->private_key = $private_key;
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
    public function getRenderType(): RichDisplayInstanceType
    {
        return $this->render;
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
    public function getPublicKey(): ?string
    {
        return $this->public_key;
    }

    public function getFederationInfo(): array
    {
        return [
            'domain' => $this->getDisplayName(),
            'link' => $this->link,
            'primary' => $this->primary,
            'secondary' => $this->secondary,
            'sticker_path' => $this->sticker_path,
            'sticker_link' => $this->sticker_link,
            'render' => $this->render->value
        ];
    }
    private static function getSignatureHash(array $federation_info): string
    {
        return sha1($federation_info['domain'] .
            $federation_info['link'] .
            $federation_info['primary'] .
            $federation_info['secondary'] .
            $federation_info['render'] .
            $federation_info['sticker_path'] ?? null .
            $federation_info['sticker_link'] ?? null);
    }
    public function getSignature(string $remote_public_key): string
    {
        $time = strval(time());
        $len = str_pad(dechex(strlen($time)), 4, '0', STR_PAD_LEFT);
        openssl_public_encrypt(
            $len . $time . self::getSignatureHash($this->getFederationInfo()),
            $signature,
            $remote_public_key
        );
        return $signature;
    }
    public function getSignedFederationInfo(string $remote_public_key): array
    {
        $info = $this->getFederationInfo();

        $info['signature'] = $this->getSignature($remote_public_key);
        return $info;
    }
    public function validateSignature(array $signed_federation_info): bool
    {
        // Decrypt signature
        if (!openssl_private_decrypt($signed_federation_info['signature'], $signature, $this->private_key)) return false; // Wrong signing key, fail
        // Extract and check signature time
        $len = hexdec(substr($signature, 0, 4));
        $time = substr($signature, 4, $len);
        if (abs($time - time()) > 30) return false; // Old signature, fail
        // Compare data hashes
        $local_hash = self::getSignatureHash($signed_federation_info);
        $remote_hash = substr($signature, 4 + $len);
        if ($local_hash !== $remote_hash) return false; // Invalid hash of values, fail
        // Success
        return true;
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
            render: $this->render,
            valid_link: $this->valid_link,
            first_link_date: $this->first_link_date,
            last_link_date: $this->last_link_date,
            last_link_status: $this->last_link_status,
            from_device: $this->from_device->getAccessObject($db),
            last_fetch_date: $this->last_fetch_date,
            last_edit_date: $this->last_edit_date,
            blocked: $this->blocked,
            sticker_path: $this->sticker_path,
            sticker_link: $this->sticker_link,
            display_sticker: $this->display_sticker,
            public_key: $this->public_key,
            private_key: $this->private_key
        );
    }
}
class LocalInstance extends Instance
{
    protected ?string $display; // This is only available for the local instance as we need the $domain variable to be a safe file path. Other instances can have anything in $domain.
    protected bool $open = false;
    protected string $richRenderOnRemoteAs = "neighbour";
    protected string $richRenderEra = "present";
    protected RichDisplayInstanceEra $era;

    public function __initialize()
    {
        $render_upper = strtoupper($this->richRenderOnRemoteAs);
        $era_upper = strtoupper($this->richRenderEra);
        foreach (RichDisplayInstanceType::cases() as $type)
            if ($type->name == $render_upper) {
                $this->render = $type;
                break;
            }
        foreach (RichDisplayInstanceEra::cases() as $era)
            if ($era->name == $era_upper) {
                $this->era = $era;
                break;
            }
    }
    public function getDisplayName(): string
    {
        return $this->display ?? $this->domain;
    }
    public function getDisplayEra(): RichDisplayInstanceEra
    {
        return $this->era;
    }
    public function getLastEditDate(): string
    {
        throw new Exception('Cannot return edit date for local instance');
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
        RichDisplayInstanceType $render,
        bool $valid_link,
        string $first_link_date,
        ?string $last_link_date,
        LinkStatus $last_link_status,
        DeviceDAO $from_device,
        ?string $last_fetch_date,
        string $last_edit_date,
        bool $blocked,
        ?string $sticker_path,
        ?string $sticker_link,
        bool $display_sticker,
        ?string $public_key = null,
        ?string $private_key = null
    ) {
        $this->db = $db;
        $this->id = $id;
        $this->domain = $domain;
        $this->link = $link;
        $this->primary = $primary;
        $this->secondary = $secondary;
        $this->render = $render;
        $this->valid_link = $valid_link;
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
        $this->public_key = $public_key;
        $this->private_key = $private_key;
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
    public function updateInstance(string $domain, string $primary, string $secondary, RichDisplayInstanceType $render, ?string $sticker_path, ?string $sticker_link, LinkStatus $status): self
    {
        $date = date('Y-m-d H:i:s');
        $display_sticker_new = $this->display_sticker && $this->sticker_link == $sticker_link; // Hide sticker when link changes to prevent malicious link changes
        if ($this->db->update(
            'UPDATE Instance SET domain = :D, `primary` = :P, secondary = :S, render = :R, sticker_path = :H, sticker_link = :K, display_sticker = :C, last_link_date = :L, last_link_status = :T, from_device = :V WHERE id = :I;',
            ['D' => $domain, 'P' => $primary, 'S' => $secondary, 'R' => $render->value, 'H' => $sticker_path, 'K' => $sticker_link, 'C' => $display_sticker_new, 'L' => $date, 'T' => $status->value, 'I' => $this->id, 'V' => $this->from_device?->getId()]
        )) {
            $this->domain = $domain;
            $this->primary = $primary;
            $this->secondary = $secondary;
            $this->render = $render;
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
            'SELECT i.domain, i.link, i.`primary`, i.secondary, i.render, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, i.private_key, i.public_key, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.id = :I;',
            ['I' => $key]
        );
        if ($data)
            return Instance::raw(
                id: $key,
                domain: $data['domain'],
                link: $data['link'],
                primary: $data['primary'],
                secondary: $data['secondary'],
                render: RichDisplayInstanceType::from($data['render']),
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
                last_edit_date: $data['last_edit_date'],
                blocked: boolval($data['blocked']),
                sticker_path: $data['sticker_path'],
                sticker_link: $data['sticker_link'],
                display_sticker: boolval($data['display_sticker']),
                public_key: $data['public_key'],
                private_key: $data['private_key']
            );
        else throw new Exception('Instance with the given id does not exist');
    }
    public static function getByAddress(Database $db, string $link_url): Instance
    {
        $data = $db->select(
            'SELECT i.id AS instance_id, i.domain, i.`primary`, i.secondary, i.render, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, i.private_key, i.public_key, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.link = :L;',
            ['L' => $link_url]
        );
        if ($data)
            return Instance::raw(
                id: $data['instance_id'],
                domain: $data['domain'],
                link: $link_url,
                primary: $data['primary'],
                secondary: $data['secondary'],
                render: RichDisplayInstanceType::from($data['render']),
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
                last_edit_date: $data['last_edit_date'],
                blocked: boolval($data['blocked']),
                sticker_path: $data['sticker_path'],
                sticker_link: $data['sticker_link'],
                display_sticker: boolval($data['display_sticker']),
                public_key: $data['public_key'],
                private_key: $data['private_key']
            );
        else throw new Exception('Instance with the given link does not exist');
    }
    /** @return Instance[] */
    public static function getAll(Database $db, bool $only_valid_links = false): array
    {
        $data = $db->selectAll('SELECT i.id, i.domain, i.link, i.`primary`, i.secondary, i.render, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device' . ($only_valid_links ? ' WHERE i.valid_link = 1' : '') . ';');
        return array_map(function (array $row) {
            return Instance::raw(
                id: $row['id'],
                domain: $row['domain'],
                link: $row['link'],
                primary: $row['primary'],
                secondary: $row['secondary'],
                render: RichDisplayInstanceType::from($row['render']),
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
                last_edit_date: $row['last_edit_date'],
                blocked: boolval($row['blocked']),
                sticker_path: $row['sticker_path'],
                sticker_link: $row['sticker_link'],
                display_sticker: boolval($row['display_sticker'])
            );
        }, $data);
    }
    /** @return Instance[] */
    public static function getStickers(Database $db): array
    {
        $data = $db->selectAll('SELECT i.id, i.domain, i.link, i.`primary`, i.secondary, i.render, i.valid_link, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device, i.last_fetch_date, i.last_edit_date, i.blocked, i.sticker_path, i.sticker_link, i.display_sticker, d.name, d.first_login, d.last_login FROM Instance i INNER JOIN Device d ON d.id = i.from_device WHERE i.display_sticker = 1;');
        return array_map(function (array $row) {
            return Instance::raw(
                id: $row['id'],
                domain: $row['domain'],
                link: $row['link'],
                primary: $row['primary'],
                secondary: $row['secondary'],
                render: RichDisplayInstanceType::from($row['render']),
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
                last_edit_date: $row['last_edit_date'],
                blocked: boolval($row['blocked']),
                sticker_path: $row['sticker_path'],
                sticker_link: $row['sticker_link'],
                display_sticker: boolval($row['display_sticker'])
            );
        }, $data);
    }
    public static function create(Database $db, string $domain, string $link, string $primary, string $secondary, RichDisplayInstanceType $render, bool $valid_link, ?string $sticker_path, ?string $sticker_link, ?LinkStatus $status = null, ?string $public_key = null): Instance
    {
        $device = DeviceDAO::getCurrent($db) ?? DeviceDAO::getRemote($db);
        $private_key = $public_key_return = null;
        if (is_null($public_key)) {
            // If `openssl_pkey_new(...)` and `openssl_pkey_export(...)` don't work, add the path to _openssl.cnf_ to either your system environment variable and restart
            // your computer, or add ['config' => 'path/to/openssl.cnf'] to their `$options` argument
            $rsa = openssl_pkey_new(['digest_alg' => 'sha512', 'private_key_bits' => 4096, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
            if (!openssl_pkey_export($rsa, $private_key, null)) throw new Exception('Creating Instance failed, could not export private key');
            $public_key_return = openssl_pkey_get_details($rsa)['key'];
        }
        $date = date('Y-m-d H:i:s');
        $id = $db->insert(
            'INSERT INTO Instance (domain, link, `primary`, secondary, render, valid_link, sticker_path, sticker_link, first_link_date, from_device, last_edit_date, public_key, private_key) VALUES (:D, :N, :P, :S, :R, :V, :H, :K, :L, :F, :L, :Y, :Z);',
            ['D' => $domain, 'N' => $link, 'P' => $primary, 'S' => $secondary, 'R' => $render->value, 'V' => $valid_link, 'H' => $sticker_path, 'K' => $sticker_link, 'L' => $date, 'F' => $device->getId(), 'Y' => $public_key, 'Z' => $private_key],
            'Instance'
        );
        return Instance::raw(
            id: $id,
            domain: $domain,
            link: $link,
            primary: $primary,
            secondary: $secondary,
            render: $render,
            valid_link: $valid_link,
            first_link_date: $date,
            last_link_date: null,
            last_link_status: $status->value ?? -1,
            from_device: $device,
            last_fetch_date: null,
            last_edit_date: $date,
            blocked: false,
            sticker_path: $sticker_path,
            sticker_link: $sticker_link,
            display_sticker: false,
            public_key: $public_key_return ?? $public_key,
            private_key: $private_key
        );
    }
    public static function createFromFederationInfo(Database $db, array $federation_info, bool $valid_link, ?string $public_key = null, ?LinkStatus $status = null): Instance
    {
        return self::create(
            db: $db,
            domain: $federation_info['domain'],
            link: $federation_info['link'],
            primary: $federation_info['primary'],
            secondary: $federation_info['secondary'],
            render: RichDisplayInstanceType::from(intval($federation_info['render'])),
            valid_link: $valid_link,
            sticker_path: $federation_info['sticker_path'] ?? null,
            sticker_link: $federation_info['sticker_link'] ?? null,
            status: $status,
            public_key: $public_key
        );
    }
}
