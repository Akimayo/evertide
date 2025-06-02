<?php

/** @var Handler $handler */
$handler = require("src/handler.php");
require_once(__DIR__ . '/src/data/Category.php');

$db = $handler->getDatabase();
$categories = CategoryDAO::getAll($db, $handler->isAuthorized());
if (empty($categories) && $handler->isAuthorized()) {
    $placeholder_blurhash_1 = 'LEHLk~WB2yk8pyo0adR*.7kCMdnj';
    $placeholder_blurhash_2 = 'LGF5]+Yk^6#M@-5c,1J5@[or[Q6.';

    $sw = CategoryDAO::create($db, 'Software', 'code', true)->getAccessObject($db);
    $sw->createLink(
        'https://blogs.windows.com/windowsdeveloper/2023/05/26/delivering-delightful-performance-for-more-than-one-billion-users-worldwide/',
        'Delivering Delightful Performance for More Than One Billion Users Worldwide',
        $placeholder_blurhash_1,
        'https://winblogs.thesourcemediaassets.com/sites/3/2021/06/cropped-browser-icon-logo-32x32.jpg',
    );
    $sw->createLink(
        'https://www.opencompute.org/documents/ocp-microscaling-formats-mx-v1-0-spec-final-pdf',
        'OCP_Microscaling Formats (MX) v1.0 Spec_Final.pdf',
        null,
        null
    );
    $sw->createLink(
        'https://mystery.knightlab.com/',
        'The SQL Murder Mystery',
        $placeholder_blurhash_1,
        'https://cdn.knightlab.com/libs/orangeline/0.1.1/assets/favicons/favicon-32x32.png'
    );

    $fo = CategoryDAO::create($db, 'Fonts', 'text-aa', false)->getAccessObject($db)->updateParent($sw);
    $fo->createLink(
        'https://b612-font.com/',
        'B612 - The font family',
        $placeholder_blurhash_2,
        null
    );

    $co = CategoryDAO::create($db, 'Cooking', 'bowl-food', false)->getAccessObject($db);
    $co->createLink(
        'https://traumbooks.itch.io/the-sad-bastard-cookbook',
        'The Sad Bastard Cookbook',
        $placeholder_blurhash_2,
        'https://img.itch.zone/aW1nLzEyOTIwNzUzLnBuZw==/32x32%23b/EcTqAU.png'
    );

    $categories = CategoryDAO::getAll($db, $handler->isAuthorized());
}

$handler->render('index.latte', ['categories' => $categories]);
