<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\HTTP;

use CharlotteDunois\Yasmin\HTTP\Endpoints\Channel;
use CharlotteDunois\Yasmin\HTTP\Endpoints\Emoji;
use CharlotteDunois\Yasmin\HTTP\Endpoints\Guild;
use CharlotteDunois\Yasmin\HTTP\Endpoints\Invite;
use CharlotteDunois\Yasmin\HTTP\Endpoints\User;
use CharlotteDunois\Yasmin\HTTP\Endpoints\Voice;
use CharlotteDunois\Yasmin\HTTP\Endpoints\Webhook;

/**
 * Handles the API endpoints.
 *
 * @internal
 */
class APIEndpoints
{
    /**
     * CDN constants.
     *
     * @var array
     * @internal
     */
    const CDN = [
        'url'            => 'https://cdn.discordapp.com/',
        'emojis'         => 'emojis/%s.%s',
        'icons'          => 'icons/%s/%s.%s',
        'splashes'       => 'splashes/%s/%s.%s',
        'defaultavatars' => 'embed/avatars/%s.%s',
        'avatars'        => 'avatars/%s/%s.%s',
        'appicons'       => 'app-icons/%s/%s.png',
        'appassets'      => 'app-assets/%s/%s.png',
        'channelicons'   => 'channel-icons/%s/%s.png',
        'guildbanners'   => 'banners/%s/%s.%s',
    ];

    /**
     * HTTP constants.
     *
     * @var array
     * @internal
     */
    const HTTP = [
        'url'     => 'https://discord.com/api/',
        'version' => 8,
        'invite'  => 'https://discord.gg/',
    ];

    /**
     * Endpoints General.
     *
     * @var array
     * @internal
     */
    const ENDPOINTS = [
        'currentOAuthApplication' => 'oauth2/applications/@me',
    ];

    /**
     * The API manager.
     *
     * @var APIManager
     */
    protected $api;

    /**
     * The channel endpoints.
     *
     * @var Channel
     */
    public $channel;

    /**
     * The emoji endpoints.
     *
     * @var Emoji
     */
    public $emoji;

    /**
     * The guild endpoints.
     *
     * @var Guild
     */
    public $guild;

    /**
     * The invite endpoints.
     *
     * @var Invite
     */
    public $invite;

    /**
     * The user endpoints.
     *
     * @var User
     */
    public $user;

    /**
     * The voice endpoints.
     *
     * @var Voice
     */
    public $voice;

    /**
     * The webhook endpoints.
     *
     * @var Webhook
     */
    public $webhook;

    /**
     * DO NOT initialize this class yourself.
     *
     * @param  APIManager  $api
     */
    public function __construct(APIManager $api)
    {
        $this->api = $api;

        $this->channel = new Channel($api);
        $this->emoji = new Emoji($api);
        $this->guild = new Guild($api);
        $this->invite = new Invite($api);
        $this->user = new User($api);
        $this->voice = new Voice($api);
        $this->webhook = new Webhook($api);
    }

    /**
     * Gets the current OAuth application.
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    public function getCurrentApplication()
    {
        $url = APIEndpoints::ENDPOINTS['currentOAuthApplication'];

        return $this->api->makeRequest('GET', $url, []);
    }

    /**
     * Formats Endpoints strings.
     *
     * @param  string  $endpoint
     * @param  string  ...$args
     *
     * @return string
     */
    public static function format(string $endpoint, ...$args)
    {
        return sprintf($endpoint, ...$args);
    }
}
