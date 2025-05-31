<?php

declare(strict_types=1);
require_once(__DIR__ . '/../data/Device.php');

return function (ServerDatabase $db): bool {
    if (isset($_COOKIE['evertide'])) {
        try {
            $parts = explode(';', $_COOKIE['evertide']);
            $device = DeviceDAO::get($db, $parts[1]);
            /** @var DeviceDAO $device */
            $device = $device->getAccessObject($db);
            $device->updateLastLogin();
            $_set_cookie = require(__DIR__ . '/set_cookie.php');
            $_set_cookie();
            return true;
        } catch (Exception) {
            /* Device not found, delete cookie if present */
            setcookie('evertide', 'false', 1);
        }
    }
    if (!file_exists(__DIR__ . '/../../opt/link') || !file_exists(__DIR__ . '/../../opt/authentication.md')) {
        $_gen_code = require(__DIR__ . '/generate_link_code.php');
        $link = $_gen_code(12);
        require_once(__DIR__ . '/../data/Device.php');
        $all_devices = DeviceDAO::getAll($db);
        $_gen_link = require(__DIR__ . '/generate_link_file.php');
        $_gen_link($link, $all_devices);
    }
    return false;
};
