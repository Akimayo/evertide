<?php

declare(strict_types=1);
require_once(__DIR__ . '/../data/Device.php');

return function (ServerDatabase $db): bool {
    $cookie_name = Config::get_config()->get_cookie_name();
    if (isset($_COOKIE[$cookie_name])) {
        try {
            $parts = explode(';', $_COOKIE[$cookie_name]);
            $device = DeviceDAO::get($db, $parts[1]);
            /** @var DeviceDAO $device */
            $device = $device->getAccessObject($db);
            $device->updateLastLogin();
            $_set_cookie = require(__DIR__ . '/set_cookie.php');
            $_set_cookie();
            return true;
        } catch (Exception) {
            /* Device not found, delete cookie if present */
            setcookie($cookie_name, 'false', 1);
        }
    }
    $data_location = Config::get_config()->get_data_location();
    if (!file_exists($data_location . 'link') || !file_exists($data_location . 'authentication.md')) {
        $_gen_code = require(__DIR__ . '/generate_link_code.php');
        $link = $_gen_code(12);
        require_once(__DIR__ . '/../data/Device.php');
        $all_devices = DeviceDAO::getAll($db);
        $_gen_link = require(__DIR__ . '/generate_link_file.php');
        $_gen_link($link, $all_devices);
    }
    return false;
};
