<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\HTTP\Endpoints;

use CharlotteDunois\Yasmin\HTTP\APIEndpoints;
use CharlotteDunois\Yasmin\HTTP\APIManager;

/**
 * Handles the API endpoints "Webhook".
 *
 * @internal
 */
class Webhook
{
    /**
     * Endpoints Webhooks.
     *
     * @var array
     */
    const ENDPOINTS = [
        'create'      => 'channels/%s/webhooks',
        'channels'    => 'channels/%s/webhooks',
        'guilds'      => 'guilds/%s/webhooks',
        'get'         => 'webhooks/%s',
        'getToken'    => 'webhooks/%s/%s',
        'modify'      => 'webhooks/%s',
        'modifyToken' => 'webhooks/%s/%s',
        'delete'      => 'webhooks/%s',
        'deleteToken' => 'webhooks/%s/%s',
        'execute'     => 'webhooks/%s/%s',
    ];

    /**
     * Constructor.
     *
     * @var APIManager
     */
    protected $api;

    /**
     * @param  APIManager  $api
     */
    public function __construct(APIManager $api)
    {
        $this->api = $api;
    }

    public function createWebhook(string $channelid, string $name, ?string $avatarBase64 = null, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['create'], $channelid);

        return $this->api->makeRequest(
            'POST',
            $url,
            [
                'auditLogReason' => $reason,
                'data'           => ['name' => $name, 'avatar' => $avatarBase64],
            ]
        );
    }

    public function getChannelWebhooks(string $channelid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['channels'], $channelid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function getGuildsWebhooks(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['guilds'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function getWebhook(string $webhookid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['get'], $webhookid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function getWebhookToken(string $webhookid, string $token)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['getToken'], $webhookid, $token);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function modifyWebhook(string $webhookid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['modify'], $webhookid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function modifyWebhookToken(string $webhookid, string $token, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['modifyToken'], $webhookid, $token);

        return $this->api->makeRequest(
            'PATCH',
            $url,
            ['auditLogReason' => $reason, 'data' => $options, 'noAuth' => true]
        );
    }

    public function deleteWebhook(string $webhookid, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['delete'], $webhookid);

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function deleteWebhookToken(string $webhookid, string $token, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['deleteToken'], $webhookid, $token);

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason, 'noAuth' => true]);
    }

    public function executeWebhook(
        string $webhookid,
        string $token,
        array $options,
        array $files = [],
        array $querystring = []
    ) {
        $url = APIEndpoints::format(self::ENDPOINTS['execute'], $webhookid, $token);

        return $this->api->makeRequest(
            'POST',
            $url,
            [
                'data'        => $options,
                'files'       => $files,
                'noAuth'      => true,
                'querystring' => $querystring,
            ]
        );
    }
}
