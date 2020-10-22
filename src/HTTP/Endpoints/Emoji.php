<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\HTTP\Endpoints;

use CharlotteDunois\Yasmin\HTTP\APIEndpoints;
use CharlotteDunois\Yasmin\HTTP\APIManager;

/**
 * Handles the API endpoints "Emoji".
 *
 * @internal
 */
class Emoji
{
    /**
     * Endpoints Emojis.
     *
     * @var array
     */
    const ENDPOINTS = [
        'list'   => 'guilds/%s/emojis',
        'get'    => 'guilds/%s/emojis/%s',
        'create' => 'guilds/%s/emojis',
        'modify' => 'guilds/%s/emojis/%s',
        'delete' => 'guilds/%s/emojis/%s',
    ];

    /**
     * @var APIManager
     */
    protected $api;

    /**
     * Constructor.
     *
     * @param  APIManager  $api
     */
    function __construct(APIManager $api)
    {
        $this->api = $api;
    }

    function listGuildEmojis(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    function getGuildEmoji(string $guildid, string $emojiid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['get'], $guildid, $emojiid);

        return $this->api->makeRequest('GET', $url, []);
    }

    function createGuildEmoji(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['create'], $guildid);

        return $this->api->makeRequest('POST', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    function modifyGuildEmoji(string $guildid, string $emojiid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['modify'], $guildid, $emojiid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    function deleteGuildEmoji(string $guildid, string $emojiid, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['delete'], $guildid, $emojiid);

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }
}
