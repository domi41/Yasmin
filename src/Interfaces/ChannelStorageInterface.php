<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Interfaces;

use CharlotteDunois\Yasmin\Models\Guild;
use InvalidArgumentException;

/**
 * Something all channel storages implement. The storage also is used as factory.
 */
interface ChannelStorageInterface extends StorageInterface
{
    /**
     * Returns the current element. From Iterator interface.
     *
     * @return ChannelInterface
     */
    public function current();

    /**
     * Fetch the key from the current element. From Iterator interface.
     *
     * @return string
     */
    public function key();

    /**
     * Advances the internal pointer. From Iterator interface.
     *
     * @return ChannelInterface|false
     */
    public function next();

    /**
     * Resets the internal pointer. From Iterator interface.
     *
     * @return ChannelInterface|false
     */
    public function rewind();

    /**
     * Checks if current position is valid. From Iterator interface.
     *
     * @return bool
     */
    public function valid();

    /**
     * Returns all items.
     *
     * @return ChannelInterface[]
     */
    public function all();

    /**
     * Resolves given data to a channel.
     *
     * @param  ChannelInterface|string|int  $channel  string/int = channel ID
     *
     * @return ChannelInterface
     * @throws InvalidArgumentException
     */
    public function resolve($channel);

    /**
     * Determines if a given key exists in the collection.
     *
     * @param  string  $key
     *
     * @return bool
     * @throws InvalidArgumentException
     */
    public function has($key);

    /**
     * Returns the item at a given key. If the key does not exist, null is returned.
     *
     * @param  string  $key
     *
     * @return ChannelInterface|null
     * @throws InvalidArgumentException
     */
    public function get($key);

    /**
     * Sets a key-value pair.
     *
     * @param  string  $key
     * @param  ChannelInterface  $value
     *
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set($key, $value);

    /**
     * Factory to create (or retrieve existing) channels.
     *
     * @param  array  $data
     * @param  Guild|null  $guilds
     *
     * @return ChannelInterface
     * @internal
     */
    public function factory(array $data, ?Guild $guild = null);
}
