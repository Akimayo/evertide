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
    protected array $links;
    protected ?Instance $source;

    /**
     * @param  int $id
     * @param  string $name
     * @param  string $icon
     * @param  Link[] $links
     * @param  ?Instance $source
     */
    public function __construct(int $id, string $name, string $icon, array $links, ?Instance $source)
    {
        $this->id = $id;
        $this->name = $name;
        $this->icon = $icon;
        $this->links = $links;
        $this->source = $source;
    }

    /** @return LeafCategoryDAO */
    public function getAccessObject(Database $db): DAO
    {
        return new LeafCategoryDAO(
            db: $db,
            id: $this->id,
            name: $this->name,
            icon: $this->icon,
            links: array_map(function (Link $link) use ($db): LinkDAO {
                return $link->getAccessObject($db);
            }, $this->links),
            source: $this->source?->getAccessObject($db)
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
    /** @return Link[] */
    public function getLinks(): array
    {
        return $this->links;
    }
    public function getSourceInstance(): ?Instance
    {
        return $this->source;
    }
}
class LeafCategoryDAO extends LeafCategory implements DAO
{
    private Database $db;
    /**
     * @param  Database $db
     * @param  string $name
     * @param  string $icon
     * @param  LinkDAO[] $links
     * @param  ?InstanceDAO $source
     */
    public function __construct(Database $db, int $id, string $name, string $icon, array $links, ?InstanceDAO $source)
    {
        $this->db = $db;
        parent::__construct($id, $name, $icon, $links, $source);
    }
    /** @return LinkDAO[] */
    public function getLinks(): array
    {
        return $this->links;
    }
    /** @return ?InstanceDAO */
    public function getSourceInstance(): ?InstanceDAO
    {
        return $this->source;
    }
    public static function get(Database $db, int|string $key): LeafCategory
    {
        throw new Exception('not implemented'); // FIXME:
    }
    /** @return LeafCategory[] */
    public static function getAll(Database $db): array
    {
        throw new Exception('not implemented'); // FIXME:
    }
}
class Category extends LeafCategory implements DaoAccessible
{
    protected array $categories;
    /**
     * @param  string $name
     * @param  string $icon
     * @param  Link[] $links
     * @param  CategoryLeaf[] $categories
     * @param  ?Instance $source
     */
    public function __construct(int $id, string $name, string $icon, array $links, array $categories, ?Instance $source)
    {
        $this->categories = $categories;
        parent::__construct($id, $name, $icon, $links, $source);
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
            links: array_map(function (Link $link) use ($db) {
                return $link->getAccessObject($db);
            }, $this->links),
            categories: array_map(function (LeafCategory $cat) use ($db) {
                return $cat->getAccessObject($db);
            }, $this->categories),
            source: $this->source->getAccessObject($db)
        );
    }
}
class CategoryDAO extends Category implements DAO
{
    private Database $db;
    /**
     * @param  Database $db
     * @param  int $id
     * @param  string $name
     * @param  string $icon
     * @param  LinkDAO[] $links
     * @param  CategoryLeafDAO[] $categories
     * @param  ?InstanceDAO $source
     */
    public function __construct(Database $db, int $id, string $name, string $icon, array $links, array $categories, ?InstanceDAO $source)
    {
        $this->db = $db;
        parent::__construct($id, $name, $icon, $links, $categories, $source);
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
    public function getSourceInstance(): ?InstanceDAO
    {
        return $this->source;
    }
    public static function get(Database $db, int|string $key): Category
    {
        throw new Exception('not implemented'); // FIXME:
    }
    /** @return Category[] */
    public static function getAll(Database $db): array
    {
        throw new Exception('not implemented'); // FIXME:
    }
}
