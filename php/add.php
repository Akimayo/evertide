<?php

/** @var Handler $handler */
$handler = require(__DIR__ . '/src/handler.php');
require_once(__DIR__ . '/src/data/Category.php');
if ($handler->isAuthorized()) {

    if (isset($_GET['type'])) {
        $db = $handler->getDatabase();
        switch (strtolower($_GET['type'])) {
            case 'link':
                if (isset($_GET['action'])) {
                    // Update or delete post
                    $id = $_POST['id'];
                    $category = LinkDAO::get($db, $id)
                        ->getAccessObject($db);
                    $link = $category->getLinks()[0];
                    switch (strtolower($_GET['action'])) {
                        case 'update':
                            $link->update(
                                name: $_POST['name'],
                                description: $_POST['description']
                            );
                            if (isset($_POST['parent']) && $category->getId() !== $_POST['parent']) {
                                $category = CategoryDAO::get($db, $_POST['parent']);
                                $link->updateParent($category);
                            }
                            break;
                        case 'delete':
                            $link->delete();
                            $handler->redirect('/');
                            exit();
                            break;
                    }
                } else if (isset($_GET['id']))
                    // Show form, no action
                    $id = $_GET['id'];
                else {
                    // Create new link
                    $category = CategoryDAO::get($db, $_POST['category'])->getAccessObject($db);
                    $_create_link = require(__DIR__ . '/src/functions/fetch_link_data.php');
                    $link = $_create_link($category);
                    $id = $link->getId();
                }
                $category = LinkDAO::get($db, $id);
                $cat_id = $category->getId();
                $all_categories = CategoryDAO::getAll($db, true);
                $expand_category = null;
                foreach ($all_categories as $cat) {
                    foreach ($cat->getCategories() as $c)
                        if ($c->getId() == $cat_id) {
                            $expand_category = $cat->getId();
                            break;
                        }
                    if ($expand_category !== null) break;
                }
                $handler
                    ->status(HTTP_CREATED)
                    ->render('add/link.latte', ['category' => $category, 'categories' => $all_categories, 'expand_category' => $expand_category]);
                break;
            case 'category':
                if (isset($_POST['id']))
                    $category = CategoryDAO::get($db, $_POST['id'])
                        ->getAccessObject($db)
                        ->update(
                            name: $_POST['name'],
                            icon: $_POST['icon'],
                            public: $_POST['public']
                        );
                else
                    $category = CategoryDAO::create(
                        $db,
                        name: $_POST['name'],
                        icon: $_POST['icon'],
                        public: $_POST['public'],
                    );
                $handler
                    ->status(HTTP_CREATED)
                    ->render('add/category.latte', ['category' => $category]);
                break;
            case 'instance':
                $_probe_instance = require(__DIR__ . '/src/functions/fetch_instance_data.php');
                $instance = $_probe_instance($db);
                if ($instance) {
                    $handler
                        ->status(HTTP_NON_AUTHORITATIVE_INFORMATION)
                        ->render('add/instance.latte', ['remote' => $instance]);
                } else $handler
                    ->status(HTTP_FORBIDDEN)
                    ->render('add/rejected.latte');
                break;
            default:
                $handler
                    ->status(HTTP_BAD_REQUEST)
                    ->render('error.latte', ['status' => HTTP_BAD_REQUEST]);
                break;
        }
    } else if (isset($_POST['url'])) {
        // TODO: Add new link, but to what category?
    } else {
        $handler->render('add/hub.latte', ['categories' => CategoryDAO::getAll($handler->getDatabase(), true)]);
    }
} else $handler
    ->status(HTTP_UNAUTHORIZED)
    ->render('error.latte', ['status' => HTTP_UNAUTHORIZED]);
