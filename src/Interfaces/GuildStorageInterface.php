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
 * Something all guild storages implement. The storage also is used as factory.
 */
interface GuildStorageInterface extends StorageInterface
{
    /**
     * Returns the current element. From Iterator interface.
     *
     * @return Guild
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
     * @return Guild|false
     */
    public function next();

    /**
     * Resets the internal pointer. From Iterator interface.
     *
     * @return Guild|false
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
     * @return Guild[]
     */
    public function all();

    /**
     * Resolves given data to a guild.
     *
     * @param  Guild|string|int  $guild  string/int = guild ID
     *
     * @return Guild
     * @throws InvalidArgumentException
     */
    public function resolve($guild);

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
     * @return Guild|null
     * @throws InvalidArgumentException
     */
    public function get($key);

    /**
     * Sets a key-value pair.
     *
     * @param  string  $key
     * @param  Guild  $value
     *
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set($key, $value);

    /**
     * Factory to create (or retrieve existing) guilds.
     *
     * @param  array  $data
     * @param  int|null  $shardID
     *
     * @return Guild
     * @internal
     */
    public function factory(array $data, ?int $shardID = null);
}
