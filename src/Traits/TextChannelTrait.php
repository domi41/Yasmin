<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Traits;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\User;
use CharlotteDunois\Yasmin\Utils\Collector;
use CharlotteDunois\Yasmin\Utils\MessageHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;
use InvalidArgumentException;
use OutOfBoundsException;
use RangeException;
use React\EventLoop\TimerInterface;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use function React\Promise\resolve;

/**
 * The text based channel trait.
 */
trait TextChannelTrait
{
    /**
     * Collection of all typing users (contains arrays).
     *
     * @var Collection
     */
    protected $typings;

    /**
     * Triggered typings in this channel.
     *
     * @var array
     * @internal
     */
    protected $typingTriggered = [
        'count' => 0,
        'timer' => null,
    ];

    /**
     * The last message's ID, or null.
     *
     * @var string|null
     */
    protected $lastMessageID;

    /**
     * @return string
     * @internal
     */
    public function serialize()
    {
        $triggers = $this->typingTriggered;
        $typings = clone $this->typings;

        foreach ($this->typings as $id => $type) {
            $type['timer'] = null;
            $this->typings->set($id, $type);
        }

        $this->typingTriggered['timer'] = null;

        $str = parent::serialize();

        $this->typingTriggered = $triggers;
        $this->typings = $typings;

        return $str;
    }

    /**
     * Deletes multiple messages at once. Resolves with $this.
     *
     * @param  Collection|array|int  $messages  A collection or array of Message instances, or the number of messages to delete (2-100).
     * @param  string  $reason
     * @param  bool  $filterOldMessages  Automatically filters out too old messages (14 days).
     *
     * @return ExtendedPromiseInterface
     */
    public function bulkDelete($messages, string $reason = '', bool $filterOldMessages = false)
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($messages, $reason, $filterOldMessages) {
                if (is_numeric($messages)) {
                    $messages = $this->fetchMessages(['limit' => (int) $messages]);
                } else {
                    $messages = resolve($messages);
                }

                $messages->done(
                    function ($messages) use ($reason, $filterOldMessages, $resolve, $reject) {
                        if ($messages instanceof Collection) {
                            $messages = $messages->all();
                        }

                        if ($filterOldMessages) {
                            $messages = array_filter(
                                $messages,
                                function ($message) {
                                    if ($message instanceof Message) {
                                        $timestamp = $message->createdTimestamp;
                                    } else {
                                        $timestamp = (int) Snowflake::deconstruct($message)->timestamp;
                                    }

                                    return (time() - $timestamp) < 1209600;
                                }
                            );
                        }

                        $messages = array_map(
                            function ($message) {
                                return $message->id;
                            },
                            $messages
                        );

                        if (count($messages) < 2 || count($messages) > 100) {
                            return $reject(
                                new InvalidArgumentException(
                                    'Unable to bulk delete less than 2 or more than 100 messages'
                                )
                            );
                        }

                        $this->client->apimanager()->endpoints->channel->bulkDeleteMessages(
                            $this->id,
                            $messages,
                            $reason
                        )->done(
                            function () use ($resolve) {
                                $resolve($this);
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
     * Collects messages during a specific duration (and max. amount). Resolves with a Collection of Message instances, mapped by their IDs.
     *
     * Options are as following (all are optional):
     *
     * ```
     * array(
     *   'max' => int, (max. messages to collect)
     *   'time' => int, (duration, in seconds, default 30)
     *   'errors' => array, (optional, which failed "conditions" (max not reached in time ("time")) lead to a rejected promise, defaults to [])
     * )
     * ```
     *
     * @param  callable  $filter  The filter to only collect desired messages. Signature: `function (Message $message): bool`
     * @param  array  $options  The collector options.
     *
     * @return ExtendedPromiseInterface  This promise is cancellable.
     * @throws RangeException          The exception the promise gets rejected with, if collecting times out.
     * @throws OutOfBoundsException    The exception the promise gets rejected with, if the promise gets cancelled.
     * @see \CharlotteDunois\Yasmin\Models\Message
     * @see \CharlotteDunois\Yasmin\Utils\Collector
     */
    public function collectMessages(callable $filter, array $options = [])
    {
        $mhandler = function (Message $message) {
            return [$message->id, $message];
        };
        $mfilter = function (Message $message) use ($filter) {
            return $message->channel->getId() === $this->id && $filter($message);
        };

        $collector = new Collector($this->client, 'message', $mhandler, $mfilter, $options);

        return $collector->collect();
    }

    /**
     * Fetches a specific message using the ID. Resolves with an instance of Message.
     *
     * @param  string  $id
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Message
     */
    public function fetchMessage(string $id)
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($id) {
                $this->client->apimanager()->endpoints->channel->getChannelMessage($this->id, $id)->done(
                    function ($data) use ($resolve) {
                        $message = $this->_createMessage($data);
                        $resolve($message);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Fetches messages of this channel. Resolves with a Collection of Message instances, mapped by their ID.
     *
     * Options are as following:
     *
     * ```
     * array(
     *   'after' => string, (message ID)
     *   'around' => string, (message ID)
     *   'before' => string, (message ID)
     *   'limit' => int, (1-100, defaults to 50)
     * )
     * ```
     *
     * @param  array  $options
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Message
     */
    public function fetchMessages(array $options = [])
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($options) {
                $this->client->apimanager()->endpoints->channel->getChannelMessages($this->id, $options)->done(
                    function ($data) use ($resolve) {
                        $collect = new Collection();

                        foreach ($data as $m) {
                            $message = $this->_createMessage($m);
                            $collect->set($message->id, $message);
                        }

                        $resolve($collect);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Gets the last message in this channel if cached, or null.
     *
     * @return Message|null
     */
    public function getLastMessage()
    {
        if (! empty($this->lastMessageID) && $this->messages->has($this->lastMessageID)) {
            return $this->messages->get($this->lastMessageID);
        }

        return null;
    }

    /**
     * Sends a message to a channel. Resolves with an instance of Message, or a Collection of Message instances, mapped by their ID.
     *
     * Options are as following (all are optional):
     *
     * ```
     * array(
     *    'embed' => array|\CharlotteDunois\Yasmin\Models\MessageEmbed, (an (embed) array/object or an instance of MessageEmbed)
     *    'files' => array, (an array of `[ 'name' => string, 'data' => string || 'path' => string ]` or just plain file contents, file paths or URLs)
     *    'nonce' => string, (a snowflake used for optimistic sending)
     *    'disableEveryone' => bool, (whether @everyone and @here should be replaced with plaintext, defaults to client option disableEveryone)
     *    'tts' => bool,
     *    'split' => bool|array, (*)
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
     * @param  string  $content  The message content.
     * @param  array  $options  Any message options.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Message
     */
    public function send(string $content, array $options = [])
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($content, $options) {
                MessageHelpers::resolveMessageOptionsFiles($options)->done(
                    function ($files) use ($content, $options, $resolve, $reject) {
                        $msg = [
                            'content' => $content,
                        ];

                        if (! empty($options['embed'])) {
                            $msg['embed'] = $options['embed'];
                        }

                        if (! empty($options['nonce'])) {
                            $msg['nonce'] = $options['nonce'];
                        }

                        $disableEveryone = (isset($options['disableEveryone']) ? ((bool) $options['disableEveryone']) : $this->client->getOption(
                            'disableEveryone',
                            true
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

                        if (isset($options['split'])) {
                            $options['split'] = $split = array_merge(
                                Message::DEFAULT_SPLIT_OPTIONS,
                                (is_array($options['split']) ? $options['split'] : [])
                            );
                            $messages = MessageHelpers::splitMessage(
                                $msg['content'],
                                $options['split']
                            );

                            if (count($messages) > 1) {
                                $collection = new Collection();
                                $i = count($messages);

                                $chunkedSend = function ($msg, $files = null) use ($collection, $reject) {
                                    return $this->client->apimanager()->endpoints->channel->createMessage(
                                        $this->id,
                                        $msg,
                                        ($files ?? [])
                                    )->then(
                                        function ($response) use ($collection) {
                                            $msg = $this->_createMessage($response);
                                            $collection->set($msg->id, $msg);
                                        },
                                        $reject
                                    );
                                };

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

                                            if (! empty($msg['embed'])) {
                                                $message['embed'] = $msg['embed'];
                                                $msg['embed'] = null;
                                            }

                                            return $chunkedSend($message, $fs);
                                        },
                                        $reject
                                    );
                                }

                                return $promise->done(
                                    function () use ($collection, $resolve) {
                                        $resolve($collection);
                                    },
                                    $reject
                                );
                            }
                        }

                        $this->client->apimanager()->endpoints->channel->createMessage(
                            $this->id,
                            $msg,
                            ($files ?? [])
                        )->done(
                            function ($response) use ($resolve) {
                                $resolve($this->_createMessage($response));
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
     * Starts sending the typing indicator in this channel. Counts up a triggered typing counter.
     *
     * @return void
     */
    public function startTyping()
    {
        if ($this->typingTriggered['count'] === 0) {
            $fn = function () {
                $this->client->apimanager()->endpoints->channel->triggerChannelTyping($this->id)->done(
                    function () {
                        $this->_updateTyping($this->client->user, time());
                    },
                    function () {
                        $this->_updateTyping($this->client->user);
                        $this->typingTriggered['count'] = 0;

                        if ($this->typingTriggered['timer']) {
                            $this->client->cancelTimer($this->typingTriggered['timer']);
                            $this->typingTriggered['timer'] = null;
                        }
                    }
                );
            };

            $this->typingTriggered['timer'] = $this->client->addPeriodicTimer(7, $fn);
            $fn();
        }

        $this->typingTriggered['count']++;
    }

    /**
     * Stops sending the typing indicator in this channel. Counts down a triggered typing counter.
     *
     * @param  bool  $force  Reset typing counter and stop sending the indicator.
     *
     * @return void
     */
    public function stopTyping(bool $force = false)
    {
        if ($this->typingTriggered['count'] === 0) {
            return;
        }

        $this->typingTriggered['count']--;
        if ($force) {
            $this->typingTriggered['count'] = 0;
        }

        if ($this->typingTriggered['count'] === 0) {
            if ($this->typingTriggered['timer']) {
                $this->client->cancelTimer($this->typingTriggered['timer']);
                $this->typingTriggered['timer'] = null;
            }
        }
    }

    /**
     * Returns the amount of user typing in this channel.
     *
     * @return int
     */
    public function typingCount()
    {
        return $this->typings->count();
    }

    /**
     * Determines whether the given user is typing in this channel or not.
     *
     * @param  User  $user
     *
     * @return bool
     */
    public function isTyping(User $user)
    {
        return $this->typings->has($user->id);
    }

    /**
     * Determines whether how long the given user has been typing in this channel. Returns -1 if the user is not typing.
     *
     * @param  User  $user
     *
     * @return int
     */
    public function isTypingSince(User $user)
    {
        if (! $this->isTyping($user)) {
            return -1;
        }

        return time() - $this->typings->get($user->id)['timestamp'];
    }

    /**
     * @param  array  $message
     *
     * @return Message
     * @internal
     */
    public function _createMessage(array $message)
    {
        if ($this->messages->has($message['id'])) {
            return $this->messages->get($message['id']);
        }

        $msg = new Message($this->client, $this, $message);
        $this->messages->set($msg->id, $msg);

        return $msg;
    }

    /**
     * @param  User  $user
     * @param  int|null  $timestamp
     *
     * @return bool
     * @internal
     */
    public function _updateTyping(User $user, ?int $timestamp = null)
    {
        if ($timestamp === null) {
            $this->typings->delete($user->id);

            return false;
        }

        $typing = $this->typings->get($user->id);
        if ($typing && ($typing['timer'] instanceof \React\EventLoop\Timer\TimerInterface || $typing['timer'] instanceof TimerInterface)) {
            $this->client->cancelTimer($typing['timer']);
        }

        $timer = $this->client->addTimer(
            9,
            function () use ($user) {
                $this->typings->delete($user->id);
                $this->client->emit('typingStop', $this, $user);
            }
        );

        $this->typings->set(
            $user->id,
            [
                'timestamp' => (int) $timestamp,
                'timer'     => $timer,
            ]
        );

        return $typing === null;
    }
}
