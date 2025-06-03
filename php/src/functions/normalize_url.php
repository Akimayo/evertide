<?php
declare(strict_types=1);
return function (string $url): array {

    $querypos = strpos($url, '?');
    $startpos = strpos($url, '/') + 2;
    $trailpos = strpos($url, '/', $startpos) + 1;
    if ($trailpos < $startpos) {
        $domain = $url . '/';
        $path = '';
    } else {
        $domain = substr($url, 0, $trailpos);
        $path = substr($url, 0, $querypos ? $querypos : null);
        if ($path[strlen($path) - 1] != '/') $path .= '/';
    }

    return [
        'domain' => $domain,
        'path' => $path,
        'display' => substr($url, $startpos, $querypos ? $querypos - $startpos : null)
    ];
};