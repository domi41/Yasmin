<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\EmojiStorageInterface;
use InvalidArgumentException;


/**
 * Emoji Storage to store emojis, utilizes Collection.
 */
class EmojiStorage extends Storage implements EmojiStorageInterface
{
    /**
     * The guild this storage belongs to.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * @param  Client  $client
     * @param  Guild|null  $guild
     * @param  array|null  $data
     *
     * @internal
     */
    public function __construct(Client $client, ?Guild $guild = null, ?array $data = null)
    {
        parent::__construct($client, $data);
        $this->guild = $guild;

        $this->baseStorageArgs[] = $this->guild;
    }

    /**
     * Resolves given data to an emoji.
     *
     * @param  Emoji|MessageReaction|string|int  $emoji  string/int = emoji ID
     *
     * @return Emoji
     * @throws InvalidArgumentException
     */
    public function resolve($emoji)
    {
        if ($emoji instanceof Emoji) {
            return $emoji;
        }

        if ($emoji instanceof MessageReaction) {
            return $emoji->emoji;
        }

        if (is_int($emoji)) {
            $emoji = (string) $emoji;
        }

        if (is_string($emoji) && parent::has($emoji)) {
            return parent::get($emoji);
        }

        throw new InvalidArgumentException('Unable to resolve unknown emoji');
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return bool
     */
    public function has($key)
    {
        return parent::has($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return Emoji|null
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     * @param  Emoji  $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        parent::set($key, $value);
        if ($this !== $this->client->emojis) {
            $this->client->emojis->set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return $this
     */
    public function delete($key)
    {
        parent::delete($key);
        if ($this !== $this->client->emojis) {
            $this->client->emojis->delete($key);
        }

        return $this;
    }

    /**
     * Factory to create (or retrieve existing) emojis.
     *
     * @param  array  $data
     *
     * @return Emoji
     * @internal
     */
    public function factory(array $data)
    {
        if (parent::has($data['id'])) {
            $emoji = parent::get($data['id']);
            $emoji->_patch($data);

            return $emoji;
        }

        $emoji = new Emoji($this->client, $this->guild, $data);

        if ($emoji->id !== null) {
            $this->set($emoji->id, $emoji);
            $this->client->emojis->set($emoji->id, $emoji);
        }

        return $emoji;
    }
}
