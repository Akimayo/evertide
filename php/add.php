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
                        break;
                    case 'update':
                        /*
                         * UPDATE LINK
                         * Used fields: id[, name = null, description = null, public = false, parent = (keep)]
                         */
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        $id = $_POST['id'];
                        $category = LinkDAO::get($db, $id)->getAccessObject($db);
                        // Take out nested category, if applicable
                        if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                        $link = $category->getLinks()[0];
                        // Perform update
                        $link->update(
                            name: empty($_POST['name']) ? null : $_POST['name'],
                            description: empty($_POST['description']) ? null : $_POST['description'],
                            public: $_POST['public'] ?? false
                        );
                        if (isset($_POST['parent']) && $category->getId() !== $_POST['parent']) {
                            $category = CategoryDAO::get($db, $_POST['parent']);
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
                    'categories' => CategoryDAO::getAll($db, true, true)
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
                            public: isset($_POST['public']) ? $_POST['public'] : false
                        );
                        $handler->status(HTTP_CREATED);
                        break;
                    case 'edit_form':
                        /*
                         * SHOW CATEGORY EDIT FORM
                         * Used fields: --
                         */
                        $category = CategoryDAO::get($db, $_GET['id']);
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
                        $parent = null;
                        if (!empty($category->getCategories())) {
                            $parent = $category;
                            $category = $category->getCategories()[0];
                        }
                        $category->update(
                            name: $_POST['name'],
                            icon: $_POST['icon'],
                            public: isset($_POST['public']) ? $_POST['public'] : null
                        );
                        if (isset($_POST['parent']) && $_POST['parent'] !== $parent->getId()) {
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
                         */
                        if (!isset($_POST['id'])) return $handler->error(HTTP_BAD_REQUEST, 'Missing "id" in POST body');
                        $category = CategoryDAO::get($db, $_POST['id'])->getAccessObject($db);
                        if (!empty($category->getCategories())) $category = $category->getCategories()[0];
                        $category->delete();
                        return $handler->redirect('/');
                    default:
                        return $handler->error(HTTP_METHOD_NOT_ALLOWED, 'Invalid action "' . $action . '"');
                }
                $handler->render('add/category.latte', [
                    'category' => $category,
                    'categories' => CategoryDAO::getAll($db, true, true),
                    'expand_category' => $expand_category
                ]);
                break;
            case 'instance':
                $_probe_instance = require(__DIR__ . '/src/functions/fetch_instance_data.php');
                $instance = $_probe_instance($db);
                if ($instance) {
                    $handler
                        ->status(HTTP_NON_AUTHORITATIVE_INFORMATION)
                        ->render('add/instance.latte', ['remote' => $instance]);
                } else $handler->error(HTTP_FORBIDDEN);
                break;
            default:
                if (isset($_POST['url'])) {
                    // TODO: Add new link, but to what category?
                    return $handler->error(HTTP_NOT_IMPLEMENTED);
                } else return $handler->error(HTTP_BAD_REQUEST, 'Invalid type "' . $type . '"');
        }
    } catch (Exception $ex) {
        $handler->error(HTTP_INTERNAL_SERVER_ERROR, $ex->getMessage());
    }
} else $handler->error(HTTP_UNAUTHORIZED);
