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
 * Handles the API endpoints "Guild".
 *
 * @internal
 */
class Guild
{
    /**
     * Endpoints Guilds.
     *
     * @var array
     */
    const ENDPOINTS = [
        'get'          => 'guilds/%s',
        'create'       => 'guilds',
        'modify'       => 'guilds/%s',
        'delete'       => 'guilds/%s',
        'channels'     => [
            'list'            => 'guilds/%s/channels',
            'create'          => 'guilds/%s/channels',
            'modifyPositions' => 'guilds/%s/channels',
        ],
        'members'      => [
            'get'               => 'guilds/%s/members/%s',
            'list'              => 'guilds/%s/members',
            'add'               => 'guilds/%s/members/%s',
            'modify'            => 'guilds/%s/members/%s',
            'modifyCurrentNick' => 'guilds/%s/members/@me/nick',
            'addRole'           => 'guilds/%s/members/%s/roles/%s',
            'removeRole'        => 'guilds/%s/members/%s/roles/%s',
            'remove'            => 'guilds/%s/members/%s',
        ],
        'bans'         => [
            'get'    => 'guilds/%s/bans/%s',
            'list'   => 'guilds/%s/bans',
            'create' => 'guilds/%s/bans/%s',
            'remove' => 'guilds/%s/bans/%s',
        ],
        'roles'        => [
            'list'            => 'guilds/%s/roles',
            'create'          => 'guilds/%s/roles',
            'modifyPositions' => 'guilds/%s/roles',
            'modify'          => 'guilds/%s/roles/%s',
            'delete'          => 'guilds/%s/roles/%s',
        ],
        'prune'        => [
            'count' => 'guilds/%s/prune',
            'begin' => 'guilds/%s/prune',
        ],
        'voice'        => [
            'regions' => 'guilds/%s/regions',
        ],
        'invites'      => [
            'list' => 'guilds/%s/invites',
        ],
        'integrations' => [
            'list'   => 'guilds/%s/integrations',
            'create' => 'guilds/%s/integrations',
            'modify' => 'guilds/%s/integrations/%s',
            'delete' => 'guilds/%s/integrations/%s',
            'sync'   => 'guilds/%s/integrations/%s',
        ],
        'embed'        => [
            'get'    => 'guilds/%s/embed',
            'modify' => 'guilds/%s/embed',
        ],
        'audit-logs'   => 'guilds/%s/audit-logs',
        'vanity-url'   => 'guilds/%s/vanity-url',
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

    public function getGuild(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['get'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createGuild(array $options)
    {
        $url = self::ENDPOINTS['create'];

        return $this->api->makeRequest('POST', $url, ['data' => $options]);
    }

    public function modifyGuild(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['modify'], $guildid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function deleteGuild(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['delete'], $guildid);

        return $this->api->makeRequest('DELETE', $url, []);
    }

    public function getGuildChannels(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['channels']['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createGuildChannel(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['channels']['create'], $guildid);

        return $this->api->makeRequest('POST', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function modifyGuildChannelPositions(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['channels']['modifyPositions'],
            $guildid
        );

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function getGuildMember(string $guildid, string $userid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['members']['get'], $guildid, $userid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function listGuildMembers(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['members']['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function addGuildMember(string $guildid, string $userid, array $options)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['members']['add'], $guildid, $userid);

        return $this->api->makeRequest('PUT', $url, ['data' => $options]);
    }

    public function modifyGuildMember(string $guildid, string $userid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['members']['modify'],
            $guildid,
            $userid
        );

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function removeGuildMember(string $guildid, string $userid, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['members']['remove'],
            $guildid,
            $userid
        );

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function modifyCurrentNick(string $guildid, string $userid, string $nick)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['members']['modifyCurrentNick'],
            $guildid,
            $userid
        );

        return $this->api->makeRequest('PATCH', $url, ['data' => ['nick' => $nick]]);
    }

    public function addGuildMemberRole(string $guildid, string $userid, string $roleid, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['members']['addRole'],
            $guildid,
            $userid,
            $roleid
        );

        return $this->api->makeRequest('PUT', $url, ['auditLogReason' => $reason]);
    }

    public function removeGuildMemberRole(string $guildid, string $userid, string $roleid, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['members']['removeRole'],
            $guildid,
            $userid,
            $roleid
        );

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function getGuildBan(string $guildid, string $userid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['bans']['get'], $guildid, $userid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function getGuildBans(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['bans']['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createGuildBan(string $guildid, string $userid, int $daysDeleteMessages = 0, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['bans']['create'], $guildid, $userid);

        $qs = ['delete-message-days' => $daysDeleteMessages];
        if (! empty($reason)) {
            $qs['reason'] = $reason;
        }

        return $this->api->makeRequest('PUT', $url, ['auditLogReason' => $reason, 'querystring' => $qs]);
    }

    public function removeGuildBan(string $guildid, string $userid, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['bans']['remove'], $guildid, $userid);

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function getGuildRoles(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['roles']['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createGuildRole(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['roles']['create'], $guildid);

        return $this->api->makeRequest('POST', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function modifyGuildRolePositions(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['roles']['modifyPositions'], $guildid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function modifyGuildRole(string $guildid, string $roleid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['roles']['modify'], $guildid, $roleid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function deleteGuildRole(string $guildid, string $roleid, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['roles']['delete'], $guildid, $roleid);

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function getGuildPruneCount(string $guildid, int $days)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['prune']['count'], $guildid);

        return $this->api->makeRequest('GET', $url, ['querystring' => ['days' => $days]]);
    }

    public function beginGuildPrune(string $guildid, int $days, bool $withCount, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['prune']['begin'], $guildid);

        return $this->api->makeRequest(
            'POST',
            $url,
            [
                'auditLogReason' => $reason,
                'querystring'    => ['days' => $days, 'compute_prune_count' => $withCount],
            ]
        );
    }

    public function getGuildVoiceRegions(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['voice']['regions'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function getGuildInvites(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['invites']['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function getGuildIntegrations(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['integrations']['list'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function createGuildIntegration(string $guildid, array $options)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['integrations']['create'], $guildid);

        return $this->api->makeRequest('POST', $url, ['data' => $options]);
    }

    public function modifyGuildIntegration(string $guildid, string $integrationid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['integrations']['modify'],
            $guildid,
            $integrationid
        );

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function deleteGuildIntegration(string $guildid, string $integrationid, string $reason = '')
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['integrations']['delete'],
            $guildid,
            $integrationid
        );

        return $this->api->makeRequest('DELETE', $url, ['auditLogReason' => $reason]);
    }

    public function syncGuildIntegration(string $guildid, string $integrationid)
    {
        $url = APIEndpoints::format(
            self::ENDPOINTS['integrations']['sync'],
            $guildid,
            $integrationid
        );

        return $this->api->makeRequest('POST', $url, []);
    }

    public function getGuildEmbed(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['embed']['get'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }

    public function modifyGuildEmbed(string $guildid, array $options, string $reason = '')
    {
        $url = APIEndpoints::format(self::ENDPOINTS['embed']['modify'], $guildid);

        return $this->api->makeRequest('PATCH', $url, ['auditLogReason' => $reason, 'data' => $options]);
    }

    public function getGuildAuditLog(string $guildid, array $query)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['audit-logs'], $guildid);

        return $this->api->makeRequest('GET', $url, ['querystring' => $query]);
    }

    public function getGuildVanityURL(string $guildid)
    {
        $url = APIEndpoints::format(self::ENDPOINTS['vanity-url'], $guildid);

        return $this->api->makeRequest('GET', $url, []);
    }
}
