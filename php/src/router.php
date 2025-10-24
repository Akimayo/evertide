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
$first_slash = strpos($script, '/');
if ($first_slash === false) $first_slash = null;
if (!empty($script) && file_exists(dirname(__DIR__) . DIRECTORY_SEPARATOR . 'opt' . DIRECTORY_SEPARATOR . substr($script, 0, $first_slash))) {
    $_SERVER['EVERTIDE_INSTANCE'] = substr($script, 0, $first_slash);
    putenv('EVERTIDE_INSTANCE=' . $_SERVER['EVERTIDE_INSTANCE']);
    $script = is_null($first_slash) ? '' : substr($script, $first_slash + 1);
}
if (empty($script)) $script = 'index.php';
$path = dirname(__DIR__) . DIRECTORY_SEPARATOR . $script;
if (empty(pathinfo($script, PATHINFO_EXTENSION))) $path .= '.php';
if (file_exists($path)) {
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    switch ($ext) {
        case 'css':
            header('Content-Type: text/css', true);
            break;
        case 'js':
        case 'mjs':
            header('Content-Type: text/javascript', true);
            break;
        case 'json':
        case 'webmanifest':
            header('Content-Type: application/json', true);
            break;
    }
    include($path);
} else header($_SERVER['SERVER_PROTOCOL'] . ' 404 Not Found');
