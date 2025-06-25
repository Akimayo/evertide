<?php

/** @var Handler $handler */
$handler = require(__DIR__ . '/src/handler.php');
require_once(__DIR__ . '/src/data/Category.php');
if ($handler->isAuthorized()) {
    $type = isset($_GET['type']) ? strtolower($_GET['type']) : false;
    $action = isset($_GET['action']) ? strtolower($_GET['action']) : false;
    if (!$action && isset($_GET['id'])) $action = 'edit_form';
    if (($action == 'update' || $action == 'delete') && !isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');

    $db = $handler->getDatabase();
    try {
        switch ($type) {
            case false:
                $handler->render('add/hub.latte', ['categories' => CategoryDAO::getAll($handler->getDatabase(), true, true)]);
                break;
            case 'link':
                if (!$action && isset($_POST['url'])) $action = 'create';
                switch ($action) {
                    case 'create':
                        /*
                         * CREATE LINK
                         * Used fields: url, category
                         */
                        if (!isset($_POST['category'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "category" in POST body');

                        $category = CategoryDAO::get($db, $_POST['category'])->getAccessObject($db);
                        if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                        $_create_link = require(__DIR__ . '/src/functions/fetch_link_data.php');
                        $link = $_create_link($category);
                        $id = $link->getId();
                        $handler->status(HTTP_CREATED);
                        break;
                    case 'edit_form':
                        /*
                         * SHOW LINK EDIT FORM
                         * Used fields: --
                         */
                        $id = $_GET['id'];
                        $category = LinkDAO::get($db, $id);
                        if (!is_null($category->getSourceInstance())) return $handler->error(HTTP_BAD_REQUEST, 'Cannot edit remote link');
                        break;
                    case 'update':
                        /*
                         * UPDATE LINK
                         * Used fields: id[, name = null, description = null, public = false, parent = (keep)]
                         */
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        $id = intval($_POST['id']);
                        $category = LinkDAO::get($db, $id)->getAccessObject($db);
                        if (!is_null($category->getSourceInstance())) return $handler->error(HTTP_BAD_REQUEST, 'Cannot update remote link');
                        // Take out nested category, if applicable
                        if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                        $link = $category->getLinks()[0];
                        // Perform update
                        $link->update(
                            name: empty($_POST['name']) ? null : $_POST['name'],
                            description: empty($_POST['description']) ? null : $_POST['description'],
                            public: isset($_POST['public']) ? boolval($_POST['public']) : false
                        );
                        if (isset($_POST['category']) && $category->getId() !== ($parent_id = intval($_POST['category']))) {
                            $category = CategoryDAO::get($db, $parent_id);
                            if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                            $link->updateParent($category);
                        }
                        break;
                    case 'delete':
                        /*
                         * DELETE LINK
                         * Used fields: id
                         */
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        $id = $_POST['id'];
                        $category = LinkDAO::get($db, $id)->getAccessObject($db);
                        if (!is_null($category->getSourceInstance())) return $handler->error(HTTP_BAD_REQUEST, 'Cannot delete remote link');
                        // Take out nested category, if applicable
                        if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                        $link = $category->getLinks()[0];
                        // Perform delete
                        $link->delete();
                        return $handler->redirect('/');
                    default:
                        return $handler->error(HTTP_METHOD_NOT_ALLOWED, 'Invalid action "' . $action . '"');
                }
                // Get a fresh Link instance and render edit form
                $category = LinkDAO::get($db, $id);
                $expand_category = null;
                if (!empty($category->getCategories())) {
                    $expand_category = $category->getId();
                    $category = $category->getCategories()[0];
                }
                $handler->render('add/link.latte', [
                    'category' => $category,
                    'expand_category' => $expand_category,
                    'categories' => CategoryDAO::getAllLocal($db, true, true)
                ]);
                break;
            case 'category':
                if (!$action && isset($_POST['name'])) $action = 'create';
                $expand_category = null;
                switch ($action) {
                    case 'create':
                        /*
                         * CREATE CATEGORY
                         * Used fields: name, icon[, public = false]
                         */
                        if (!isset($_POST['name'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "name" in POST body');
                        if (!isset($_POST['icon'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "icon" in POST body');
                        $category = CategoryDAO::create(
                            $db,
                            name: $_POST['name'],
                            icon: $_POST['icon'],
                            public: isset($_POST['public']) ? boolval($_POST['public']) : false
                        );
                        $handler->status(HTTP_CREATED);
                        break;
                    case 'edit_form':
                        /*
                         * SHOW CATEGORY EDIT FORM
                         * Used fields: --
                         */
                        $category = CategoryDAO::get($db, $_GET['id']);
                        if (!is_null($category->getSourceInstance())) return $handler->error(HTTP_BAD_REQUEST, 'Cannot edit remote category');
                        if (!empty($category->getCategories())) {
                            $expand_category = $category->getId();
                            $category = $category->getCategories()[0];
                        }
                        break;
                    case 'update':
                        /**
                         * UPDATE CATEGORY
                         * Used fields: id, name, icon[, public = (keep), parent = (keep)]
                         */
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        if (!isset($_POST['name'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "name" in POST body');
                        if (!isset($_POST['icon'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "icon" in POST body');
                        $category = CategoryDAO::get($db, $_POST['id'])->getAccessObject($db);
                        if (!is_null($category->getSourceInstance())) return $handler->error(HTTP_BAD_REQUEST, 'Cannot update remote category');
                        $parent = null;
                        if (!empty($category->getCategories())) {
                            $parent = $category;
                            $category = $category->getCategories()[0];
                        }
                        $category->update(
                            name: $_POST['name'],
                            icon: $_POST['icon'],
                            public: isset($_POST['public']) ? boolval($_POST['public']) : false
                        );
                        if (isset($_POST['parent']) && $_POST['parent'] !== $parent?->getId()) {
                            if (empty($_POST['parent'])) {
                                $category->updateParent(null);
                                $parent = null;
                            } else {
                                $parent = CategoryDAO::get($db, $_POST['parent']);
                                if (!empty($parent->getCategories())) return $handler->error(HTTP_BAD_REQUEST, 'Parent category can only be a top-level category');
                                $category->updateParent($parent);
                            }
                        }
                        $expand_category = $parent?->getId();
                        break;
                    case 'delete':
                        /*
                         * DELETE CATEGORY
                         * Used fields: id
                         */
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        $category = CategoryDAO::get($db, $_POST['id'])->getAccessObject($db);
                        if (!is_null($category->getSourceInstance())) return $handler->error(HTTP_BAD_REQUEST, 'Cannot delete remote category');
                        if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                        $category->delete();
                        return $handler->redirect('/');
                    default:
                        return $handler->error(HTTP_METHOD_NOT_ALLOWED, 'Invalid action "' . $action . '"');
                }
                $handler->render('add/category.latte', [
                    'category' => $category,
                    'categories' => CategoryDAO::getAllLocal($db, true, true),
                    'expand_category' => $expand_category
                ]);
                break;
            case 'instance':
                if (!$action && isset($_POST['url'])) $action = 'probe';
                switch ($action) {
                    case 'probe':
                        $_probe_instance = require(__DIR__ . '/src/functions/fetch_instance_data.php');
                        [$remote, $extra] = $_probe_instance($db);
                        if ($remote) {
                            $local_categories = [];
                            foreach (CategoryDAO::getAllFromRemote($db, $remote) as $cat) {
                                $local_categories[] = $cat->getSourceId();
                                foreach ($cat->getCategories() as $cat)
                                    $local_categories[] = $cat->getSourceId();
                            }
                            $handler
                                ->status(HTTP_NON_AUTHORITATIVE_INFORMATION)
                                ->render('add/instance.latte', ['remote' => $remote, 'categories' => $extra, 'local_categories' => $local_categories]);
                        } else $handler->error(HTTP_FORBIDDEN, $extra);
                        break;
                    case 'edit_form':
                        $id = intval($_GET['id']);
                        $remote = InstanceDAO::get($db, $id);
                        $local_categories = [];
                        foreach (CategoryDAO::getAllFromRemote($db, $remote) as $cat) {
                            $local_categories[] = $cat->getSourceId();
                            foreach ($cat->getCategories() as $cat)
                                $local_categories[] = $cat->getSourceId();
                        }
                        $_probe_instance = require(__DIR__ . '/src/functions/fetch_instance_data.php');
                        [$maybe_remote, $extra] = $_probe_instance($db, instance_id: $id);
                        if ($maybe_remote)
                            $handler->render('add/instance.latte', [
                                'remote' => $maybe_remote,
                                'categories' => $extra,
                                'local_categories' => $local_categories,
                                'responded' => true
                            ]);
                        else
                            $handler->render('add/instance.latte', [
                                'remote' => $remote,
                                'categories' => CategoryDAO::getAllFromRemote($db, $remote),
                                'local_categories' => $local_categories,
                                'responded' => false
                            ]);
                        break;
                    case 'update':
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        // if (!isset($_POST['category'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "category" in POST body');
                        $id = intval($_POST['id']);
                        $c_selected = !isset($_POST['category']) ? [] : (is_array($_POST['category']) ? array_map(function (mixed $x) {
                            return intval($x);
                        }, $_POST['category']) : [intval($_POST['category'])]);

                        $remote = InstanceDAO::get($db, $id);
                        $c_local = CategoryDAO::getAllFromRemote($db, $remote);
                        $_probe_instance = require(__DIR__ . '/src/functions/fetch_instance_data.php');
                        [$remote, $c_available] = $_probe_instance($db, instance_id: $id);

                        // Go through local categories and delete unselected
                        $has_local = [];
                        $local_map = [];
                        foreach ($c_local as $cat) {
                            $local_map[$cat->getSourceId()] = $cat;
                            foreach ($cat->getCategories() as $cat) {
                                $local_map[$cat->getSourceId()] = $cat;
                                if (array_search($cat->getSourceId(), $c_selected) === false)
                                    $cat->getAccessObject($db)->delete();
                                else $has_local[] = $cat->getSourceId();
                            }
                            if (array_search($cat->getSourceId(), $c_selected) === false)
                                $cat->getAccessObject($db)->delete();
                            else $has_local[] = $cat->getSourceId();
                        }

                        // Go through available categories and create selected, unless they already exist locally
                        if ($remote) {
                            foreach ($c_available as $cat) {
                                $parent = null;
                                if (array_search($cat->getSourceId(), $c_selected) !== false) {
                                    if (array_search($cat->getSourceId(), $has_local) === false) {
                                        $parent = CategoryDAO::create(
                                            $db,
                                            name: $cat->getName(),
                                            icon: $cat->getIcon(),
                                            public: true,
                                            source: $remote,
                                            source_id: $cat->getSourceId()
                                        );
                                        $c_local[] = $parent;
                                    } else $parent = $local_map[$cat->getSourceId()];
                                }
                                foreach ($cat->getCategories() as $cat) {
                                    if (array_search($cat->getSourceId(), $c_selected) !== false && array_search($cat->getSourceId(), $has_local) === false) {
                                        $inst = CategoryDAO::create(
                                            $db,
                                            name: $cat->getName(),
                                            icon: $cat->getIcon(),
                                            public: true,
                                            source: $remote,
                                            source_id: $cat->getSourceId()
                                        );
                                        if ($parent !== null) $inst->getAccessObject($db)->updateParent($parent);
                                        $c_local[] = $inst;
                                    }
                                }
                            }
                        }

                        $handler->render('add/instance.latte', [
                            'remote' => $remote,
                            'categories' => $c_available,
                            'local_categories' => $c_selected,
                            'responded' => $remote !== false
                        ]);
                        break;
                    case 'delete':
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        // TODO: Delete instance, or just its categories?
                        return $handler->error(HTTP_NOT_IMPLEMENTED);
                        return $handler->redirect('/');
                    default:
                        return $handler->error(HTTP_METHOD_NOT_ALLOWED, 'Invalid action "' . $action . '"');
                }
                break;
            default:
                if (isset($_POST['url'])) {
                    try {
                        $category = CategoryDAO::get($db, -1);
                    } catch (Exception) {
                        // When the Bookmarks category does not exist
                        $date = date('Y-m-d H:i:s');
                        $device = DeviceDAO::getCurrent($db);
                        $db->insert('INSERT INTO Category (id, name, icon, public, create_date, update_date, from_device) VALUES (-1, :N, `bookmarks`, FALSE, :D, :D, :F);', ['N' => L::add_category_bookmarks, 'D' => $date, 'F' => $device->getId()]);
                        $category = new CategoryDAO($db, -1, L::add_category_bookmarks, 'bookmarks', null, false, $date, $date, $device, [], []);
                    }
                    $_create_link = require(__DIR__ . '/src/functions/fetch_link_data.php');
                    $link = $_create_link($category);
                    $id = $link->getId();
                    $handler->status(HTTP_CREATED);
                    $handler->render('add/link.latte', [
                        'category' => $category,
                        'expand_category' => -1,
                        'categories' => CategoryDAO::getAllLocal($db, true, true)
                    ]);
                } else return $handler->error(HTTP_BAD_REQUEST, 'Invalid type "' . $type . '"');
        }
    } catch (Throwable $ex) {
        $handler->error(HTTP_INTERNAL_SERVER_ERROR, $ex->getMessage() . PHP_EOL . $ex->getTraceAsString());
    }
} else $handler->error(HTTP_UNAUTHORIZED);
