<?php
require_once(__DIR__ . '/DaoAccessible.php');
require_once(__DIR__ . '/Link.php');
require_once(__DIR__ . '/Instance.php');
require_once(__DIR__ . '/../database.php');

class LeafCategory implements DaoAccessible
{
    protected int $id;
    protected string $name;
    protected string $icon;
    protected ?Instance $source;
    protected bool $public;
    protected string $create_date;
    protected string $update_date;
    protected Device $from_device;
    protected array $links;
    protected ?int $source_id;

    /** @param Link[] $links */
    public function __construct(int $id, string $name, string $icon, ?Instance $source, bool $public, string $create_date, string $update_date, Device $from_device, array $links, ?int $source_id = null)
    {
        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->source = $source;
        $this->public = $public;
        $this->create_date = $create_date;
        $this->update_date = $update_date;
        $this->from_device = $from_device;
        $this->links = $links;
        $this->source_id = $source_id;
    }

    /** @return LeafCategoryDAO */
    public function getAccessObject(Database $db): DAO
    {
        return new LeafCategoryDAO(
            db: $db,
            id: $this->id,
            name: $this->name,
            icon: $this->icon,
            source: $this->source?->getAccessObject($db),
            public: $this->public,
            create_date: $this->create_date,
            update_date: $this->update_date,
            from_device: $this->from_device->getAccessObject($db),
            links: array_map(function (Link $link) use ($db): LinkDAO {
                return $link->getAccessObject($db);
            }, $this->links),
            source_id: $this->source_id
        );
    }

    public function getId(): int
    {
        return $this->id;
    }
    public function getName(): string
    {
        return $this->name;
    }
    public function getIcon(): string
    {
        return $this->icon;
    }
    public function getSourceInstance(): ?Instance
    {
        return $this->source;
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
    /** @return Link[] */
    public function getLinks(): array
    {
        return $this->links;
    }
    public function getSourceId(): ?int
    {
        return $this->source_id;
    }
}
class LeafCategoryDAO extends LeafCategory implements DAO
{
    private Database $db;
    /** @param  LinkDAO[] $links */
    public function __construct(Database $db, int $id, string $name, string $icon, ?InstanceDAO $source, bool $public, string $create_date, string $update_date, DeviceDAO $from_device, array $links, ?int $source_id = null)
    {
        $this->db = $db;
        parent::__construct($id, $name, $icon, $source, $public, $create_date, $update_date, $from_device, $links, $source_id);
    }

    /** @return LinkDAO[] */
    public function getLinks(): array
    {
        return $this->links;
    }
    /** @return ?InstanceDAO */
    public function getSourceInstance(): ?Instance
    {
        return $this->source;
    }
    /** @return DeviceDAO */
    public function getAuthorDevice(): Device
    {
        return $this->from_device;
    }

    public function updateParent(?LeafCategory $parent): self
    {
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->db->update('UPDATE Category SET parent = :P, update_date = :D WHERE id = :I;', ['P' => $parent?->getId(), 'D' => $date, 'I' => $this->id]) &&
            ($parent === null || $this->db->update('UPDATE Category SET update_date = :D WHERE id = :J;', ['D' => $date, 'J' => $parent->getId()]))
        ) {
            $this->update_date = $date;
            $this->db->commit();
            return $this;
        } else {
            $this->db->rollback();
            throw new Exception('Updating Category.parent and Category.update_date failed');
        }
    }
    public function update(string $name, string $icon, ?bool $public = null): self
    {
        $public ??= $this->public;
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->db->update('UPDATE Category SET name = :N, icon = :C, public = :P, update_date = :D WHERE id = :I;', ['N' => $name, 'C' => $icon, 'P' => $public, 'D' => $date, 'I' => $this->id]) &&
            $this->db->update('UPDATE Category SET update_date = :D WHERE id = (SELECT parent FROM Category WHERE id = :I);', ['D' => $date, 'I' => $this->id])
        ) {
            $this->name = $name;
            $this->icon = $icon;
            $this->public = $public;
            $this->update_date = $date;
            $this->db->commit();
            return $this;
        } else {
            $this->db->rollback();
            throw new Exception('Updating Category.name, Category.icon, Category.public and Category.update_date failed');
        }
    }
    public function delete(): void
    {
        $date = date('Y-m-d H:i:s');
        $this->db->begin();
        if (
            $this->db->update('UPDATE Category SET update_date = :D WHERE id = (SELECT parent FROM Category WHERE id = :I);', ['D' => $date, 'I' => $this->id]) &&
            $this->db->delete('DELETE FROM Category WHERE id = :I;', ['I' => $this->id]) &&
            $this->db->insert('INSERT INTO DeletedItems (type, id, from_device, delete_date) VALUES (0, :I, :F, :D);', ['I' => $this->id, 'F' => DeviceDAO::getCurrent($this->db)->getId(), 'D' => $date])
        ) {
            $this->db->commit();
            return;
        } else throw new Exception('Deleting Category failed');
    }

    public static function get(Database $db, int|string $key): LeafCategory
    {
        throw new Exception('Getting leaf categories not supported, please use Category::get(...)');
    }
    /** @return LeafCategory[] */
    public static function getAll(Database $db): array
    {
        throw new Exception('Getting leaf categories not supported, please use Category::getAll(...)');
    }
    /** @return int[] */
    public static function getDeletedIds(Database $db, string $since): array {
        return array_column($db->selectAll('SELECT id FROM DeletedItems WHERE type = 0 AND delete_date > :D;', ['D' => $since]), 'id');
    }
    public function createLink(string $url, string $title, ?string $blurhash, ?string $favicon, ?int $source_id = null): LinkDAO
    {
        $date = date('Y-m-d H:i:s');
        $device = DeviceDAO::getCurrent($this->db);
        $this->db->begin();
        $id = $this->db->insert(
            'INSERT INTO Link (url, title, blurhash, favicon, category, create_date, update_date, from_device, public, source_id) VALUES (:U, :T, :B, :F, :C, :D, :D, :R, :P, :S);',
            ['U' => $url, 'T' => $title, 'B' => $blurhash, 'F' => $favicon, 'C' => $this->id, 'D' => $date, 'R' => $device->getId(), 'P' => $this->public, 'S' => $source_id],
            'Link'
        );
        if (
            $id !== false &&
            ($cat = $this->db->select('SELECT parent FROM Category WHERE id = :I;', ['I' => $this->id])) !== false &&
            $this->db->update('UPDATE Category SET update_date = :D WHERE id IN (:I, :J);', ['D' => $date, 'I' => $this->id, 'J' => $cat['parent'] ?? -1])
        ) {
            $instance = new LinkDAO(
                db: $this->db,
                id: $id,
                url: $url,
                title: $title,
                blurhash: $blurhash,
                name: null,
                description: null,
                favicon: $favicon,
                public: $this->public,
                create_date: $date,
                update_date: $date,
                from_device: $device->getAccessObject($this->db),
                source_id: $source_id
            );
            $this->links[] = $instance;
            $this->db->commit();
            return $instance;
        } else {
            $this->db->rollback();
            throw new Exception('Creating link failed');
        }
    }
}
class Category extends LeafCategory implements DaoAccessible
{
    protected array $categories;
    /**
     * @param  Link[] $links
     * @param  CategoryLeaf[] $categories
     */
    public function __construct(int $id, string $name, string $icon, ?Instance $source, bool $public, string $create_date, string $update_date, Device $from_device, array $links, array $categories, ?int $source_id = null)
    {
        $this->categories = $categories;
        parent::__construct($id, $name, $icon, $source, $public, $create_date, $update_date, $from_device, $links, $source_id);
    }

    /** @return CategoryLeaf[] */
    public function getCategories(): array
    {
        return $this->categories;
    }

    /** @return CategoryDAO */
    public function getAccessObject(Database $db): DAO
    {
        return new CategoryDAO(
            db: $db,
            id: $this->id,
            name: $this->name,
            icon: $this->icon,
            source: $this->source?->getAccessObject($db),
            public: $this->public,
            create_date: $this->create_date,
            update_date: $this->update_date,
            from_device: $this->from_device->getAccessObject($db),
            links: array_map(function (Link $link) use ($db) {
                return $link->getAccessObject($db);
            }, $this->links),
            categories: array_map(function (LeafCategory $cat) use ($db) {
                return $cat->getAccessObject($db);
            }, $this->categories),
            source_id: $this->source_id
        );
    }
}
class CategoryDAO extends LeafCategoryDAO implements DAO
{
    private array $categories;
    /**
     * @param  LinkDAO[] $links
     * @param  CategoryLeafDAO[] $categories
     */
    public function __construct(Database $db, int $id, string $name, string $icon, ?InstanceDAO $source, bool $public, string $create_date, string $update_date, DeviceDAO $from_device, array $links, array $categories, ?int $source_id = null)
    {
        $this->categories = $categories;
        parent::__construct($db, $id, $name, $icon, $source, $public, $create_date, $update_date, $from_device, $links, $source_id);
    }
    /** @return LinkDAO[] */
    public function getLinks(): array
    {
        return $this->links;
    }
    /** @return CategoryDAO[] */
    public function getCategories(): array
    {
        return $this->categories;
    }
    /** @return ?InstanceDAO */
    public function getSourceInstance(): ?Instance
    {
        return $this->source;
    }
    /** @return DeviceDAO */
    public function getAuthorDevice(): Device
    {
        return $this->from_device;
    }

    public const SELECT = <<<PHP_EOL
    SELECT l.id AS link_id, l.url, l.title, l.blurhash, l.name AS link_name, l.description, l.favicon, l.create_date AS link_create_date, l.update_date AS link_update_date, l.from_device AS link_device, l.public AS link_public, l.source_id AS link_source_id,
           dl.name AS link_device_name, dl.first_login AS link_device_first_login, dl.last_login AS link_device_last_login,
           c.id AS category_id, c.name AS category_name, c.icon AS category_icon, c.source AS category_source, c.create_date AS category_create_date, c.update_date AS category_update_date, c.from_device AS category_device, c.public AS category_public, c.source_id AS category_source_id,
           dc.name AS category_device_name, dc.first_login AS category_device_first_login, dc.last_login AS category_device_last_login,
           i.domain, i.link, i.`primary`, i.secondary, i.first_link_date, i.last_link_date, i.last_link_status, i.from_device AS instance_device, i.last_fetch_date, i.blocked,
           di.name AS instance_device_name, di.first_login AS instance_device_first_login, di.last_login AS instance_device_last_login,
           p.id AS parent_id, p.name AS parent_name, p.icon AS parent_icon, p.create_date AS parent_create_date, p.update_date AS parent_update_date, p.from_device AS parent_device, p.public AS parent_public, p.source_id AS parent_source_id,
           dp.name AS parent_device_name, dp.first_login AS parent_device_first_login, dp.last_login AS parent_device_last_login
      FROM Category c
      INNER JOIN Device dc  ON dc.id = c.from_device
      LEFT JOIN Link l     ON  c.id = l.category
      LEFT JOIN Device dl  ON dl.id = l.from_device
      LEFT JOIN Instance i ON  i.id = c.source
      LEFT JOIN Device di  ON di.id = i.from_device
      LEFT JOIN Category p ON  p.id = c.parent
      LEFT JOIN Device dp  ON dp.id = p.from_device
    PHP_EOL;
    public const ORDER = <<<PHP_EOL
     ORDER BY c.source IS NULL DESC, IFNULL(p.id, c.id) ASC, p.id IS NULL ASC, p.id ASC, c.id ASC, l.id ASC;
    PHP_EOL;
    /** @return Category[] */
    public static function __mapInstances(array $rows, bool $only_non_empty): array
    {
        $instances = [];
        $lastParent = null;
        $lastParentId = -1;
        $lastParentAddedId = -1;
        $categories = [];
        $lastCategory = null;
        $lastCategoryId = -1;
        $links = [];
        $lastSource = null;
        $lastSourceId = -1;

        ob_start();
        echo '<pre>';
        foreach ($rows as $row) {
            if ($lastParentId != $row['parent_id'] && $lastParentId != $row['category_id']) {
                if ($lastParent !== null && $lastParentId != $lastParentAddedId) {
                    if (!$only_non_empty || !(empty($links) && empty($categories)))
                        $instances[] = new Category(
                            id: $lastParentId,
                            name: $lastParent['parent_name'],
                            icon: $lastParent['parent_icon'],
                            source: $lastSource,
                            public: boolval($lastParent['parent_public']),
                            create_date: $lastParent['parent_create_date'],
                            update_date: $lastParent['parent_update_date'],
                            from_device: new Device(
                                id: $lastParent['parent_device'],
                                name: $lastParent['parent_device_name'],
                                first_login: $lastParent['parent_device_first_login'],
                                last_login: $lastParent['parent_device_last_login']
                            ),
                            links: $links,
                            categories: $categories,
                            source_id: $lastParent['parent_source_id']
                        );

                    echo 'added category "' . $lastParent['parent_name'] . '" (from parent)' . PHP_EOL;

                    $links = [];
                    $categories = [];
                    $lastParentAddedId = $lastParentId;
                }
                if ($row['parent_id'] !== null) {
                    $lastParent = $row;
                    $lastParentId = $row['parent_id'];
                }
            }
            if ($lastCategoryId != $row['category_id']) {
                if ($lastCategory !== null) {
                    if ($lastCategory['parent_id'] === $lastParentId && $lastCategory['parent_id'] !== null) {
                        if (!$only_non_empty || !empty($links))
                            $categories[] = new LeafCategory(
                                id: $lastCategory['category_id'],
                                name: $lastCategory['category_name'],
                                icon: $lastCategory['category_icon'],
                                source: $lastSource,
                                public: boolval($lastCategory['category_public']),
                                create_date: $lastCategory['category_create_date'],
                                update_date: $lastCategory['category_update_date'],
                                from_device: new Device(
                                    id: $lastCategory['category_device'],
                                    name: $lastCategory['category_device_name'],
                                    first_login: $lastCategory['category_device_first_login'],
                                    last_login: $lastCategory['category_device_last_login']
                                ),
                                links: $links,
                                source_id: $lastCategory['category_source_id']
                            );
                        echo 'added category "' . $lastCategory['category_name'] . '" to "' . $lastParent['parent_name'] . '"' . PHP_EOL;
                    } else if ($lastCategoryId != $lastParentAddedId) {
                        if (!$only_non_empty || !(empty($links) && empty($categories)))
                            $instances[] = new Category(
                                id: $lastCategory['category_id'],
                                name: $lastCategory['category_name'],
                                icon: $lastCategory['category_icon'],
                                source: $lastSource,
                                public: boolval($lastCategory['category_public']),
                                create_date: $lastCategory['category_create_date'],
                                update_date: $lastCategory['category_update_date'],
                                from_device: new Device(
                                    id: $lastCategory['category_device'],
                                    name: $lastCategory['category_device_name'],
                                    first_login: $lastCategory['category_device_first_login'],
                                    last_login: $lastCategory['category_device_last_login']
                                ),
                                links: $links,
                                categories: $categories,
                                source_id: $lastCategory['category_source_id']
                            );
                        $categories = [];
                        echo 'added category "' . $lastCategory['category_name'] . '" (from alone)' . PHP_EOL;
                    }
                    $links = [];
                }
                $lastCategory = $row;
                $lastCategoryId = $row['category_id'];
            }
            if ($lastSourceId !== $row['category_source']) {
                $sourceRow = $lastCategory === null ? $lastParent : $lastCategory;
                $lastSource = is_null($sourceRow['category_source']) ? null : Instance::raw(
                    id: $sourceRow['category_source'],
                    domain: $sourceRow['domain'],
                    link: $sourceRow['link'],
                    primary: $sourceRow['primary'],
                    secondary: $sourceRow['secondary'],
                    first_link_date: $sourceRow['first_link_date'],
                    last_link_date: $sourceRow['last_link_date'],
                    last_link_status: $sourceRow['last_link_status'],
                    from_device: new Device(
                        id: $sourceRow['instance_device'],
                        name: $sourceRow['instance_device_name'],
                        first_login: $sourceRow['instance_device_first_login'],
                        last_login: $sourceRow['instance_device_last_login']
                    ),
                    last_fetch_date: $sourceRow['last_fetch_date'],
                    blocked: $sourceRow['blocked']
                );
            }

            if ($row['link_id'] !== null) {
                $links[] = new Link(
                    id: $row['link_id'],
                    url: $row['url'],
                    title: $row['title'],
                    blurhash: $row['blurhash'],
                    name: $row['link_name'],
                    description: $row['description'],
                    favicon: $row['favicon'],
                    public: boolval($row['link_public']),
                    create_date: $row['link_create_date'],
                    update_date: $row['link_update_date'],
                    from_device: new Device(
                        id: $row['link_device'],
                        name: $row['link_device_name'],
                        first_login: $row['link_device_first_login'],
                        last_login: $row['link_device_last_login']
                    ),
                    source_id: $row['link_source_id']
                );
                echo 'added link "' . $row['title'] . '" to "' . $lastCategory['category_name'] . '"' . PHP_EOL;
            }
        }
        if ($lastCategory !== null) {
            if ($lastCategory['parent_id'] === $lastParentId && $lastCategory['parent_id'] !== null) {
                if (!$only_non_empty || !empty($links))
                    $categories[] = new LeafCategory(
                        id: $lastCategory['category_id'],
                        name: $lastCategory['category_name'],
                        icon: $lastCategory['category_icon'],
                        source: $lastSource,
                        public: boolval($lastCategory['category_public']),
                        create_date: $lastCategory['category_create_date'],
                        update_date: $lastCategory['category_update_date'],
                        from_device: new Device(
                            id: $lastCategory['category_device'],
                            name: $lastCategory['category_device_name'],
                            first_login: $lastCategory['category_device_first_login'],
                            last_login: $lastCategory['category_device_last_login']
                        ),
                        links: $links,
                        source_id: $lastCategory['category_source_id']
                    );
                echo 'added category "' . $lastCategory['category_name'] . '" to "' . $lastParent['parent_name'] . '"' . PHP_EOL;
            } else if ($lastCategoryId != $lastParentAddedId) {
                if (!$only_non_empty || !(empty($links) && empty($categories)))
                    $instances[] = new Category(
                        id: $lastCategory['category_id'],
                        name: $lastCategory['category_name'],
                        icon: $lastCategory['category_icon'],
                        source: $lastSource,
                        public: boolval($lastCategory['category_public']),
                        create_date: $lastCategory['category_create_date'],
                        update_date: $lastCategory['category_update_date'],
                        from_device: new Device(
                            id: $lastCategory['category_device'],
                            name: $lastCategory['category_device_name'],
                            first_login: $lastCategory['category_device_first_login'],
                            last_login: $lastCategory['category_device_last_login']
                        ),
                        links: $links,
                        categories: $categories,
                        source_id: $lastCategory['category_source_id']
                    );
                $lastParentAddedId = $lastCategory['category_id'];
                echo 'added category "' . $lastCategory['category_name'] . '"' . PHP_EOL;
            }
            $links = [];
        }
        if ($lastParent !== null && $lastParentId != $lastParentAddedId && $lastCategoryId != $lastParentAddedId) {
            if (!$only_non_empty || !(empty($links) && empty($categories)))
                $instances[] = new Category(
                    id: $lastParentId,
                    name: $lastParent['parent_name'],
                    icon: $lastParent['parent_icon'],
                    source: $lastSource,
                    public: boolval($lastParent['parent_public']),
                    create_date: $lastParent['parent_create_date'],
                    update_date: $lastParent['parent_update_date'],
                    from_device: new Device(
                        id: $lastParent['parent_device'],
                        name: $lastParent['parent_device_name'],
                        first_login: $lastParent['parent_device_first_login'],
                        last_login: $lastParent['parent_device_last_login']
                    ),
                    links: $links,
                    categories: $categories,
                    source_id: $lastParent['parent_source_id']
                );
            echo 'added category "' . $lastParent['parent_name'] . '"' . PHP_EOL;
        }
        echo '</pre>';

        // Set to true to enable debug logging
        if (false) ob_end_flush();
        else ob_end_clean();

        return $instances;
    }
    public static function get(Database $db, int|string $key): Category
    {
        $data = $db->selectAll(self::SELECT . ' WHERE c.id = :I ' . self::ORDER, ['I' => $key]);
        if ($data && ($instances = self::__mapInstances($data, false)) && count($instances) > 0) return $instances[0];
        else throw new Exception('Category with the given id does not exist');
    }
    /** @return Category[] */
    public static function getAll(Database $db, bool $includePrivate = false, bool $includeEmpty = false): array
    {
        $data = $db->selectAll(self::SELECT . ($includePrivate ? '' : ' WHERE l.public = 1 ') . self::ORDER);
        if ($data) return self::__mapInstances($data, !$includeEmpty);
        else return [];
    }
    /** @return Category[] */
    public static function getAllLocal(Database $db, bool $includePrivate = false, bool $includeEmpty = false): array
    {
        $data = $db->selectAll(self::SELECT . ' WHERE c.source IS NULL ' . ($includePrivate ? '' : 'AND l.public > 0 ') . self::ORDER);
        if ($data) return self::__mapInstances($data, !$includeEmpty);
        else return [];
    }
    /** @return Category[] */
    public static function getAllFromRemote(Database $db, Instance $remote): array
    {
        $data = $db->selectAll(self::SELECT . ' WHERE c.source = :S ' . self::ORDER, ['S' => $remote->getId()]);
        if ($data) return self::__mapInstances($data, false);
        else return [];
    }
    /** @return Category[] */
    public static function getSync(Database $db, array $category_ids, string $last_sync): array
    {
        $data = $db->selectAll(self::SELECT . ' WHERE c.source IS NULL AND l.public > 0 AND l.update_date > :D AND c.id IN (:I) ' . self::ORDER, ['D' => $last_sync, 'I' => implode(', ', $category_ids)]);
        if ($data) return self::__mapInstances($data, false);
        else return [];
    }
    public static function create(Database $db, string $name, string $icon, bool $public, ?Instance $source = null, ?int $source_id = null)
    {
        $date = date('Y-m-d H:i:s');
        $device = DeviceDAO::getCurrent($db);
        $id = $db->insert(
            'INSERT INTO Category (name, icon, source, create_date, update_date, from_device, public, source_id) VALUES (:N, :C, :S, :D, :D, :F, :P, :I);',
            ['N' => $name, 'C' => $icon, 'S' => $source?->getId(), 'D' => $date, 'F' => $device->getId(), 'P' => $public, 'I' => $source_id],
            'Category'
        );
        if ($id !== false)
            return new Category(
                id: $id,
                name: $name,
                icon: $icon,
                source: $source,
                public: $public,
                create_date: $date,
                update_date: $date,
                from_device: $device,
                links: [],
                categories: [],
                source_id: $source_id
            );
        else throw new Exception('Creating category failed');
    }
}
