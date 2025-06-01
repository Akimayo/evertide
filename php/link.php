<?php

/** @var Handler $handler */
$handler = require("src/handler.php");
if (isset($_GET['device'])) {
    // Link new device
    $_link_fn = require(__DIR__ . '/src/functions/link_device.php');
    if ($_link_fn(new ServerDatabase($handler), $_GET['device']))
        $handler
            // ->status(HTTP_CREATED) // Sending a header along with the redirect stops the redirect
            ->redirect('/link');
    else
        $handler
            ->status(HTTP_UNAUTHORIZED)
            ->render('link/link_failed.latte');
} else if ($handler->isAuthorized()) {
    // Display device management
    $db = $handler->getDatabase();
    $handler->render(
        'link/device_list.latte',
        [
            'devices' => DeviceDAO::getAll($db),
            'current' => DeviceDAO::getCurrent($db)->getId()
        ]
    );
} else {
    // Send an 'Unauthorized' response
    $handler
        ->status(HTTP_UNAUTHORIZED)
        ->render('error.latte', ['status' => HTTP_UNAUTHORIZED]);
}
