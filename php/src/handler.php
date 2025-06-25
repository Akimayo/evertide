<?php

declare(strict_types=1);
require_once(__DIR__ . "/../vendor/autoload.php");

define('HTTP_OK', 200);
define('HTTP_CREATED', 201);
define('HTTP_ACCEPTED', 202);
define('HTTP_NON_AUTHORITATIVE_INFORMATION', 203);
define('HTTP_NOT_MODIFIED', 304);
define('HTTP_BAD_REQUEST', 400);
define('HTTP_UNAUTHORIZED', 401);
define('HTTP_FORBIDDEN', 403);
define('HTTP_NOT_FOUND', 404);
define('HTTP_METHOD_NOT_ALLOWED', 405);
define('HTTP_REQUEST_TIMEOUT', 408);
define('HTTP_INTERNAL_SERVER_ERROR', 500);
define('HTTP_NOT_IMPLEMENTED', 501);

class Handler
{
    private Latte\Engine $latte;
    private string $language;
    private Database $db;
    private bool $authorized = false;

    public function __construct()
    {
        // Instatiate Latte template engine
        $this->latte = new Latte\Engine();
        $this->latte->setTempDirectory(__DIR__ . '/../cache/');
        $this->latte->setStrictParsing();
        // Instatiate i18n and add translator extension to Latte
        $i18n = new i18n(__DIR__ . '/../assets/locale/lang_{LANGUAGE}.yml', __DIR__ . '/../cache/locale', 'en');
        $i18n->setMergeFallback(true);
        $i18n->init();
        $this->language = $i18n->getAppliedLang();
        $translator = new Latte\Essential\TranslatorExtension(function (string $original, ...$params) {
            /**
             * @disregard P1010 Undefined function 'L'
             * php-i18n generates and loads this function dynamically
             */
            return L($original, $params);
        });
        $this->latte->addExtension($translator);
        // Add Phosphor Icons function
        $this->latte->addFunction('icon', function (string $name, string $weight = ''): string {
            if ($weight === 'fill') $weight = 'ph-fill';
            else $weight = 'ph';
            return $weight . ' ph-' . htmlspecialchars($name, ENT_DISALLOWED);
        });
        // Add spiral rendering function
        $this->latte->addFunction('spiral', require(__DIR__ . '/functions/render-spiral.php'));
        // Connect to database
        require_once(__DIR__ . '/database.php');
        $this->db = new ServerDatabase($this);

        // Set and check cookie authorization
        $_auth_fn = require(__DIR__ . '/functions/auth.php');
        $this->authorized = $_auth_fn($this->db);
        $this->db = $this->authorized ? new ReadWriteDatabase($this) : new ReadOnlyDatabase($this);

        // TODO: Add https://darkvisitors.com/docs/robots-txt
    }

    public function render(string $template, object|array $params = [], bool $allow_fetch = false): static
    {
        $params['authorized'] = $this->authorized;
        $instance = Config::get_config()->instance;
        if (
            !$allow_fetch ||
            (!empty($_SERVER['HTTP_ACCEPT']) && strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === 0)
        ) {
            $params['language'] = $this->language;
            $params['instance'] = $instance;
            $this->latte->render(__DIR__ . '/../templates/' . $template, $params);
        } else {
            $params['instance'] = [
                'domain' => $instance->getDisplayName(),
                'link' => $instance->getLink(),
                'primary' => $instance->getPrimaryColor(),
                'secondary' => $instance->getSecondaryColor()
            ];
            header('Content-Type: application/json');
            echo json_encode($params);
        }
        return $this;
    }
    public function status(int $status): static
    {
        $key = 'http_' . $status;
        if (property_exists(L::class, $key))
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status . ' ' . L($key));
        else
            header($_SERVER['SERVER_PROTOCOL'] . ' ' . $status);
        return $this;
    }
    public function error(int $status, ?string $message = null): static
    {
        return $this->status($status)
            ->render('error.latte', ['status' => $status, 'message' => $message], true);
    }
    public function redirect(string $location): static
    {
        header('Location: ' . $location);
        return $this;
    }
    public function getDatabase(): Database
    {
        return $this->db;
    }
    public function isAuthorized(): bool
    {
        return $this->authorized;
    }
}
return new Handler();
