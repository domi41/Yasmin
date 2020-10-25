<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\GuildTextChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\MessageStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\StorageInterface;
use CharlotteDunois\Yasmin\Traits\GuildChannelTrait;
use CharlotteDunois\Yasmin\Traits\TextChannelTrait;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\FileHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;
use DateTime;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use function React\Promise\resolve;
use RuntimeException;

/**
 * Represents a guild's text channel.
 *
 * @property string $id                     The channel ID.
 * @property Guild $guild                  The associated guild.
 * @property int $createdTimestamp       The timestamp of when this channel was created.
 * @property string $name                   The channel name.
 * @property string $topic                  The channel topic.
 * @property bool $nsfw                   Whether the channel is marked as NSFW or not.
 * @property string|null $parentID               The ID of the parent channel, or null.
 * @property int $position               The channel position.
 * @property int $slowmode               Ratelimit to send one message for each non-bot user, without `MANAGE_CHANNEL` and `MANAGE_MESSAGES` permissions, in seconds (0-120).
 * @property Collection $permissionOverwrites   A collection of PermissionOverwrite instances, mapped by their ID.
 * @property string|null $lastMessageID          The last message ID, or null.
 * @property MessageStorageInterface $messages               The storage with all cached messages.
 *
 * @property DateTime $createdAt              The DateTime instance of createdTimestamp.
 * @property CategoryChannel|null $parent                 The channel's parent, or null.
 * @method string getId()
 * @method int getCreatedTimestamp()
 * @method string getName()
 * @method Guild getGuild()
 * @method int getPosition()
 * @method Collection getPermissionOverwrites()
 * @method null getParent()
 * @method MessageStorageInterface getMessages()
 * @method string getLastMessageID()
 */
class TextChannel extends ClientBase implements GuildTextChannelInterface
{
    use GuildChannelTrait, TextChannelTrait;

    /**
     * The associated guild.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * The storage with all cached messages.
     *
     * @var StorageInterface
     */
    protected $messages;

    /**
     * The channel ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The ID of the parent channel, or null.
     *
     * @var string|null
     */
    protected $parentID;

    /**
     * The channel name.
     *
     * @var string
     */
    protected $name;

    /**
     * The channel topic.
     *
     * @var string
     */
    protected $topic;

    /**
     * Whether the channel is marked as NSFW or not.
     *
     * @var bool
     */
    protected $nsfw;

    /**
     * The channel position.
     *
     * @var int
     */
    protected $position;

    /**
     * Ratelimit to send one message for each non-bot user, without `MANAGE_CHANNEL` and `MANAGE_MESSAGES` permissions, in seconds (0-120).
     *
     * @var int
     */
    protected $slowmode;

    /**
     * A collection of PermissionOverwrite instances, mapped by their ID.
     *
     * @var Collection
     */
    protected $permissionOverwrites;

    /**
     * The timestamp of when this channel was created.
     *
     * @var int
     */
    protected $createdTimestamp;

    /**
     * @param  Client  $client
     * @param  Guild  $guild
     * @param  array  $channel
     *
     * @internal
     */
    public function __construct(
        Client $client,
        Guild $guild,
        array $channel
    ) {
        parent::__construct($client);
        $this->guild = $guild;

        $storage = $this->client->getOption('internal.storages.messages');
        $this->messages = new $storage($this->client, $this);
        $this->typings = new Collection();

        $this->id = (string) $channel['id'];
        $this->lastMessageID = DataHelpers::typecastVariable(
            ($channel['last_message_id'] ?? null),
            'string'
        );

        $this->createdTimestamp = (int) Snowflake::deconstruct($this->id)->timestamp;
        $this->permissionOverwrites = new Collection();

        $this->_patch($channel);
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @throws RuntimeException
     * @internal
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        switch ($name) {
            case 'createdAt':
                return DataHelpers::makeDateTime($this->createdTimestamp);
                break;
            case 'parent':
                return $this->guild->channels->get($this->parentID);
                break;
        }

        return parent::__get($name);
    }

    /**
     * Create a webhook for the channel. Resolves with the new Webhook instance.
     *
     * @param  string  $name
     * @param  string|null  $avatar  An URL or file path, or data.
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Webhook
     */
    public function createWebhook(string $name, ?string $avatar = null, string $reason = '')
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($name, $avatar, $reason) {
                if (! empty($avatar)) {
                    $file = FileHelpers::resolveFileResolvable($avatar)->then(
                        function ($avatar) {
                            return DataHelpers::makeBase64URI($avatar);
                        }
                    );
                } else {
                    $file = resolve(null);
                }

                $file->done(
                    function ($avatar = null) use ($name, $reason, $resolve, $reject) {
                        $this->client->apimanager()->endpoints->webhook->createWebhook(
                            $this->id,
                            $name,
                            $avatar,
                            $reason
                        )->done(
                            function ($data) use ($resolve) {
                                $hook = new Webhook($this->client, $data);
                                $resolve($hook);
                            },
                            $reject
                        );
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Fetches the channel's webhooks. Resolves with a Collection of Webhook instances, mapped by their ID.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Webhook
     */
    public function fetchWebhooks()
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                $this->client->apimanager()->endpoints->webhook->getChannelWebhooks($this->id)->done(
                    function ($data) use ($resolve) {
                        $collect = new Collection();

                        foreach ($data as $web) {
                            $hook = new Webhook($this->client, $web);
                            $collect->set($hook->id, $hook);
                        }

                        $resolve($collect);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Sets the slowmode in seconds for this channel.
     *
     * @param  int  $slowmode  0-21600
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     */
    public function setSlowmode(int $slowmode, string $reason = '')
    {
        return $this->edit(['slowmode' => $slowmode], $reason);
    }

    /**
     * Automatically converts to a mention.
     *
     * @return string
     */
    public function __toString()
    {
        return '<#'.$this->id.'>';
    }

    /**
     * @return void
     * @internal
     */
    public function _patch(array $channel)
    {
        $this->name = (string) ($channel['name'] ?? $this->name ?? '');
        $this->topic = (string) ($channel['topic'] ?? $this->topic ?? '');
        $this->nsfw = (bool) ($channel['nsfw'] ?? $this->nsfw ?? false);
        $this->parentID = DataHelpers::typecastVariable(
            ($channel['parent_id'] ?? $this->parentID ?? null),
            'string'
        );
        $this->position = (int) ($channel['position'] ?? $this->position ?? 0);
        $this->slowmode = (int) ($channel['rate_limit_per_user'] ?? $this->slowmode ?? 0);

        if (isset($channel['permission_overwrites'])) {
            $this->permissionOverwrites->clear();

            foreach ($channel['permission_overwrites'] as $permission) {
                $overwrite = new PermissionOverwrite($this->client, $this, $permission);
                $this->permissionOverwrites->set($overwrite->id, $overwrite);
            }
        }
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method string getId()
        // TODO: Implement @method int getCreatedTimestamp()
        // TODO: Implement @method string getName()
        // TODO: Implement @method \CharlotteDunois\Yasmin\Models\Guild getGuild()
        // TODO: Implement @method int getPosition()
        // TODO: Implement @method \CharlotteDunois\Collect\Collection getPermissionOverwrites()
        // TODO: Implement @method null getParent()
        // TODO: Implement @method \CharlotteDunois\Yasmin\Interfaces\MessageStorageInterface getMessages()
        // TODO: Implement @method string getLastMessageID()
    }
}
