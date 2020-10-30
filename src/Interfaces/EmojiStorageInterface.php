<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Interfaces;

use CharlotteDunois\Yasmin\Models\Emoji;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use InvalidArgumentException;

/**
 * Something all emoji storages implement. The storage also is used as factory.
 */
interface EmojiStorageInterface extends StorageInterface
{
    /**
     * Returns the current element. From Iterator interface.
     *
     * @return Emoji
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
     * @return Emoji|false
     */
    public function next();

    /**
     * Resets the internal pointer. From Iterator interface.
     *
     * @return Emoji|false
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
     * @return Emoji[]
     */
    public function all();

    /**
     * Resolves given data to an emoji.
     *
     * @param  Emoji|MessageReaction|string|int  $emoji  string/int = emoji ID
     *
     * @return Emoji
     * @throws InvalidArgumentException
     */
    public function resolve($emoji);

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
     * @return Emoji|null
     * @throws InvalidArgumentException
     */
    public function get($key);

    /**
     * Sets a key-value pair.
     *
     * @param  string  $key
     * @param  Emoji  $value
     *
     * @return $this
     * @throws InvalidArgumentException
     */
    public function set($key, $value);

    /**
     * Factory to create (or retrieve existing) emojis.
     *
     * @param  array  $data
     *
     * @return Emoji
     * @internal
     */
    public function factory(array $data);
}
