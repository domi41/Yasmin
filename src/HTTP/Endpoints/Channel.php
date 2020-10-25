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
 * Handles the API endpoints "Channel".
 *
 * @internal
 */
class Channel
{
    /**
     * Endpoints Channels.
     *
     * @var array
     */
    const ENDPOINTS = [
        'get'         => 'channels/%s',
        'modify'      => 'channels/%s',
        'delete'      => 'channels/%s',
        'messages'    => [
            'list'       => 'channels/%s/messages',
            'get'        => 'channels/%s/messages/%s',
            'create'     => 'channels/%s/messages',
            'reactions'  => [
                'create'    => 'channels/%s/messages/%s/reactions/%s/@me',
                'delete'    => 'channels/%s/messages/%s/reactions/%s/%s',
                'get'       => 'channels/%s/messages/%s/reactions/%s',
                'deleteAll' => 'channels/%s/messages/%s/reactions',
            ],
            'edit'       => 'channels/%s/messages/%s',
            'delete'     => 'channels/%s/messages/%s',
            'bulkDelete' => 'channels/%s/messages/bulk-delete',
        ],
        'permissions' => [
            'edit'   => 'channels/%s/permissions/%s',
            'delete' => 'channels/%s/permissions/%s',
        ],
        'invites'     => [
            'list'   => 'channels/%s/invites',
            'create' => 'channels/%s/invites',
        ],
        'typing'      => 'channels/%s/typing',
        'pins'        => [
            'list'   => 'channels/%s/pins',
            'add'    => 'channels/%s/pins/%s',
            'delete' => 'channels/%s/pins/%s',
        ],
        'groupDM'     => [
            'add'    => 'channels/%s/recipients/%s',
            'remove' => 'channels/%s/recipients/%s',
        ],
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
    public function __construct(APIManager $api)
    {
        $this->api = $api;
    }

    public function getChannel(string $channelid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['get'], $channelid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function modifyChannel(string $channelid, array $data, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['modify'], $channelid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $data]);
    }

    public function deleteChannel(string $channelid, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['delete'], $channelid);

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function getChannelMessages(string $channelid, array $options = [])
    {
        $url = APIEndpoints::format(self::ENDPOINTS['messages']['list'], $channelid);

        return $this->api->makeRequest('GET', $url, ['querystring' => $options]);
    }

    public function getChannelMessage(string $channelid, string $messageid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['get'],
            $channelid,
            $messageid
        );

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createMessage(string $channelid, array $options, array $files = [])
    {
        $url = APIEndpoints::format(self::ENDPOINTS['messages']['create'], $channelid);

        return $this->api->makeRequest('POST', $url, ['data' => $options, 'files' => $files]);
    }

    public function editMessage(string $channelid, string $messageid, array $options)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['edit'],
            $channelid,
            $messageid
        );

        return $this->api->makeRequest('PATCH', $url, ['data' => $options]);
    }

    public function deleteMessage(string $channelid, string $messageid, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['delete'],
            $channelid,
            $messageid
        );

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function bulkDeleteMessages(string $channelid, array $snowflakes, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['messages']['bulkDelete'], $channelid);

        return $this->api->makeRequest(
            'POST',
            $url,
            ['auditLogReason' => $reason, 'data' => ['messages' => $snowflakes]]
        );
    }

    public function createMessageReaction(string $channelid, string $messageid, string $emoji)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['reactions']['create'],
            $channelid,
            $messageid,
            $emoji
        );

        return $this->api->makeRequest('PUT', $url, ['reactionRatelimit' => true]);
    }

    public function deleteMessageUserReaction(string $channelid, string $messageid, string $emoji, string $userid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['reactions']['delete'],
            $channelid,
            $messageid,
            $emoji,
            $userid
        );

        return $this->api->makeRequest('DELETE', $url, ['reactionRatelimit' => true]);
    }

    public function getMessageReactions(string $channelid, string $messageid, string $emoji, array $querystring = [])
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['reactions']['get'],
            $channelid,
            $messageid,
            $emoji
        );

        return $this->api->makeRequest('GET', $url, ['querystring' => $querystring]);
    }

    public function deleteMessageReactions(string $channelid, string $messageid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['messages']['reactions']['deleteAll'],
            $channelid,
            $messageid
        );

        return $this->api->makeRequest('DELETE', $url, []);
    }

    public function editChannelPermissions(string $channelid, string $overwriteid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['permissions']['edit'],
            $channelid,
            $overwriteid
        );

        return $this->api->makeRequest('PUT', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function deleteChannelPermission(string $channelid, string $overwriteid, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['permissions']['delete'],
            $channelid,
            $overwriteid
        );

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function getChannelInvites(string $channelid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['invites']['list'], $channelid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createChannelInvite(string $channelid, array $options = [])
    {
        $url = APIEndpoints::format(self::ENDPOINTS['invites']['create'], $channelid);

        return $this->api->makeRequest('POST', $url, ['data' => $options]);
    }

    public function triggerChannelTyping(string $channelid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['typing'], $channelid);

        return $this->api->makeRequest('POST', $url, []);
    }

    public function getPinnedChannelMessages(string $channelid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['pins']['list'], $channelid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function pinChannelMessage(string $channelid, string $messageid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['pins']['add'],
            $channelid,
            $messageid
        );

        return $this->api->makeRequest('PUT', $url, []);
    }

    public function unpinChannelMessage(string $channelid, string $messageid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['pins']['delete'],
            $channelid,
            $messageid
        );

        return $this->api->makeRequest('DELETE', $url, []);
    }

    public function groupDMAddRecipient(string $channelid, string $userid, string $accessToken, string $nick)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['groupDM']['add'],
            $channelid,
            $userid
        );

        return $this->api->makeRequest('PUT', $url, ['data' => ['access_token' => $accessToken, 'nick' => $nick]]);
    }

    public function groupDMRemoveRecipient(string $channelid, string $userid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['groupDM']['remove'],
            $channelid,
            $userid
        );

        return $this->api->makeRequest('DELETE', $url, []);
    }
}
