<?php

declare(strict_types=1);
require_once(__DIR__ . '/../data/Device.php');

return function (ServerDatabase $db, string $code): bool {
    $_gen_code = require(__DIR__ . '/generate_link_code.php');
    $_gen_link = require(__DIR__ . '/generate_link_file.php');
    $_set_cookie = require(__DIR__ . '/set_cookie.php');
    $all_devices = DeviceDAO::getAll($db);

    // If the link file does not exist, create it
    if (!file_exists(__DIR__ . '/../../opt/link')) {
        // echo 'link failed: link file does not exist' . PHP_EOL;
        $link = $_gen_code(12);
        $_gen_link($link, $all_devices);
        return false;
    }

    $check_code = file_get_contents(__DIR__ . '/../../opt/link');
    // If the code does not match, re-generate the link just to be sure
    if ($code !== $check_code) {
        // echo 'link failed: code does not match' . PHP_EOL;
        $link = $_gen_code(12);
        $_gen_link($link, $all_devices);
        return false;
    }

    // If code matches, use HTTP_AUTH to request a device name from user
    if (!isset($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_USER'])) {
        header('WWW-Authenticate: Basic realm="evertide New Device"');
        return false;
    }

    // If code matches and we got a name, register the new device and set cookie
    $link = $_gen_code(64);
    $all_devices[] = DeviceDAO::create($db, $_SERVER['PHP_AUTH_USER'], $link);
    $_set_cookie($link);
    $link = $_gen_code(12);
    $_gen_link($link, $all_devices);

    return true;
};
