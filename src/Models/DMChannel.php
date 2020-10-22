<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\DMChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\MessageStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\StorageInterface;
use CharlotteDunois\Yasmin\Traits\TextChannelTrait;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;
use DateTime;
use InvalidArgumentException;
use RuntimeException;

/**
 * Represents a classic DM channel.
 *
 * @property string $id                 The channel ID.
 * @property int $createdTimestamp   The timestamp of when this channel was created.
 * @property string|null $ownerID            The owner ID of this channel, or null.
 * @property Collection $recipients         The recipients of this channel, mapped by user ID.
 * @property string|null $lastMessageID      The last message ID, or null.
 * @property StorageInterface $messages           The storage with all cached messages.
 *
 * @property DateTime $createdAt          The DateTime instance of createdTimestamp.
 * @property User|null $owner              The owner of this channel, or null.
 * @method string getId()
 * @method int getCreatedTimestamp()
 * @method MessageStorageInterface getMessages()
 * @method string getLastMessageID()
 */
class DMChannel extends ClientBase implements DMChannelInterface
{
    use TextChannelTrait;

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
     * The owner ID of this channel, or null.
     *
     * @var string|null
     */
    protected $ownerID;

    /**
     * The recipients of this channel, mapped by user ID.
     *
     * @var Collection
     */
    protected $recipients;

    /**
     * The timestamp of when this channel was created.
     *
     * @var int
     */
    protected $createdTimestamp;

    /**
     * @param  Client  $client
     * @param  array  $channel
     *
     * @internal
     */
    function __construct(Client $client, array $channel)
    {
        parent::__construct($client);

        $storage = $this->client->getOption('internal.storages.messages');
        $this->messages = new $storage($this->client, $this);
        $this->typings = new Collection();

        $this->id = (string) $channel['id'];
        $this->lastMessageID = DataHelpers::typecastVariable(
            ($channel['last_message_id'] ?? null),
            'string'
        );

        $this->createdTimestamp = (int) Snowflake::deconstruct($this->id)->timestamp;

        $this->ownerID = DataHelpers::typecastVariable(
            ($channel['owner_id'] ?? null),
            'string'
        );
        $this->recipients = new Collection();

        if (! empty($channel['recipients'])) {
            foreach ($channel['recipients'] as $rec) {
                $user = $this->client->users->patch($rec);
                if ($user) {
                    $this->recipients->set($user->id, $user);
                }
            }
        }
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

        switch ($name) {
            case 'createdAt':
                return DataHelpers::makeDateTime($this->createdTimestamp);
                break;
            case 'owner':
                return $this->client->users->get($this->ownerID);
                break;
        }

        return parent::__get($name);
    }

    /**
     * Determines whether a given user is a recipient of this channel.
     *
     * @param  User|string  $user  The User instance or user ID.
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    function isRecipient($user)
    {
        $user = $this->client->users->resolve($user);

        return $this->recipients->has($user->id);
    }

    /**
     * @return void
     * @internal
     */
    function _patch(array $channel)
    {
        $this->ownerID = DataHelpers::typecastVariable(
            ($channel['owner_id'] ?? $this->ownerID ?? null),
            'string'
        );
        $this->lastMessageID = DataHelpers::typecastVariable(
            ($channel['last_message_id'] ?? $this->lastMessageID ?? null),
            'string'
        );

        if (isset($channel['recipients'])) {
            $this->recipients->clear();

            foreach ($channel['recipients'] as $rec) {
                $user = $this->client->users->patch($rec);
                if ($user) {
                    $this->recipients->set($user->id, $user);
                }
            }
        }
    }

    public function __call($name, $arguments)
    {
        // TODO: Implement @method string getId()
        // TODO: Implement @method int getCreatedTimestamp()
        // TODO: Implement @method \CharlotteDunois\Yasmin\Interfaces\MessageStorageInterface getMessages()
        // TODO: Implement @method string getLastMessageID()
    }
}
