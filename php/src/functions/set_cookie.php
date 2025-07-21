<?php

declare(strict_types=1);

return function (?string $link = null): void {
    $cfg = Config::get_config();
    $cookie_name = $cfg->get_cookie_name();
    if (isset($_COOKIE[$cookie_name]) && $link === null) {
        $parts = explode(';', $_COOKIE[$cookie_name]);
        $expires = intval($parts[0]);
        $link = $parts[1];
    } else $expires = -1;
    if ($link === null) return;
    if ($expires < strtotime('+1 month')) {
        $expires = strtotime('+1 year');

        $url = $cfg->instance->getLink();
        $url = substr($url, strpos($url, '/') + 2);
        $slash_pos = strpos($url, '/');
        $domain = substr($url, 0, $slash_pos);
        $colon_pos = strpos($domain, ':');
        if ($colon_pos !== false) $domain = substr($domain, 0, $colon_pos);
        $path = substr($url, $slash_pos);

        setcookie($cookie_name, $expires . ';' . $link, [
            'expires' => $expires,
            'path' => $path,
            'domain' => $domain,
            'secure' => true,
            'httponly' => false,
            'samesite' => 'Lax'
        ]);
    }
};
