<?php

/** @var Handler $handler */
$handler = require(__DIR__ . "/src/handler.php");
require_once(__DIR__ . "/src/data/Category.php");

if (isset($_GET['device'])) {
    /*
     * LINK NEW DEVICE
     */
    $_link_fn = require(__DIR__ . '/src/functions/link_device.php');
    if ($_link_fn(new ServerDatabase($handler), $_GET['device']))
        $handler
            // ->status(HTTP_CREATED) // Sending a header along with the redirect stops the redirect
            ->redirect('/link');
    else
        $handler
            ->status(HTTP_UNAUTHORIZED)         // This is not using the $handler->error(...) function, because it may have been
            ->render('link/link_failed.latte'); // a legitimate request, but the user clicked 'Cancel' in the browser login dialog.
} else if (isset($_GET['sync'])) {
    $db = new ServerDatabase($handler);
    try {
        $remote = InstanceDAO::getByAddress($db, $_POST['link'])->getAccessObject($db);
        if ((time() - strtotime($remote->getLastFetchDate())) < 2) return $handler->error(HTTP_REQUEST_TIMEOUT, 'Too many requests');
        $remote->updateFetchDate();
        if ($remote->isBlocked()) return $handler->error(HTTP_FORBIDDEN, 'Blocked');
    } catch (Exception) {
        return $handler->error(HTTP_UNAUTHORIZED, 'Instance not registered');
    }

    if (!isset($_POST['domain'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "domain" in POST body');
    if (!isset($_POST['link'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "link" in POST body');
    if (!isset($_POST['primary'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "primary" in POST body');
    if (!isset($_POST['secondary'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "secondary" in POST body');

    if (
        $remote->getDisplayName() != $_POST['domain'] ||
        $remote->getPrimaryColor() != $_POST['primary'] ||
        $remote->getSecondaryColor() != $_POST['secondary']
    ) $remote->updateInstance($_POST['domain'], $_POST['primary'], $_POST['secondary'], LinkStatus::SUCCESS);

    if (!isset($_POST['categories'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "categories" in POST body');
    if (!isset($_POST['last_sync'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "last_sync" in POST body');

    $categories = is_array($_POST['categories']) ? array_map(function (mixed $x): int {
        return intval($x);
    }, $_POST['categories']) : [intval($_POST['categories'])];
    $last_sync = empty($_POST['last_sync']) ? '0' : $_POST['last_sync'];

    $handler->render('', [
        'categories' => array_map(function (Category $cat): array {
            return [
                'id' => $cat->getId(),
                'name' => $cat->getName(),
                'icon' => $cat->getIcon(),
                'created' => $cat->getCreationDate(),
                'updated' => $cat->getUpdateDate(),
                'links' => array_map(function (Link $l): array {
                    return [
                        'id' => $l->getId(),
                        'url' => $l->getUrl(),
                        'title' => $l->getTitle(),
                        'blurhash' => $l->getBlurHash(),
                        'name' => $l->getName(),
                        'description' => $l->getDescription(),
                        'favicon' => $l->getFavicon(),
                        'created' => $l->getCreationDate(),
                        'updated' => $l->getUpdateDate()
                    ];
                }, $cat->getLinks()),
                'categories' => array_map(function (LeafCategory $cat): array {
                    return [
                        'id' => $cat->getId(),
                        'name' => $cat->getName(),
                        'icon' => $cat->getIcon(),
                        'created' => $cat->getCreationDate(),
                        'updated' => $cat->getUpdateDate(),
                        'links' => array_map(function (Link $l): array {
                            return [
                                'id' => $l->getId(),
                                'url' => $l->getUrl(),
                                'title' => $l->getTitle(),
                                'blurhash' => $l->getBlurHash(),
                                'name' => $l->getName(),
                                'description' => $l->getDescription(),
                                'favicon' => $l->getFavicon(),
                                'created' => $l->getCreationDate(),
                                'updated' => $l->getUpdateDate()
                            ];
                        }, $cat->getLinks())
                    ];
                }, $cat->getCategories())
            ];
        }, CategoryDAO::getSync($db, $categories, $last_sync)),
        'deleted_categories' => CategoryDAO::getDeletedIds($db, since: $last_sync),
        'deleted_links' => LinkDAO::getDeletedIds($db, since: $last_sync)
    ], true);
} else if (!empty($_POST)) {
    /*
     * INSTANCE FETCH REQUEST
     */
    if (!isset($_POST['domain'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "domain" in POST body');
    $instance = Config::get_config()->instance;
    $db = new ServerDatabase($handler);
    try {
        $remote = InstanceDAO::getByAddress($db, $_POST['link']);
        if ($remote->isBlocked()) return $handler->error(HTTP_FORBIDDEN, 'Blocked');
        if ((time() - strtotime($remote->getLastFetchDate())) < 2) return $handler->error(HTTP_REQUEST_TIMEOUT, 'Too many requests');
    } catch (Exception) {
        /* Intended fail, new instance */
        if (!$instance->isOpen()) return $handler->error(HTTP_FORBIDDEN, 'Federation is not open');
        $remote = InstanceDAO::create(
            $db,
            domain: $_POST['domain'],
            link: $_POST['link'],
            primary: $_POST['primary'],
            secondary: $_POST['secondary']
        );
    }
    $remote->getAccessObject($db)->updateFetchDate();
    $all_categories = CategoryDAO::getAll($db);
    $handler->render('', [
        'categories' => array_map(function (Category $cat): array {
            return [
                'id' => $cat->getId(),
                'name' => $cat->getName(),
                'icon' => $cat->getIcon(),
                'link_count' => count($cat->getLinks()),
                'categories' => array_map(function (LeafCategory $cat): array {
                    return [
                        'id' => $cat->getId(),
                        'name' => $cat->getName(),
                        'icon' => $cat->getIcon(),
                        'link_count' => count($cat->getLinks())
                    ];
                }, $cat->getCategories())
            ];
        }, $all_categories)
    ], true);
} else if ($handler->isAuthorized()) {
    $db = $handler->getDatabase();
    if (!isset($_GET['action'])) $_GET['action'] = 'devices';
    else if (!isset($_GET['instance'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "instance" query parameter');
    else $instance = InstanceDAO::get($db, intval($_GET['instance']));
    switch ($_GET['action']) {
        case 'devices':
            /**
             * LIST DEVICE MANAGEMENT
             */
            return $handler->render(
                'link/device_list.latte',
                [
                    'devices' => DeviceDAO::getAll($db),
                    'current' => DeviceDAO::getCurrent($db)->getId()
                ]
            );
        default:
            return $handler->error(HTTP_BAD_REQUEST, 'Unknown action');
    }
} else $handler->error(HTTP_UNAUTHORIZED);
