<?php

declare(strict_types=1);
interface DaoAccessible
{
    function getAccessObject(Database $db): DAO;
}
interface DAO
{
    static function get(Database $db, int|string $key): DaoAccessible;
    static function getAll(Database $db): array;
}
