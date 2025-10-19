<?php

declare(strict_types=1);

return function (ReadWriteDatabase $db, ?int $instance_id = null, bool $retry_unreachable = false): array {
    $valid_link = true;
    // Check whether instance is already known and is not unreachable or blocked
    if (is_null($instance_id)) {
        $_normalize_url = require(__DIR__ . '/normalize_url.php');
        ['domain' => $domain, 'path' => $path, 'valid_link' => $valid_link] = $_normalize_url($_POST['url']);

        $url = $domain . $path;
        $remote = null;
        try {
            $remote = InstanceDAO::getByAddress($db, $url);
            if (!$retry_unreachable && $remote->getLastLinkStatus() >= LinkStatus::UNREACHABLE) return [false, 'Remote unreachable (cache)'];
            // Continue as normal
        } catch (Exception) {
            /* Intended fail, new instance */
        }
    } else {
        $remote = InstanceDAO::get($db, $instance_id);
        $url = $remote->getLink();
    }
    $url .= 'link';
    $cfg = Config::get_config();
    $instance = $cfg->instance;

    // Request federation from the remote instance
    $context = stream_context_create([
        'http' => [
            'header' => "Content-type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
            'method' => 'POST',
            // If the remote is known, sign the request with its public key, otherwise send unsigned
            'content' => http_build_query(is_null($remote) ? $instance->getFederationInfo() : $instance->getSignedFederationInfo($remote->getPublicKey())),
            'ignore_errors' => true
        ]
    ]);
    $result = file_get_contents($url, false, $context);

    // Check whether request did not fail on HTTP layer
    if (!$result || ($result = json_decode($result)) === null) {
        if ($remote !== null) $remote->getAccessObject($db)->updateLinkStatus(LinkStatus::UNREACHABLE);
        return [false, 'Remote unreachable (invalid response)'];
    }
    // Check whether remote is an evertide instance
    $status = property_exists($result, 'status') ? $result->status : HTTP_OK;
    $message = property_exists($result, 'message') ? $result->message : null;
    if (!property_exists($result, 'instance') || empty($result->instance)) {
        // In case of fatal errors
        if ($remote !== null) $remote->getAccessObject($db)->updateLinkStatus(LinkStatus::ERROR);
        return [false, 'Remote error (missing instance)'];
    }
    // Check whether remote instance returned success
    $link_status = property_exists($result, 'categories') && is_array($result->categories) ? match ($status) {
        HTTP_OK => LinkStatus::PRELOADED,
        HTTP_REQUEST_TIMEOUT => LinkStatus::TIMED_OUT,
        HTTP_BAD_REQUEST => LinkStatus::ERROR,
        HTTP_FORBIDDEN => LinkStatus::BLOCKED,
        default => LinkStatus::UNREACHABLE
    } : LinkStatus::ERROR;
    $categories = @$result->categories;
    $result = $result->instance;
    try {
        // If instance is known, update
        $remote = InstanceDAO::getByAddress($db, $result->link)->getAccessObject($db); // This is in case the instance is a redirect - fetch by the new address
        if (
            $remote->getDomainName() != $result->domain ||
            $remote->getPrimaryColor() != $result->primary ||
            $remote->getSecondaryColor() != $result->secondary ||
            $remote->getStickerPath() != $result->sticker_path ||
            $remote->getStickerLink() != $result->sticker_link
        ) $remote->updateInstance($result->domain, $result->primary, $result->secondary, $result->sticker_path, $result->sticker_link, $link_status);
        else $remote->updateLinkStatus($link_status);
    } catch (Exception) {
        /* Intended fail, new instance */
        $remote = InstanceDAO::createFromFederationInfo($db, $result, $link_status);
    }

    // Return preloaded categories for selection by user
    $device = DeviceDAO::getCurrent($db);
    if ($link_status == LinkStatus::PRELOADED) return [
        $remote,
        array_map(function (stdClass $category) use ($device, $remote): Category {
            return new Category(
                id: -1,
                name: $category->name,
                icon: $category->icon,
                source: $remote,
                public: true,
                create_date: '',
                update_date: '',
                from_device: $device,
                links: [],
                categories: array_map(function (stdClass $category) use ($device, $remote): LeafCategory {
                    return new LeafCategory(
                        id: -1,
                        name: $category->name,
                        icon: $category->icon,
                        source: $remote,
                        public: true,
                        create_date: '',
                        update_date: '',
                        from_device: $device,
                        links: [],
                        source_id: $category->id
                    );
                }, $category->categories),
                source_id: $category->id
            );
        }, $categories)
    ];
    // Return error message when something goes wrong
    else return [false, $message ?? $link_status->name];
};
