<?php

/** @var Handler $handler */
$handler = require(__DIR__ . '/src/handler.php');

$authorNames = explode(';', L::about_development_localization_authorNames);
$authorUrls = explode(';', L::about_development_localization_authorUrls);
$authors = [];
foreach ($authorNames as $i => $name) {
    $name = trim($name);
    $url = trim($authorUrls[$i]);
    $authors[] = [
        $name,
        empty($url) || $url == '-' ? null : $url
    ];
}

$handler->render('about.latte', [
    'instances' => InstanceDAO::getAll(
        $handler->getDatabase(),
        only_valid_links: Config::get_config()->instance->isLinkValid() // Only display locally-hosted instances if this instance itself is locally hosted
    ),
    'authors' => $authors
]);
