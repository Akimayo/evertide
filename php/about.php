<?php

/** @var Handler $handler */
$handler = require(__DIR__ . '/src/handler.php');
$handler->render('about.latte', ['instances' => InstanceDAO::getAll($handler->getDatabase())]);
