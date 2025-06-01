<?php

/** @var Handler $handler */
$handler = require("src/handler.php");
require_once(__DIR__ . '/src/data/Category.php');
$handler->render('index.latte', [
    'categories' => [
        new Category(
            id: 0,
            name: 'Software',
            icon: 'code',
            links: [],
            categories: [],
            source: null
        ),
        new Category(
            id: 1,
            name: 'Websites',
            icon: 'globe',
            links: [],
            categories: [],
            source: null
        ),
        new Category(
            id: 2,
            name: 'Papers',
            icon: 'flask',
            links: [],
            categories: [],
            source: Instance::raw(
                id: 0,
                domain: 'example.com',
                link: 'https://example.com/evertide',
                primary: '#ea8592',
                secondary: '#0197f6'
            )
        )
    ]
]);
