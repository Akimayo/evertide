<?php

declare(strict_types=1);
require_once(__DIR__ . "/../vendor/autoload.php");
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
        $i18n = new i18n(__DIR__ . '/../../web/locale/lang_{LANGUAGE}.yml', __DIR__ . '/../cache/locale', 'en');
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

        // Connect to database
        require_once(__DIR__ . '/database.php');
        $this->db = new ServerDatabase($this);

        // Set and check cookie authorization
        $_auth_fn = require(__DIR__ . '/functions/auth.php');
        $this->authorized = $_auth_fn($this->db);
        $this->db = $this->authorized ? new ReadWriteDatabase($this) : new ReadOnlyDatabase($this);

        // TODO: Add https://darkvisitors.com/docs/robots-txt
    }

    public function render(string $template, object|array $params = []): void
    {
        $params['language'] = $this->language;
        $this->latte->render(__DIR__ . '/../templates/' . $template, $params);
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
