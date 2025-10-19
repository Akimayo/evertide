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

    $reserved_domains = ['.example', 'example.com', 'example.org', 'example.net', '.invalid', '.local', '.localhost', '.test', 'localhost'];
    $local_ip_ranges = ['192.168.', '10.', '172.16.', '169.254.', 'fe80:', '127.0.'];
    $port_pos = strrpos($domain, ':');
    if ($port_pos !== false) $domain_without_port = substr($domain, $startpos, $port_pos - $startpos);
    else $domain_without_port = substr($domain, $startpos, $trailpos - $startpos - 1);
    $valid_link = true;
    // Check reserved domains
    foreach ($reserved_domains as $dom) {
        if (strrpos($domain_without_port, $dom) === 0) {
            $valid_link = false;
            break;
        }
    }
    // Check IPv6 link-local
    if ($valid_link && $domain_without_port == '::1') $valid_link = false;
    // Check local IPv4 addresses
    if ($valid_link) {
        foreach ($local_ip_ranges as $ip) {
            if (strpos($domain_without_port, $ip) === 0) {
                $valid_link = false;
                break;
            }
        }
    }

    return [
        'domain' => $domain,
        'path' => $path,
        'display' => substr($url, $startpos, $querypos ? $querypos - $startpos : null),
        'valid_link' => $valid_link
    ];
};
