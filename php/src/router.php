<?php
/* 
 * FOR DEVELOPMENT PURPOSES ONLY
 *
 * This script is used to properly route URLs without file extensions
 * when running the built-in PHP server. It can safely be deleted in
 * production use.
 * 
 * Running:
 * php -S localhost:80 src/router.php
 */
$query_pos = strpos($_SERVER['REQUEST_URI'], '?');
$script = $query_pos ? substr($_SERVER['REQUEST_URI'], 1, $query_pos - 1) : substr($_SERVER['REQUEST_URI'], 1);
if (empty($script)) $script = 'index.php';
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $script;
if (empty(pathinfo($script, PATHINFO_EXTENSION))) $path .= '.php';
if (file_exists($path)) {
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'css':
            header('Content-Type: text/css', true);
            break;
    }
    include($path);
} else header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
