<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use BadMethodCallException;
use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\FileHelpers;
use CharlotteDunois\Yasmin\Utils\MessageHelpers;
use InvalidArgumentException;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use RuntimeException;

use function React\Promise\resolve;

/**
 * Represents a webhook.
 *
 * @property string $id         The webhook ID.
 * @property string|null $name       The webhook default name, or null.
 * @property string|null $avatar     The webhook default avatar, or null.
 * @property string|null $channelID  The channel ID the webhook belongs to.
 * @property string|null $guildID    The guild ID the webhook belongs to, or null.
 * @property User|null $owner      The owner of the webhook, or null.
 * @property string|null $token      The webhook token, or null.
 */
class Webhook extends ClientBase
{
    /**
     * The webhook ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The webhook default name, or null.
     *
     * @var string|null
     */
    protected $name;

    /**
     * The webhook default avatar, or null.
     *
     * @var string|null
     */
    protected $avatar;

    /**
     * The channel ID the webhook belongs to.
     *
     * @var string|null
     */
    protected $channelID;

    /**
     * The guild ID the webhook belongs to, or null.
     *
     * @var string|null
     */
    protected $guildID;

    /**
     * The owner of the webhook, or null.
     *
     * @var User|null
     */
    protected $owner;

    /**
     * The webhook token, or null.
     *
     * @var string|null
     */
    protected $token;

    /**
     * @param  Client  $client
     * @param  array  $webhook
     *
     * @internal
     */
    function __construct(Client $client, array $webhook)
    {
        parent::__construct($client);

        $this->id = (string) $webhook['id'];
        $this->_patch($webhook);
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @throws RuntimeException
     * @internal
     */
    function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return parent::__get($name);
    }

    /**
     * Edits the webhook. Resolves with $this.
     *
     * Options are as following (at least one is required):
     *
     * ```
     * array(
     *    'name' => string,
     *    'avatar' => string, (data, filepath or URL)
     *    'channel' => \CharlotteDunois\Yasmin\Models\TextChannel|string
     * )
     * ```
     *
     * @param  array  $options
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    function edit(array $options, string $reason = '')
    {
        $data = [];

        if (! empty($options['name'])) {
            $data['name'] = $options['name'];
        }

        if (! empty($options['channel'])) {
            $data['channel'] = $this->client->channels->resolve($options['channel'])->getId();
        }

        return (new Promise(
            function (callable $resolve, callable $reject) use ($data, $options, $reason) {
                FileHelpers::resolveFileResolvable(($options['avatar'] ?? ''))->done(
                    function ($avatar = null) use ($data, $reason, $resolve, $reject) {
                        if (! empty($avatar)) {
                            $data['avatar'] = DataHelpers::makeBase64URI($avatar);
                        }

                        $method = 'modifyWebhook';
                        $args = [$this->id, $data, $reason];

                        if (! empty($this->token)) {
                            $method = 'modifyWebhookToken';
                            $args = [$this->id, $this->token, $data, $reason];
                        }

                        $this->client->apimanager()->endpoints->webhook->$method(...$args)->done(
                            function ($data) use ($resolve) {
                                $this->_patch($data);
                                $resolve($this);
                            },
                            $reject
                        );
                    },
                    $reject
                );
            }
        ));
    }

    /**
     * Deletes the webhook.
     *
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     */
    function delete(string $reason = '')
    {
        return (new Promise(
            function (callable $resolve, callable $reject) use ($reason) {
                $method = 'deleteWebhook';
                $args = [$this->id, $reason];

                if (! empty($this->token)) {
                    $method = 'deleteWebhookToken';
                    $args = [$this->id, $this->token, $reason];
                }

                $this->client->apimanager()->endpoints->webhook->$method(...$args)->done(
                    function () use ($resolve) {
                        $resolve();
                    },
                    $reject
                );
            }
        ));
    }

    /**
     * Executes the webhooks and sends a message to the channel. Resolves with an instance of Message, or a Collection of Message instances, mapped by their ID. Or when using the WebhookClient, it will resolve with a raw Message object (array) or an array of raw Message objects (array).
     *
     * Options are as following (all are optional):
     *
     * ```
     * array(
     *    'embeds' => \CharlotteDunois\Yasmin\Models\MessageEmbed[]|array[], (an array of (embed) array/object or MessageEmbed instances)
     *    'files' => array, (an array of `[ 'name' => string, 'data' => string || 'path' => string ]` or just plain file contents, file paths or URLs)
     *    'nonce' => string, (a snowflake used for optimistic sending)
     *    'disableEveryone' => bool, (whether @everyone and @here should be replaced with plaintext, defaults to client option disableEveryone (which itself defaults to false))
     *    'tts' => bool,
     *    'split' => bool|array, (*)
     *    'username' => string,
     *    'avatar' => string, (an URL)
     * )
     *
     *   * array(
     *   *   'before' => string, (The string to insert before the split)
     *   *   'after' => string, (The string to insert after the split)
     *   *   'char' => string, (The string to split on)
     *   *   'maxLength' => int, (The max. length of each message)
     *   * )
     * ```
     *
     * @param  string  $content  The webhook message content.
     * @param  array  $options  Any webhook message options.
     *
     * @return ExtendedPromiseInterface
     * @throws BadMethodCallException
     * @see \CharlotteDunois\Yasmin\Models\Message
     * @see https://discordapp.com/developers/docs/resources/channel#message-object
     */
    function send(string $content, array $options = [])
    {
        if (empty($this->token)) {
            throw new BadMethodCallException('Can not use webhook without token to send message');
        }

        return (new Promise(
            function (callable $resolve, callable $reject) use ($content, $options) {
                MessageHelpers::resolveMessageOptionsFiles($options)->done(
                    function ($files) use ($content, $options, $resolve, $reject) {
                        $msg = [
                            'content' => $content,
                        ];

                        if (! empty($options['embeds'])) {
                            $msg['embeds'] = $options['embeds'];
                        }

                        if (! empty($options['nonce'])) {
                            $msg['nonce'] = $options['nonce'];
                        }

                        $disableEveryone = (isset($options['disableEveryone']) ? ((bool) $options['disableEveryone']) : $this->client->getOption(
                            'disableEveryone',
                            false
                        ));
                        if ($disableEveryone) {
                            $msg['content'] = str_replace(
                                ['@everyone', '@here'],
                                ["@\u{200b}everyone", "@\u{200b}here"],
                                $msg['content']
                            );
                        }

                        if (! empty($options['tts'])) {
                            $msg['tts'] = true;
                        }

                        if (! empty($options['split'])) {
                            $options['split'] = $split = array_merge(
                                Message::DEFAULT_SPLIT_OPTIONS,
                                (is_array($options['split']) ? $options['split'] : [])
                            );
                            $messages = MessageHelpers::splitMessage($msg['content'], $options['split']);

                            if (count($messages) > 0) {
                                $collection = new Collection();

                                $chunkedSend = function ($msg, $files = null) use ($collection, $reject) {
                                    return $this->executeWebhook($msg, ($files ?? []))->then(
                                        function ($message) use ($collection) {
                                            $collection->set($message->id, $message);
                                        },
                                        $reject
                                    );
                                };

                                $i = 0;

                                $promise = resolve();
                                foreach ($messages as $key => $message) {
                                    $promise = $promise->then(
                                        function () use ($chunkedSend, &$files, $key, $i, $message, &$msg, $split) {
                                            $fs = null;
                                            if ($files) {
                                                $fs = $files;
                                                $files = null;
                                            }

                                            $message = [
                                                'content' => ($key > 0 ? $split['before'] : '').$message.($key < $i ? $split['after'] : ''),
                                            ];

                                            if (! empty($msg['embeds'])) {
                                                $message['embeds'] = $msg['embeds'];
                                                $msg['embeds'] = null;
                                            }

                                            return $chunkedSend($message, $fs);
                                        },
                                        $reject
                                    );
                                }

                                return $promise->done(
                                    function () use (&$collection, $resolve) {
                                        $resolve($collection);
                                    },
                                    $reject
                                );
                            }
                        }

                        if (! empty($options['username'])) {
                            $msg['username'] = $options['username'];
                        }

                        if (! empty($options['avatar'])) {
                            $msg['avatar_url'] = $options['avatar'];
                        }

                        $this->executeWebhook($msg, ($files ?? []))->done(
                            function ($data) use ($resolve) {
                                $resolve($data);
                            },
                            $reject
                        );
                    }
                );
            }
        ));
    }

    /**
     * Executes the webhook effectively. Resolves with an instance of Message.
     *
     * @param  array  $opts
     * @param  array  $files
     *
     * @return ExtendedPromiseInterface
     * @internal
     */
    protected function executeWebhook(array $opts, array $files)
    {
        return $this->client->apimanager()->endpoints->webhook->executeWebhook(
            $this->id,
            $this->token,
            $opts,
            $files,
            ['wait' => true]
        )->then(
            function ($data) {
                $channel = $this->client->channels->get($this->channelID);
                if ($channel instanceof TextChannelInterface) {
                    return $channel->_createMessage($data);
                }

                return $data;
            }
        );
    }

    /**
     * @param  array  $webhook
     *
     * @return void
     * @internal
     */
    function _patch(array $webhook)
    {
        $this->name = $webhook['name'] ?? null;
        $this->avatar = $webhook['avatar'] ?? null;
        $this->channelID = DataHelpers::typecastVariable(($webhook['channel_id'] ?? null), 'string');
        $this->guildID = DataHelpers::typecastVariable(($webhook['guild_id'] ?? null), 'string');
        $this->owner = (! empty($webhook['user']) ? $this->client->users->patch($webhook['user']) : null);
        $this->token = $webhook['token'] ?? null;
    }
}
