<?php

declare(strict_types=1);

return function (?string $link = null): void {
    if (isset($_COOKIE['evertide']) && $link === null) {
        $parts = explode(';', $_COOKIE['evertide']);
        $expires = intval($parts[0]);
        $link = $parts[1];
    } else $expires = -1;
    if ($link === null) return;
    if ($expires < strtotime('+1 month')) {
        $expires = strtotime('+1 year');
        setcookie('evertide', $expires . ';' . $link, [
            'expires' => $expires,
            'path' => '/', // FIXME: Set path from config
            'domain' => 'localhost', // FIXME: Set domain from config
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Strict'
        ]);
    }
};
