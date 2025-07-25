<?php

declare(strict_types=1);
require_once(__DIR__ . '/DaoAccessible.php');
require_once(__DIR__ . '/../database.php');

class Link implements DaoAccessible
{
    protected int $id;
    protected string $url;
    protected string $title;
    protected ?string $blurhash;
    protected ?string $name;
    protected ?string $favicon;
    protected string $create_date;
    protected string $update_date;
    protected Device $from_device;
    protected bool $public;
    protected ?string $description;
    protected ?int $source_id;

    public function __construct(int $id, string $url, string $title, ?string $blurhash, ?string $name, ?string $description, ?string $favicon, bool $public, string $create_date, string $update_date, Device $from_device, ?int $source_id = null)
    {
        $this->id = $id;
        $this->url = $url;
        $this->title = $title;
        $this->blurhash = $blurhash;
        $this->name = $name;
        $this->description = $description;
        $this->favicon = $favicon;
        $this->public = $public;
        $this->create_date = $create_date;
        $this->update_date = $update_date;
        $this->from_device = $from_device;
        $this->source_id = $source_id;
    }

    public function getId(): int
    {
        return $this->id;
    }
    public function getUrl(): string
    {
        return $this->url;
    }
    public function getTitle(): string
    {
        return $this->title;
    }
    public function getBlurHash(): ?string
    {
        return $this->blurhash;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function getDescription(): ?string
    {
        return $this->description;
    }
    public function getFavicon(): ?string
    {
        return $this->favicon;
    }
    public function isPublic(): bool
    {
        return $this->public;
    }
    public function getCreationDate(): string
    {
        return $this->create_date;
    }
    public function getUpdateDate(): string
    {
        return $this->update_date;
    }
    public function getAuthorDevice(): Device
    {
        return $this->from_device;
    }
    public function getSourceId(): ?int
    {
        return $this->source_id;
    }

    public function getAccessObject(Database $db): DAO
    {
        return new LinkDAO(
            db: $db,
            id: $this->id,
            url: $this->url,
            title: $this->title,
            blurhash: $this->blurhash,
            name: $this->name,
            description: $this->description,
            favicon: $this->favicon,
            public: $this->public,
            create_date: $this->create_date,
            update_date: $this->update_date,
            from_device: $this->from_device->getAccessObject($db),
            source_id: $this->source_id
        );
    }
}
class LinkDAO extends Link implements DAO
{
    private Database $db;
    public function __construct(Database $db, int $id, string $url, string $title, ?string $blurhash, ?string $name, ?string $description, ?string $favicon, bool $public, string $create_date, string $update_date, DeviceDAO $from_device, ?int $source_id = null)
    {
        $this->db = $db;
        parent::__construct($id, $url, $title, $blurhash, $name, $description, $favicon, $public, $create_date, $update_date, $from_device, $source_id);
    }
    /** @return DeviceDAO */
    public function getAuthorDevice(): Device
    {
        return $this->from_device;
    }

    private function updateCategories(string $date): bool
    {
        return ($cat = $this->db->select('SELECT c.id, c.parent FROM Link l INNER JOIN Category c ON c.id = l.category WHERE l.id = :I', ['I' => $this->id])) !== false &&
            $this->db->update('UPDATE Category SET update_date = :D WHERE id IN (:I, :J);', ['D' => $date, 'I' => $cat['id'], 'J' => $cat['parent'] ?? -1]);
    }
    public function updateParent(LeafCategory $parent): self
    {
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->updateCategories($date) &&
            $this->db->update('UPDATE Link SET category = :C WHERE id = :I;', ['C' => $parent->getId(), 'I' => $this->id]) &&
            $this->updateCategories($date)
        ) {
            $this->update_date = $date;
            $this->db->commit();
            return $this;
        } else {
            $this->db->rollback();
            throw new Exception('Updating Link.category and Category.update_date failed');
        }
    }
    public function update(?string $name, ?string $description, bool $public): self
    {
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->db->update('UPDATE Link SET name = :N, description = :O, public = :P, update_date = :D WHERE id = :I;', ['N' => $name, 'O' => $description, 'P' => $public, 'D' => $date, 'I' => $this->id]) &&
            $this->updateCategories($date)
        ) {
            $this->name = $name;
            $this->description = $description;
            $this->public = $public;
            $this->update_date = $date;
            $this->db->commit();
            return $this;
        } else {
            $this->db->rollback();
            throw new Exception('Updating Link.name, Link.description, Link.update_date and Category.update_date failed');
        }
    }
    public function updateSiteInfo(string $title, string $blurhash, ?string $favicon): self
    {
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->db->update('UPDATE Link SET title = :T, blurhash = :B, favicon = :F, update_date = :D WHERE id = :I;', ['T' => $title, 'B' => $blurhash, 'F' => $favicon, 'D' => $date, 'I' => $this->id]) &&
            $this->updateCategories($date)
        ) {
            $this->title = $title;
            $this->blurhash = $blurhash;
            $this->favicon = $favicon;
            $this->update_date = $date;
            $this->db->commit();
            return $this;
        } else {
            $this->db->rollback();
            throw new Exception('Updating Link.title, Link.blurhash, Link.favicon, Link.update_date and Category.update_date failed');
        }
    }
    public function delete(): void
    {
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->updateCategories($date) &&
            $this->db->delete('DELETE FROM Link WHERE id = :I;', ['I' => $this->id]) &&
            $this->db->insert('INSERT INTO DeletedItems (type, id, from_device, delete_date) VALUES (1, :I, :F, :D);', ['I' => $this->id, 'F' => DeviceDAO::getCurrent($this->db)->getId(), 'D' => $date])
        ) {
            $this->db->commit();
        } else {
            $this->db->rollback();
            throw new Exception('Deleting Link failed');
        }
    }

    /** @return Category */
    public static function get(Database $db, int|string $key): DaoAccessible
    {
        $data = $db->selectAll(CategoryDAO::SELECT . ' WHERE l.id = :I ' . CategoryDAO::ORDER, ['I' => $key]);
        if ($data && ($instances = CategoryDAO::__mapInstances($data, false)) && count($instances) > 0) return $instances[0];
        else throw new Exception('Link with the given id does not exist');
    }
    /** @return Link[] */
    public static function getAll(Database $db): array
    {
        throw new Exception('Getting all links not supported, please use Category::getAll(...)');
    }
    /** @return int[] */
    public static function getDeletedIds(Database $db, string $since): array
    {
        return array_column($db->selectAll('SELECT id FROM DeletedItems WHERE type = 1 AND delete_date > :D;', ['D' => $since]), 'id');
    }
}
