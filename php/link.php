<?php

/** @var Handler $handler */
$handler = require("src/handler.php");
if (isset($_GET['device'])) {
    // Link new device
    $_link_fn = require(__DIR__ . '/src/functions/link_device.php');
    $_link_fn(new ServerDatabase($handler), $_GET['device']);
    header('HTTP/1.0 201 Created');
    header('Location: /link.php');
} else if ($handler->isAuthorized()) {
    // Display device management
    $db = $handler->getDatabase();
    $handler->render(
        'devicelist.latte',
        [
            'devices' => DeviceDAO::getAll($db),
            'current' => DeviceDAO::getCurrent($db)->getId()
        ]
    );
} else {
    // Send an 'Unauthorized' response
    header('HTTP/1.0 401 Unauthorized');
}
