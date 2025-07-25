<?php

/** @var Handler $handler */
$handler = require("src/handler.php");
require_once(__DIR__ . '/src/data/Category.php');


if (isset($_GET['sync'])) {
    $db = new ServerDatabase($handler);
    $date_limit = date('Y-m-d H:i:s', strtotime('-4 hours'));
    // Select categories of a single instance that has had the longest time since last sync
    $to_refresh = $db->selectAll(<<<PHP_EOL
    SELECT c.id, c.source, c.source_id, s.link, s.last_fetch_date
      FROM Category c
     INNER JOIN Instance s ON s.id = c.source
     WHERE c.source IS NOT NULL
       AND s.last_link_status < 65
       AND (s.last_fetch_date < :D OR s.last_fetch_date IS NULL)
       AND c.source = (SELECT id FROM Instance ORDER BY last_fetch_date IS NULL DESC, last_fetch_date ASC LIMIT 1)
     ORDER BY s.last_fetch_date IS NULL DESC, s.last_fetch_date ASC;
    PHP_EOL, ['D' => $date_limit]);
    if (empty($to_refresh)) return $handler->status(HTTP_NOT_MODIFIED);

    function sync(ServerDatabase $db, int $instance_id, string $link, array $category_map, ?string $original_date): bool
    {
        ob_start();
        echo '<pre>';
        echo 'FROM ' . $link . PHP_EOL;
        $date = date('Y-m-d H:i:s');
        // Race condition prevention: Set the date as current straigth away.
        // If another sync request comes during execution, it will get a different instance from the query above.
        $db->update('UPDATE Instance SET last_fetch_date = :D WHERE id = :S;', ['D' => $date, 'S' => $instance_id]);
        $instance = Config::get_config()->instance;
        try {
            $db->begin();
            $context = stream_context_create([
                'http' => [
                    'header' => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                    'method' => 'POST',
                    'content' => http_build_query([
                        'domain' => $instance->getDisplayName(),
                        'link' => $instance->getLink(),
                        'primary' => $instance->getPrimaryColor(),
                        'secondary' => $instance->getSecondaryColor(),
                        'categories' => array_keys($category_map),
                        'last_sync' => $original_date ?? ''
                    ]),
                    'ignore_errors' => true
                ]
            ]);
            $result = file_get_contents($link . 'link?sync', false, $context);
            $remote = InstanceDAO::get($db, $instance_id)->getAccessObject($db);
            if (!$result || ($result = json_decode($result)) === null) {
                $remote->updateLinkStatus(LinkStatus::UNREACHABLE);
                $db->commit();
                return false;
            }
            $status = property_exists($result, 'status') ? $result->status : HTTP_OK;
            if (!property_exists($result, 'instance') || empty($result->instance)) {
                // In case of fatal errors
                $remote->updateLinkStatus(LinkStatus::ERROR);
                $db->commit();
                return false;
            }
            $link_status = property_exists($result, 'categories') && is_array($result->categories) ? match ($status) {
                HTTP_OK => LinkStatus::PRELOADED,
                HTTP_REQUEST_TIMEOUT => LinkStatus::TIMED_OUT,
                HTTP_BAD_REQUEST => LinkStatus::ERROR,
                HTTP_FORBIDDEN => LinkStatus::BLOCKED,
                default => LinkStatus::UNREACHABLE
            } : LinkStatus::ERROR;
            $categories = @$result->categories;
            $deleted_categories = property_exists($result, 'deleted_categories') ? $result->deleted_categories : [];
            $deleted_links = property_exists($result, 'deleted_links') ? $result->deleted_links : [];
            $result = $result->instance;
            $instance_changed = false;
            if (
                $remote->getDomainName() != $result->domain ||
                $remote->getPrimaryColor() != $result->primary ||
                $remote->getSecondaryColor() != $result->secondary
            ) {
                $remote->updateInstance($result->domain, $result->primary, $result->secondary, $link_status);
                $instance_changed = true;
            } else $remote->updateLinkStatus($link_status);
            if ($link_status != $link_status::PRELOADED) {
                // Unless the request was successful, revert the fetch date to try again later
                // (not doing a rollback to keep the link status)
                $db->update('UPDATE Instance SET last_fetch_date = :D WHERE id = :S;', ['D' => $original_date, 'S' => $instance_id]);
                return false;
            }

            if (empty($categories) && empty($deleted_categories) && empty($deleted_links) && !$instance_changed) return false; // Nothing changed

            // Iterate through "parent" categories
            foreach ($categories as $cat) {
                echo 'Category ' . $cat->name . PHP_EOL;
                // Update existing category
                if (array_key_exists($cat->id, $category_map)) $parent = $category_map[$cat->id];
                // Create new category
                else $parent = CategoryDAO::create(
                    $db,
                    name: $cat->name,
                    icon: $cat->icon,
                    public: true,
                    source: $remote,
                    source_id: $cat->id
                )->getId();
                // In both cases there are some updates necessary, so just do them in a single query
                $db->update(
                    'UPDATE Category SET create_date = :C, update_date = :D, name = :N, icon = :I WHERE id = :J;',
                    ['C' => $cat->created, 'D' => $cat->updated, 'N' => $cat->name, 'I' => $cat->icon, 'J' => $parent]
                );
                // Create a local <-> source ID map for links in this category
                $link_map = [];
                foreach ($db->selectAll('SELECT id, source_id FROM Link WHERE category = :C;', ['C' => $parent]) as ['source_id' => $key, 'id' => $value])
                    $link_map[$key] = $value;
                // Iterate through links of the "parent" category
                foreach ($cat->links as $link) {
                    echo '  Link ' . $link->title . PHP_EOL;
                    // Update existing link
                    if (array_key_exists($link->id, $link_map)) $l = $link_map[$link->id];
                    // Create new link
                    else $l = $db->insert(
                        'INSERT INTO Link (url, title, blurhash, favicon, category, create_date, update_date, from_device, public, source_id) VALUES (:U, :T, :B, :F, :K, :C, :D, -1, true, :S);',
                        ['U' => $link->url, 'T' => $link->title, 'B' => $link->blurhash, 'F' => $link->favicon, 'K' => $parent, 'C' => $link->created, 'D' => $link->updated, 'S' => $link->id],
                        'Link'
                    );
                    // In both cases there are some updates necessary, so just do them in a single query
                    $db->update(
                        'UPDATE Link SET title = :T, blurhash = :B, name = :N, description = :D, favicon = :F, category = :K, update_date = :D WHERE id = :I;',
                        ['T' => $link->title, 'B' => $link->blurhash, 'N' => $link->name, 'D' => $link->description, 'F' => $link->favicon, 'K' => $parent, 'D' => $link->updated, 'I' => $l]
                    );
                }
                // Iterate through "leaf" categories
                foreach ($cat->categories as $cat) {
                    echo '  Category ' . $cat->name . PHP_EOL;
                    // Update existing category
                    if (array_key_exists($cat->id, $category_map)) $inst = $category_map[$cat->id];
                    // Create new category
                    else $inst = CategoryDAO::create(
                        $db,
                        name: $cat->name,
                        icon: $cat->icon,
                        public: true,
                        source: $remote,
                        source_id: $cat->id
                    )->getId();
                    // In both cases there are some updates necessary, so just do them in a single query
                    $db->update('UPDATE Category SET create_date = :C, update_date = :D, parent = :P WHERE id = :I;', ['I' => $inst, 'C' => $cat->created, 'D' => $cat->updated, 'P' => $parent]);
                    // Create a local <-> source ID map for links in this category
                    $link_map = [];
                    foreach ($db->selectAll('SELECT id, source_id FROM Link WHERE category = :C;', ['C' => $parent]) as ['source_id' => $key, 'id' => $value])
                        $link_map[$key] = $value;
                    // Iterate through links of the "leaf" category
                    foreach ($cat->links as $link) {
                        echo '    Link ' . $link->title . PHP_EOL;
                        // Update existing link
                        if (array_key_exists($link->id, $link_map)) $l = $link_map[$link->id];
                        // Create new link
                        else $l = $db->insert(
                            'INSERT INTO Link (url, title, blurhash, favicon, category, create_date, update_date, from_device, public, source_id) VALUES (:U, :T, :B, :F, :K, :C, :D, -1, true, :S);',
                            ['U' => $link->url, 'T' => $link->title, 'B' => $link->blurhash, 'F' => $link->favicon, 'K' => $inst, 'C' => $link->created, 'D' => $link->updated, 'S' => $link->id],
                            'Link'
                        );
                        // In both cases there are some updates necessary, so just do them in a single query
                        $db->update(
                            'UPDATE Link SET title = :T, blurhash = :B, name = :N, description = :D, favicon = :F, public = TRUE, category = :K, create_date = :C, update_date = :D WHERE id = :I;',
                            ['T' => $link->title, 'B' => $link->blurhash, 'N' => $link->name, 'D' => $link->description, 'F' => $link->favicon, 'K' => $inst, 'D' => $link->updated, 'I' => $l]
                        );
                    }
                }
            }

            // Deleted categories and links, as sent in the sync response
            if (!empty($deleted_links)) {
                $deleted_links = array_combine(
                    array_map(function (int $i): string {
                        return ':I' . $i;
                    }, array_keys($deleted_links)),
                    $deleted_links
                );
                $remote_categories = CategoryDAO::getAllFromRemote($db, $remote);
                $remote_categories = array_combine(
                    array_map(function (int $i): string {
                        return ':S' . $i;
                    }, array_keys($remote_categories)),
                    $remote_categories
                );
                $I_ = implode(', ', array_keys($deleted_links));
                $S_ = implode(', ', array_keys($remote_categories));
                $db->delete('DELETE FROM Link WHERE category IN (' . $S_ . ') AND source_id IN (' . $I_ . ');', array_merge($deleted_links, $remote_categories));
            }
            if (!empty($deleted_categories)) {
                $deleted_categories = array_combine(
                    array_map(function (int $i): string {
                        return ':I' . $i;
                    }, array_keys($deleted_categories)),
                    $deleted_categories
                );
                $I_ = implode(', ', array_keys($deleted_categories));
                $db->delete('DELETE FROM Category WHERE source = :S AND source_id IN (' . $I_ . ');', array_merge(['S' => $remote->getId()], $deleted_categories));
            }
            $db->commit();
            return true;
        } catch (Throwable $ex) {
            $db->rollback();
            echo 'ERROR: ' . $ex->getMessage() . PHP_EOL;
            echo $ex->getTraceAsString() . PHP_EOL;
            // If the sync fails, write back the original last_fetch_date so that it is available for another sync
            $db->update('UPDATE Instance SET last_fetch_date = :D WHERE id = :S;', ['D' => $original_date, 'S' => $instance_id]);
            return false;
        } finally {
            echo '</pre>';
            if (false) ob_end_flush();
            else ob_end_clean();
        }
    }

    // This part is built to support categories from multiple instances, even though it is only used with one instance at a time due to concurrency concerns.
    $lastSourceId = -1;
    $lastSourceLink = '';
    $lastSourceDate = null;
    $categories = [];
    $changed = false;
    foreach ($to_refresh as $row) {
        if ($row['source'] != $lastSourceId) {
            if ($lastSourceId >= 0) {
                $changed = $changed || sync($db, $lastSourceId, $lastSourceLink, $categories, $lastSourceDate);
                $categories = [];
            }
            $lastSourceId = $row['source'];
            $lastSourceLink = $row['link'];
            $lastSourceDate = $row['last_fetch_date'];
        }
        $categories[$row['source_id']] = $row['id'];
    }
    if ($lastSourceId >= 0) $changed = $changed || sync($db, $lastSourceId, $lastSourceLink, $categories, $lastSourceDate);
    if ($changed) $handler->render('index-bare.latte', ['categories' => CategoryDAO::getAll($db, includePrivate: $handler->isAuthorized())]);
    else $handler->status(HTTP_NOT_MODIFIED);
} else $handler->render('index.latte', ['categories' => CategoryDAO::getAll($handler->getDatabase(), includePrivate: $handler->isAuthorized())]);
