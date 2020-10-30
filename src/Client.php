<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Events\EventEmitterErrorTrait;
use CharlotteDunois\Events\EventEmitterInterface;
use CharlotteDunois\Validation\Validator;
use CharlotteDunois\Yasmin\HTTP\APIManager;
use CharlotteDunois\Yasmin\Interfaces\ChannelStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\EmojiStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildMemberStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\MessageStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\PresenceStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\RatelimitBucketInterface;
use CharlotteDunois\Yasmin\Interfaces\RoleStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\UserStorageInterface;
use CharlotteDunois\Yasmin\Models\CategoryChannel;
use CharlotteDunois\Yasmin\Models\ChannelStorage;
use CharlotteDunois\Yasmin\Models\ClientUser;
use CharlotteDunois\Yasmin\Models\EmojiStorage;
use CharlotteDunois\Yasmin\Models\GuildMemberStorage;
use CharlotteDunois\Yasmin\Models\GuildStorage;
use CharlotteDunois\Yasmin\Models\Invite;
use CharlotteDunois\Yasmin\Models\MessageStorage;
use CharlotteDunois\Yasmin\Models\OAuthApplication;
use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\PresenceStorage;
use CharlotteDunois\Yasmin\Models\RoleStorage;
use CharlotteDunois\Yasmin\Models\Shard;
use CharlotteDunois\Yasmin\Models\User;
use CharlotteDunois\Yasmin\Models\UserStorage;
use CharlotteDunois\Yasmin\Models\VoiceRegion;
use CharlotteDunois\Yasmin\Models\Webhook;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\FileHelpers;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;
use Exception;
use InvalidArgumentException;
use RangeException;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\Timer;
use React\EventLoop\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use RuntimeException;

use function React\Promise\resolve;

/**
 * The client. What else do you expect this to say?
 *
 * @property LoopInterface $loop       The event loop.
 * @property ChannelStorageInterface $channels   Holds all cached channels, mapped by ID.
 * @property EmojiStorageInterface $emojis     Holds all emojis, mapped by ID (custom emojis) and/or name (unicode emojis).
 * @property GuildStorageInterface $guilds     Holds all guilds, mapped by ID.
 * @property PresenceStorageInterface $presences  Holds all cached presences (latest ones), mapped by user ID.
 * @property UserStorageInterface $users      Holds all cached users, mapped by ID.
 * @property int[] $pings      The last 3 websocket pings of each shard.
 * @property Collection $shards     Holds all shards, mapped by shard ID.
 * @property ClientUser|null $user       User that the client is logged in as. The instance gets created when the client turns ready.
 *
 * @method on(string $event, callable $listener)               Attach a listener to an event. The method is from the trait - only for documentation purpose here.
 * @method once(string $event, callable $listener)             Attach a listener to an event, for exactly once. The method is from the trait - only for documentation purpose here.
 * @method removeListener(string $event, callable $listener)   Remove specified listener from an event. The method is from the trait - only for documentation purpose here.
 * @method removeAllListeners($event = null)                   Remove all listeners from an event ( or all listeners).
 */
class Client implements EventEmitterInterface
{
    use EventEmitterErrorTrait;

    /**
     * The version of Yasmin.
     *
     * @var string
     */
    const VERSION = '1.0.0-dev';

    /**
     * WS connection status: Disconnected.
     *
     * @var int
     */
    const WS_STATUS_DISCONNECTED = 0;

    /**
     * WS connection status: Connecting.
     *
     * @var int
     */
    const WS_STATUS_CONNECTING = 1;

    /**
     * WS connection status: Reconnecting.
     *
     * @var int
     */
    const WS_STATUS_RECONNECTING = 2;

    /**
     * WS connection status: Connected (not ready yet - nearly).
     *
     * @var int
     */
    const WS_STATUS_NEARLY = 3;

    /**
     * WS connection status: Connected (ready).
     *
     * @var int
     */
    const WS_STATUS_CONNECTED = 4;

    /**
     * WS connection status: Idling (disconnected and no reconnect planned).
     *
     * @var int
     */
    const WS_STATUS_IDLE = 5;

    /**
     * WS default compression.
     *
     * @var string
     */
    const WS_DEFAULT_COMPRESSION = 'zlib-stream';

    /**
     * It holds all cached channels, mapped by ID.
     *
     * @var ChannelStorage
     * @internal
     */
    protected $channels;

    /**
     * It holds all emojis, mapped by ID (custom emojis) and/or name (unicode emojis).
     *
     * @var EmojiStorage
     * @internal
     */
    protected $emojis;

    /**
     * It holds all guilds, mapped by ID.
     *
     * @var GuildStorage
     * @internal
     */
    protected $guilds;

    /**
     * It holds all cached presences (latest ones), mapped by user ID.
     *
     * @var PresenceStorage
     * @internal
     */
    protected $presences;

    /**
     * It holds all cached users, mapped by ID.
     *
     * @var UserStorage
     * @internal
     */
    protected $users;

    /**
     * Holds all shards, mapped by shard ID.
     *
     * @var Collection
     * @internal
     */
    protected $shards;

    /**
     * The UNIX timestamp of the last emitted ready event (or null if none yet).
     *
     * @var int|null
     */
    public $readyTimestamp = null;

    /**
     * The token.
     *
     * @var string|null
     */
    public $token;

    /**
     * The event loop.
     *
     * @var LoopInterface
     * @internal
     */
    protected $loop;

    /**
     * Client Options.
     *
     * @var array
     * @internal
     */
    protected $options = [];

    /**
     * The Client User.
     *
     * @var ClientUser|null
     * @internal
     */
    protected $user;

    /**
     * The API manager.
     *
     * @var APIManager
     * @internal
     */
    protected $api;

    /**
     * The WS manager.
     *
     * @var WSManager|null
     * @internal
     */
    protected $ws;

    /**
     * Gateway address information.
     *
     * @var array
     * @internal
     */
    protected $gateway;

    /**
     * Timers which automatically get cancelled on destroy and only get run when we have a WS connection.
     *
     * @var array
     * @internal
     */
    protected $timers = [];

    /**
     * Loaded Utils with a loop instance.
     *
     * @var array
     * @internal
     */
    protected $utils = [];

    /**
     * Events queue, until client turns ready.
     *
     * @var array
     * @internal
     */
    protected $eventsQueue = [];

    /**
     * What do you expect this to do? It makes a new Client instance. Available client options are as following (all are optional):.
     *
     * ```
     * array(
     *   'disableClones' => bool|string[], (disables cloning of class instances (for perfomance), affects update events - bool: true - disables all cloning)
     *   'disableEveryone' => bool, (disables the everyone and here mentions and replaces them with plaintext, defaults to true)
     *   'fetchAllMembers' => bool, (fetches all guild members, this should be avoided - necessary members get automatically fetched)
     *   'messageCache' => bool, (enables message cache, defaults to true)
     *   'messageCacheLifetime' => int, (invalidates messages in the store older than the specified duration)
     *   'messageSweepInterval' => int, (interval when the message cache gets invalidated (see messageCacheLifetime), defaults to messageCacheLifetime)
     *   'presenceCache' => bool, (enables presence cache, defaults to true)
     *   'minShardID' => int, (minimum shard ID to spawn - 0-indexed, if omitted, the client will determine the shards to spawn themself)
     *   'maxShardID' => int, (maximum shard ID to spawn - 0-indexed, if omitted, the client will determine the shards to spawn themself)
     *   'shardCount' => int, (shard count, if omitted, the client will determine the shards to spawn themself)
     *   'userSweepInterval' => int, (interval when the user cache gets invalidated (users sharing no mutual guilds get removed), defaults to 600)
     *   'http.ratelimitbucket.name' => string, (class name of the custom ratelimit bucket, has to implement the interface)
     *   'http.restTimeOffset' => int|float, (specifies how many seconds should be waited after one REST request before the next REST request should be done)
     *   'http.requestErrorDelay' => int, (specifies how many seconds should be waited after a HTTP 5XX error before queueing the request again, after two retries the time will be doubled, defaults to 30 - minimum 15)
     *   'http.requestMaxRetries' => int, (specifies how many times the request should be retried on HTTP 5XX until we give up, defaults to 0 (never give up))
     *   'ws.compression' => string, (Enables a specific one, defaults to zlib-stream, which is currently the only available compression)
     *   'ws.encoding' => string, (use a specific websocket encoding, JSON or ETF (if suggested package installed), recommended is JSON for now)
     *   'ws.disabledEvents' => string[], (disables specific websocket events (e.g. TYPING_START), only disable websocket events if you know what they do)
     *   'ws.largeThreshold' => int, (50-250, members threshold after which guilds gets counted as large, defaults to 250)
     *   'ws.presence' => array, (the presence to send on WS connect, see See Also section)
     *   'ws.presenceUpdate.ignoreUnknownUsers' => bool, (whether we ignore presence updates of uncached users, defaults to false)
     * )
     * ```
     *
     * @param  array  $options  Any client options.
     * @param  LoopInterface  $loop  You can pass an event loop to the class, or it will automatically create one (you still need to make it run yourself).
     *
     * @throws Exception
     * @throws RuntimeException
     * @throws InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\ClientEvents
     * @see https://discordapp.com/developers/docs/topics/gateway#update-status
     */
    public function __construct(array $options = [], ?LoopInterface $loop = null)
    {
        if (PHP_SAPI !== 'cli') {
            throw new Exception('Yasmin can only be used in the CLI SAPI. Please use PHP CLI to run Yasmin.');
        }

        if (! empty($options)) {
            $this->validateClientOptions($options);
            $this->options = array_merge($this->options, $options);
        }

        if (! $loop) {
            $loop = Factory::create();
        }

        $this->loop = $loop;

        // ONLY use this if you know to 100% the consequences and know what you are doing
        if (! empty($options['internal.api.instance'])) {
            if (is_string($options['internal.api.instance'])) {
                $api = $options['internal.api.instance'];
                $this->api = new $api($this);
            } else {
                $this->api = $options['internal.api.instance'];
            }
        } else {
            $this->api = new APIManager($this);
        }

        // ONLY use this if you know to 100% the consequences and know what you are doing
        if (($options['internal.ws.disable'] ?? false) !== true) {
            // ONLY use this if you know to 100% the consequences and know what you are doing
            if (! empty($options['internal.ws.instance'])) {
                if (is_string($options['internal.ws.instance'])) {
                    $ws = $options['internal.ws.instance'];
                    $this->ws = new $ws($this);
                } else {
                    $this->ws = $options['internal.ws.instance'];
                }
            } else {
                $this->ws = new WSManager($this);
            }

            $this->ws->on(
                'ready',
                function () {
                    $this->readyTimestamp = time();
                    $this->emit('ready');
                }
            );

            $this->ws->once(
                'ready',
                function () {
                    while ([$event, $args] = array_shift($this->eventsQueue)) {
                        $this->emit($event, ...$args);
                    }
                }
            );
        }

        $this->checkOptionsStorages();

        $this->channels = new $this->options['internal.storages.channels']($this);
        $this->emojis = new $this->options['internal.storages.emojis']($this);
        $this->guilds = new $this->options['internal.storages.guilds']($this);
        $this->presences = new $this->options['internal.storages.presences']($this);
        $this->users = new $this->options['internal.storages.users']($this);

        $this->shards = new Collection();

        $this->registerUtils();
    }

    /**
     * @param  string  $name
     *
     * @return bool
     * @throws Exception
     * @internal
     */
    public function __isset($name)
    {
        try {
            return $this->$name !== null;
        } catch (RuntimeException $e) {
            if ($e->getTrace()[0]['function'] === '__get') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @param  string  $name
     *
     * @return mixed
     * @throws RuntimeException
     * @internal
     */
    public function __get($name)
    {
        $props = ['loop', 'channels', 'emojis', 'guilds', 'presences', 'users', 'shards', 'user'];

        if (in_array($name, $props)) {
            return $this->$name;
        }

        switch ($name) {
            case 'pings':
                $pings = [];

                foreach ($this->shards as $shard) {
                    $pings = array_merge($pings, $shard->ws->pings);
                }

                return $pings;
                break;
        }

        throw new RuntimeException('Unknown property '.get_class($this).'::$'.$name);
    }

    /**
     * You don't need to know.
     *
     * @return APIManager
     * @internal
     */
    public function apimanager()
    {
        return $this->api;
    }

    /**
     * You don't need to know.
     *
     * @return WSManager
     * @internal
     */
    public function wsmanager()
    {
        return $this->ws;
    }

    /**
     * Get the React Event Loop that is stored in this class.
     *
     * @return LoopInterface
     */
    public function getLoop()
    {
        return $this->loop;
    }

    /**
     * Get a specific option, or the default value.
     *
     * @param  string  $name
     * @param  mixed  $default
     *
     * @return mixed
     */
    public function getOption($name, $default = null)
    {
        if (isset($this->options[$name])) {
            return $this->options[$name];
        }

        return $default;
    }

    /**
     * Calculates the average ping. Or NAN.
     *
     * @return int|float
     */
    public function getPing()
    {
        $pings = $this->pings;
        $cpings = count($pings);

        if ($cpings === 0) {
            return NAN;
        }

        return (int) ceil(array_sum($pings) / $cpings);
    }

    /**
     * Returns the computed WS status across all shards.
     *
     * @return int
     */
    public function getWSstatus()
    {
        $largest = 0;

        foreach ($this->shards as $shard) {
            $largest = ($shard->ws->status > $largest ? $shard->ws->status : $largest);

            if ($shard->ws->status === self::WS_STATUS_CONNECTED) {
                return self::WS_STATUS_CONNECTED;
            }
        }

        return $largest;
    }

    /**
     * Login into Discord. Opens a WebSocket Gateway connection. Resolves once a WebSocket connection has been successfully established (does not mean the client is ready).
     *
     * @param  string  $token  Your token.
     * @param  bool  $force  Forces the client to get the gateway address from Discord.
     *
     * @return ExtendedPromiseInterface
     * @throws RuntimeException
     */
    public function login(string $token, bool $force = false)
    {
        $token = trim($token);

        if (empty($token)) {
            throw new RuntimeException('Token can not be empty');
        }

        $this->token = $token;

        return new Promise(
            function (callable $resolve, callable $reject) use ($force) {
                if ($this->ws === null) {
                    return $resolve();
                }

                if (! empty($this->gateway) && ! $force) {
                    $gateway = resolve($this->gateway);
                } else {
                    $gateway = $this->api->getGateway(true);
                }

                $gateway->then(
                    function (array $url) {
                        $this->gateway = $url;

                        $wsquery = WSManager::WS;
                        $encoding = $this->getOption('ws.encoding');

                        if (! empty($encoding) && is_string($encoding)) {
                            $wsquery['encoding'] = $encoding;
                        }

                        $minShard = $this->getOption('minShardID');
                        $maxShard = $this->getOption('maxShardID');

                        if ($minShard === null || $maxShard === null || $this->getOption('shardCount') === null) {
                            $minShard = 0;
                            $maxShard = $url['shards'] - 1;
                            $this->options['shardCount'] = (int) $url['shards'];
                        }

                        $this->options['numShards'] = $maxShard - $minShard + 1;

                        $remlogin = ($url['remaining'] ?? INF);
                        if ($remlogin < $this->options['numShards']) {
                            throw new RangeException(
                                'Remaining gateway identify limit is not sufficient ('.$remlogin.' - '.$this->options['numShards'].' shards)'
                            );
                        }

                        $prom = resolve();

                        for ($shard = $minShard; $shard <= $maxShard; $shard++) {
                            $prom = $prom->then(
                                function () use ($shard, $url, $wsquery) {
                                    $prom = $this->ws->connectShard($shard, $url['url'], $wsquery);

                                    if (! $this->shards->has($shard)) {
                                        $prom = $prom->then(
                                            function (WSConnection $ws) use ($shard) {
                                                $shard = new Shard($this, $shard, $ws);
                                                $this->shards->set($shard->id, $shard);
                                            }
                                        );
                                    }

                                    return $prom;
                                }
                            );
                        }

                        return $prom->then(
                            function () {
                                return null;
                            }
                        );
                    }
                )->done(
                    $resolve,
                    function ($error) use ($reject) {
                        $this->api->clear();
                        $this->ws->destroy();

                        $this->cancelTimers();
                        $this->destroyUtils();

                        $this->emit('error', $error);
                        $reject($error);
                    }
                );
            }
        );
    }

    /**
     * Cleanly logs out of Discord.
     *
     * @param  bool  $destroyUtils  Stop timers of utils which have an instance of the event loop.
     *
     * @return ExtendedPromiseInterface
     */
    public function destroy(bool $destroyUtils = true)
    {
        return new Promise(
            function (callable $resolve) use ($destroyUtils) {
                if ($this->api !== null) {
                    $this->api->clear();
                }

                if ($this->ws !== null) {
                    $this->ws->destroy();
                }

                $this->cancelTimers();

                if ($destroyUtils) {
                    $this->destroyUtils();
                }

                $resolve();
            }
        );
    }

    /**
     * Creates a new guild. Resolves with an instance of Guild. Options is as following, everything is optional unless specified:.
     *
     * ```
     * array(
     *   'name' => string, (required)
     *   'region' => \CharlotteDunois\Yasmin\Models\VoiceRegion|string, (required)
     *   'icon' => string, (an URL, a filepath or data)
     *   'verificationLevel' => int, (0-4)
     *   'defaultMessageNotifications' => int, (0 or 1)
     *   'explicitContentFilter' => int, (0-2)
     *   'roles' => array, (an array of role arrays*)
     *   'channels' => array (an array of channel arrays**)
     *
     *     * array( // role array
     *     *   'name' => string, (required)
     *     *   'permissions' => \CharlotteDunois\Yasmin\Models\Permissions|int,
     *     *   'color' => int|string,
     *     *   'hoist' => bool,
     *     *   'mentionable' => bool
     *     * )
     *
     *     ** array( // channel array
     *     **   'name' => string, (required)
     *     **   'type' => 'text'|'voice', (category is not supported by the API, defaults to 'text')
     *     **   'bitrate' => int, (only for voice channels)
     *     **   'userLimit' => int, (only for voice channels, 0 = unlimited)
     *     **   'permissionOverwrites' => array, (an array of permission overwrite arrays***)
     *     **   'nsfw' => bool (only for text channels)
     *     ** )
     *
     *     *** array( // overwrite array, all required
     *     ***   'id' => \CharlotteDunois\Yasmin\Models\User|string, (string = user ID or role name (of above role array!))
     *     ***   'allow' => \CharlotteDunois\Yasmin\Models\Permissions|int,
     *     ***   'deny' => \CharlotteDunois\Yasmin\Models\Permissions|int
     *     *** )
     * )
     * ```
     *
     * @param  array  $options
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\Models\Guild
     */
    public function createGuild(array $options)
    {
        if (empty($options['name'])) {
            throw new InvalidArgumentException('Guild name can not be empty');
        }

        if (empty($options['region'])) {
            throw new InvalidArgumentException('Guild region can not be empty');
        }

        return new Promise(
            function (callable $resolve, callable $reject) use ($options) {
                $data = [
                    'name'                          => $options['name'],
                    'region'                        => ($options['region'] instanceof VoiceRegion ? $options['region']->id : $options['region']),
                    'verification_level'            => ((int) ($options['verificationLevel'] ?? 0)),
                    'default_message_notifications' => ((int) ($options['defaultMessageNotifications'] ?? 0)),
                    'explicit_content_filter'       => ((int) ($options['explicitContentFilter'] ?? 0)),
                    'roles'                         => [
                        [
                            'id'          => 0,
                            'name'        => '@everyone',
                            'permissions' => 0,
                            'color'       => 0,
                            'hoist'       => false,
                            'mentionable' => false,
                        ],
                    ],
                    'channels'                      => [],
                ];

                $rolemap = [
                    '@everyone' => 0,
                ];
                $roleint = 1;

                if (! empty($options['roles'])) {
                    foreach ($options['roles'] as $role) {
                        $role = [
                            'id'          => $roleint,
                            'name'        => ((string) $role['name']),
                            'permissions' => ($role['permissions'] ?? 0),
                            'color'       => (! empty($role['color']) ? DataHelpers::resolveColor(
                                $role['color']
                            ) : 0),
                            'hoist'       => ((bool) ($role['hoist'] ?? false)),
                            'mentionable' => ((bool) ($role['mentionable'] ?? false)),
                        ];

                        if ($role['name'] === '@everyone') {
                            $data['roles'][0] = $role;
                        } else {
                            $data['roles'][] = $role;
                            $rolemap[$role['name']] = $roleint++;
                        }
                    }
                }

                if (! empty($options['channels'])) {
                    foreach ($options['channels'] as $channel) {
                        $cdata = [
                            'name' => ((string) $channel['name']),
                            'type' => (ChannelStorage::CHANNEL_TYPES[($channel['type'] ?? 'text')] ?? 0),
                        ];

                        if (isset($channel['bitrate'])) {
                            $cdata['bitrate'] = (int) $channel['bitrate'];
                        }

                        if (isset($channel['userLimit'])) {
                            $cdata['user_limit'] = $channel['userLimit'];
                        }

                        if (isset($channel['permissionOverwrites'])) {
                            $overwrites = [];

                            foreach ($channel['permissionOverwrites'] as $overwrite) {
                                $id = ($overwrite['id'] instanceof User ? $overwrite['id']->id : ($rolemap[$overwrite['id']] ?? $overwrite['id']));

                                $overwrites[] = [
                                    'id'    => $id,
                                    'type'  => (isset($rolemap[$overwrite['id']]) ? 'role' : 'member'),
                                    'allow' => ($overwrite['allow'] ?? 0),
                                    'deny'  => ($overwrite['deny'] ?? 0),
                                ];
                            }

                            $cdata['permission_overwrites'] = $overwrites;
                        }

                        if (isset($channel['parent'])) {
                            $cdata['parent_id'] = ($channel['parent'] instanceof CategoryChannel ? $channel['parent']->id : $channel['parent']);
                        }

                        if (isset($channel['nsfw'])) {
                            $cdata['nsfw'] = $channel['nsfw'];
                        }

                        $data['channels'][] = $cdata;
                    }
                }

                if (! empty($options['icon'])) {
                    $pr = FileHelpers::resolveFileResolvable($options['icon'])->then(
                        function ($icon) use (&$data) {
                            $data['icon'] = $icon;
                        }
                    );
                } else {
                    $pr = resolve(null);
                }

                $pr->then(
                    function () use (&$data) {
                        return $this->api->endpoints->guild->createGuild($data)->then(
                            function ($gdata) {
                                return $this->guilds->factory($gdata);
                            }
                        );
                    }
                )->done($resolve, $reject);
            }
        );
    }

    /**
     * Obtains the OAuth Application of the bot from Discord.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\OAuthApplication
     */
    public function fetchApplication()
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                $this->api->endpoints->getCurrentApplication()->done(
                    function ($data) use ($resolve) {
                        $app = new OAuthApplication($this, $data);
                        $resolve($app);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Obtains an invite from Discord. Resolves with an instance of Invite.
     *
     * @param  string  $invite  The invite code or an invite URL.
     * @param  bool  $withCounts  Whether the invite should contain approximate counts.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Invite
     */
    public function fetchInvite(string $invite, bool $withCounts = false)
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($invite, $withCounts) {
                preg_match('/discord(?:app\.com\/invite|\.gg)\/([\w-]{2,255})/i', $invite, $matches);
                if (! empty($matches[1])) {
                    $invite = $matches[1];
                }

                $this->api->endpoints->invite->getInvite($invite, $withCounts)->done(
                    function ($data) use ($resolve) {
                        $invite = new Invite($this, $data);
                        $resolve($invite);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Fetches an User from the API. Resolves with an User.
     *
     * @param  string  $userid  The User ID to fetch.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\User
     */
    public function fetchUser(string $userid)
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($userid) {
                if ($this->users->has($userid)) {
                    return $resolve($this->users->get($userid));
                }

                $this->api->endpoints->user->getUser($userid)->then(
                    function ($user) use ($resolve) {
                        $user = $this->users->factory($user, true);
                        $resolve($user);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Obtains the available voice regions from Discord. Resolves with a Collection of Voice Region instances, mapped by their ID.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\VoiceRegion
     */
    public function fetchVoiceRegions()
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                $this->api->endpoints->voice->listVoiceRegions()->done(
                    function ($data) use ($resolve) {
                        $collect = new Collection();

                        foreach ($data as $region) {
                            $voice = new VoiceRegion($this, $region);
                            $collect->set($voice->id, $voice);
                        }

                        $resolve($collect);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Fetches a webhook from Discord. Resolves with an instance of Webhook.
     *
     * @param  string  $id
     * @param  string|null  $token
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Webhook
     */
    public function fetchWebhook(string $id, ?string $token = null)
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($id, $token) {
                $method = (! empty($token) ? 'getWebhookToken' : 'getWebhook');

                $this->api->endpoints->webhook->$method($id, $token)->done(
                    function ($data) use ($resolve) {
                        $hook = new Webhook($this, $data);
                        $resolve($hook);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Generates a link that can be used to invite the bot to a guild. Resolves with a string.
     *
     * @param  string|int  ...$permissions
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function generateOAuthInvite(...$permissions)
    {
        $perm = new Permissions();
        if (! empty($permissions)) {
            $perm->add(...$permissions);
        }

        return $this->fetchApplication()->then(
            function ($app) use ($perm) {
                return 'https://discordapp.com/oauth2/authorize?client_id='.$app->id.'&permissions='.$perm->bitfield.'&scope=bot';
            }
        );
    }

    /**
     * Adds a "client-dependant" timer. The timer gets automatically cancelled on destroy. The callback can only accept one argument, the client.
     *
     * @param  float|int  $timeout
     * @param  callable  $callback
     *
     * @return Timer
     */
    public function addTimer($timeout, callable $callback)
    {
        $timer = $this->loop->addTimer(
            $timeout,
            function () use ($callback, &$timer) {
                $callback($this);
                $this->cancelTimer($timer);
            }
        );

        $this->timers[] = $timer;

        return $timer;
    }

    /**
     * Adds a "client-dependant" periodic timer. The timer gets automatically cancelled on destroy. The callback can only accept one argument, the client.
     *
     * @param  float|int  $interval
     * @param  callable  $callback
     *
     * @return Timer
     */
    public function addPeriodicTimer($interval, callable $callback)
    {
        $timer = $this->loop->addPeriodicTimer(
            $interval,
            function () use ($callback) {
                $callback($this);
            }
        );

        $this->timers[] = $timer;

        return $timer;
    }

    /**
     * Cancels a timer.
     *
     * @param  TimerInterface  $timer
     *
     * @return bool
     */
    public function cancelTimer($timer)
    {
        $this->loop->cancelTimer($timer);

        $key = array_search($timer, $this->timers, true);
        if ($key !== false) {
            unset($this->timers[$key]);
        }

        return true;
    }

    /**
     * Cancels all timers.
     *
     * @return void
     */
    public function cancelTimers()
    {
        foreach ($this->timers as $key => $timer) {
            $this->loop->cancelTimer($timer);
            unset($this->timers[$key]);
        }
    }

    /**
     * Make an instance of {ClientUser} and store it.
     *
     * @return void
     * @internal
     */
    public function setClientUser(array $user)
    {
        $this->user = new ClientUser($this, $user);
        $this->users->set($this->user->id, $this->user);
    }

    /**
     * Returns an array of classes with are registered as Util.
     *
     * @return string[]
     */
    public function getUtils()
    {
        return $this->utils;
    }

    /**
     * Registers an Util, if it has a setLoop method. All methods used need to be static.
     * It will set the event loop through `setLoop` and on destroy will call `destroy`.
     *
     * @param  string  $name
     *
     * @return void
     */
    public function registerUtil(string $name)
    {
        if (method_exists($name, 'setLoop')) {
            $name::setLoop($this->loop);
            $this->utils[] = $name;
        }
    }

    /**
     * Destroys an Util and calls `destroy` (requires that it is registered as such).
     *
     * @param  string  $name
     *
     * @return void
     */
    public function destroyUtil(string $name)
    {
        $pos = array_search($name, $this->utils, true);
        if ($pos !== false) {
            if (method_exists($name, 'destroy')) {
                $name::destroy();
            }

            unset($this->utils[$pos]);
        }
    }

    /**
     * Registers Utils which have a setLoop method.
     *
     * @return void
     * @internal
     */
    public function registerUtils()
    {
        $utils = glob(__DIR__.'/Utils/*.php');
        foreach ($utils as $util) {
            $parts = explode('/', str_replace('\\', '/', $util));
            $name = substr(array_pop($parts), 0, -4);
            $fqn = '\\CharlotteDunois\\Yasmin\\Utils\\'.$name;

            if (method_exists($fqn, 'setLoop')) {
                $fqn::setLoop($this->loop);
                $this->utils[] = $fqn;
            }
        }
    }

    /**
     * Destroys or stops all timers from Utils (requires that they are registered as such).
     *
     * @return void
     * @internal
     */
    public function destroyUtils()
    {
        foreach ($this->utils as $util) {
            if (method_exists($util, 'destroy')) {
                $util::destroy();
            }
        }
    }

    /**
     * Emits an error for an unhandled promise rejection.
     *
     * @return void
     * @internal
     */
    public function handlePromiseRejection($error)
    {
        $this->emit('error', $error);
    }

    /**
     * Validates the passed client options.
     *
     * @param  array  $options
     *
     * @return void
     * @throws InvalidArgumentException
     */
    protected function validateClientOptions(array $options)
    {
        Validator::make(
            $options,
            [
                'disableClones'                        => 'boolean|array:string',
                'disableEveryone'                      => 'boolean',
                'fetchAllMembers'                      => 'boolean',
                'messageCache'                         => 'boolean',
                'messageCacheLifetime'                 => 'integer|min:0',
                'messageSweepInterval'                 => 'integer|min:0',
                'presenceCache'                        => 'boolean',
                'minShardID'                           => 'integer|min:0',
                'maxShardID'                           => 'integer|min:0',
                'shardCount'                           => 'integer|min:1',
                'userSweepInterval'                    => 'integer|min:0',
                'http.ratelimitbucket.name'            => 'class:'.RatelimitBucketInterface::class.'=string',
                'http.ratelimitbucket.athena'          => 'class:CharlotteDunois\\Athena\\AthenaCache=object',
                'http.requestErrorDelay'               => 'integer|min:15',
                'http.requestMaxRetries'               => 'integer|min:0',
                'http.restTimeOffset'                  => 'integer|float',
                'ws.compression'                       => 'string',
                'ws.disabledEvents'                    => 'array:string',
                'ws.encoding'                          => 'string',
                'ws.largeThreshold'                    => 'integer|min:50|max:250',
                'ws.presence'                          => 'array',
                'ws.presenceUpdate.ignoreUnknownUsers' => 'boolean',
                'internal.api.instance'                => 'class:'.APIManager::class,
                'internal.storages.channels'           => 'class:'.ChannelStorageInterface::class.'=string',
                'internal.storages.emojis'             => 'class:'.EmojiStorageInterface::class.'=string',
                'internal.storages.guilds'             => 'class:'.GuildStorageInterface::class.'=string',
                'internal.storages.messages'           => 'class:'.MessageStorageInterface::class.'=string',
                'internal.storages.members'            => 'class:'.GuildMemberStorageInterface::class.'=string',
                'internal.storages.presences'          => 'class:'.PresenceStorageInterface::class.'=string',
                'internal.storages.roles'              => 'class:'.RoleStorageInterface::class.'=string',
                'internal.storages.users'              => 'class:'.UserStorageInterface::class.'=string',
                'internal.ws.instance'                 => 'class:'.WSManager::class,
            ]
        )->throw(InvalidArgumentException::class);
    }

    /**
     * Validates the passed client options storages.
     *
     * @return void
     * @throws RuntimeException
     */
    protected function checkOptionsStorages()
    {
        $storages = [
            'channels'  => ChannelStorage::class,
            'emojis'    => EmojiStorage::class,
            'guilds'    => GuildStorage::class,
            'messages'  => MessageStorage::class,
            'members'   => GuildMemberStorage::class,
            'presences' => PresenceStorage::class,
            'roles'     => RoleStorage::class,
            'users'     => UserStorage::class,
        ];

        foreach ($storages as $name => $base) {
            if (empty($this->options['internal.storages.'.$name])) {
                $this->options['internal.storages.'.$name] = $base;
            }
        }
    }

    /**
     * Puts events into a queue, if the client is not ready yet.
     *
     * @return void
     * @internal
     */
    public function queuedEmit(string $event, ...$args)
    {
        if ($this->readyTimestamp === null) {
            $this->eventsQueue[] = [$event, $args];

            return;
        }

        return $this->emit($event, ...$args);
    }
}
