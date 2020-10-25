<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Interfaces\GuildStorageInterface;

/**
 * Guild Storage to store guilds, utilizes Collection.
 */
class GuildStorage extends Storage implements GuildStorageInterface
{
    /**
     * Resolves given data to a guild.
     *
     * @param  Guild|string|int  $guild  string/int = guild ID
     *
     * @return Guild
     * @throws \InvalidArgumentException
     */
    public function resolve($guild)
    {
        if ($guild instanceof Guild) {
            return $guild;
        }

        if (is_int($guild)) {
            $guild = (string) $guild;
        }

        if (is_string($guild) && parent::has($guild)) {
            return parent::get($guild);
        }

        throw new \InvalidArgumentException('Unable to resolve unknown guild');
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
     * @return Guild|null
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     * @param  Guild  $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        parent::set($key, $value);

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
        if ($this !== $this->client->guilds) {
            $this->client->guilds->delete($key);
        }

        return $this;
    }

    /**
     * Factory to create (or retrieve existing) guilds.
     *
     * @param  array  $data
     * @param  int|null  $shardID
     *
     * @return Guild
     * @internal
     */
    public function factory(array $data, ?int $shardID = null)
    {
        if (parent::has($data['id'])) {
            $guild = parent::get($data['id']);
            $guild->_patch($data);

            return $guild;
        }

        $guild = new Guild($this->client, $data, $shardID);
        $this->set($guild->id, $guild);

        return $guild;
    }
}
