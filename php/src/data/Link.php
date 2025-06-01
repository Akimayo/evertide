<?php

declare(strict_types=1);
require_once(__DIR__ . '/DaoAccessible.php');
require_once(__DIR__ . '/../database.php');

class Link implements DaoAccessible
{
    protected int $id;
    protected string $url;
    protected string $title;
    protected string $blurhash;
    protected ?string $name;
    protected ?string $favicon;

    public function __construct(int $id, string $url, string $title, string $blurhash, ?string $name, ?string $favicon)
    {
        $this->id = $id;
        $this->url = $url;
        $this->title = $title;
        $this->blurhash = $blurhash;
        $this->name = $name;
        $this->favicon = $favicon;
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
    public function getBlurHash(): string
    {
        return $this->blurhash;
    }
    public function getName(): ?string
    {
        return $this->name;
    }
    public function getFavicon(): ?string
    {
        return $this->favicon;
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
            favicon: $this->favicon
        );
    }
}
class LinkDAO extends Link implements DAO
{
    private Database $db;
    public function __construct(Database $db, int $id, string $url, string $title, string $blurhash, ?string $name, ?string $favicon)
    {
        $this->db = $db;
        parent::__construct($id, $url, $title, $blurhash, $name, $favicon);
    }

    public static function get(Database $db, int|string $key): Link
    {
        throw new Exception('not implemented'); // FIXME:
    }
    /** @return Link[] */
    public static function getAll(Database $db): array
    {
        throw new Exception('not implemented'); // FIXME:
    }
}
