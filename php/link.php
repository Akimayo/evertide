<?php

/** @var Handler $handler */
$handler = require("src/handler.php");
if (isset($_GET['device'])) {
    $_link_fn = require(__DIR__ . '/src/functions/link_device.php');
    $_link_fn(new ServerDatabase($handler), $_GET['device']);
}
header('Location: /');
