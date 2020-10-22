<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\PresenceStorageInterface;

/**
 * Presence Storage, which utilizes Collection.
 */
class PresenceStorage extends Storage implements PresenceStorageInterface
{
    /**
     * Whether the presence cache is enabled.
     *
     * @var bool
     */
    protected $enabled;

    /**
     * @param  Client  $client
     * @param  array|null  $data
     *
     * @internal
     */
    function __construct(Client $client, ?array $data = null)
    {
        parent::__construct($client, $data);
        $this->enabled = (bool) $this->client->getOption('presenceCache', true);
    }

    /**
     * Resolves given data to a presence.
     *
     * @param  Presence|User|string|int  $presence  string/int = user ID
     *
     * @return Presence
     * @throws \InvalidArgumentException
     */
    function resolve($presence)
    {
        if ($presence instanceof Presence) {
            return $presence;
        }

        if ($presence instanceof User) {
            $presence = $presence->id;
        }

        if (\is_int($presence)) {
            $presence = (string) $presence;
        }

        if (\is_string($presence) && parent::has($presence)) {
            return parent::get($presence);
        }

        throw new \InvalidArgumentException('Unable to resolve unknown presence');
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return bool
     */
    function has($key)
    {
        return parent::has($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return Presence|null
     */
    function get($key)
    {
        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     * @param  Presence  $value
     *
     * @return $this
     */
    function set($key, $value)
    {
        if (! $this->enabled) {
            return $this;
        }

        parent::set($key, $value);
        if ($this !== $this->client->presences) {
            $this->client->presences->set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return $this
     */
    function delete($key)
    {
        if (! $this->enabled) {
            return $this;
        }

        parent::delete($key);
        if ($this !== $this->client->presences) {
            $this->client->presences->delete($key);
        }

        return $this;
    }

    /**
     * Factory to create presences.
     *
     * @param  array  $data
     *
     * @return Presence
     * @internal
     */
    function factory(array $data)
    {
        $presence = new Presence($this->client, $data);
        $this->set($presence->userID, $presence);

        return $presence;
    }
}
