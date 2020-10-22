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
use CharlotteDunois\Yasmin\Interfaces\UserStorageInterface;

/**
 * User Storage to store and cache users, which utlizies Collection.
 */
class UserStorage extends Storage implements UserStorageInterface
{
    /**
     * The sweep timer, or null.
     *
     * @var \React\EventLoop\TimerInterface|\React\EventLoop\Timer\TimerInterface|null
     */
    protected $timer;

    /**
     * @param  Client  $client
     * @param  array|null  $data
     *
     * @internal
     */
    function __construct(Client $client, array $data = null)
    {
        parent::__construct($client, $data);

        $inv = (int) $this->client->getOption('userSweepInterval', 600);
        if ($inv > 0) {
            $this->timer = $this->client->addPeriodicTimer(
                $inv,
                function () {
                    $this->sweep();
                }
            );
        }
    }

    /**
     * Resolves given data to an user.
     *
     * @param  User|GuildMember|string|int  $user  string/int = user ID
     *
     * @return User
     * @throws \InvalidArgumentException
     */
    function resolve($user)
    {
        if ($user instanceof User) {
            return $user;
        }

        if ($user instanceof GuildMember) {
            return $user->user;
        }

        if (\is_int($user)) {
            $user = (string) $user;
        }

        if (\is_string($user) && parent::has($user)) {
            return parent::get($user);
        }

        throw new \InvalidArgumentException('Unable to resolve unknown user');
    }

    /**
     * Patches an user (retrieves the user if the user exists), returns null if only the ID is in the array, or creates an user.
     *
     * @param  array  $user
     *
     * @return User|null
     */
    function patch(array $user)
    {
        if (parent::has($user['id'])) {
            return parent::get($user['id']);
        }

        if (count($user) === 1) {
            return null;
        }

        return $this->factory($user);
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
     * @return User|null
     */
    function get($key)
    {
        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     * @param  User  $value
     *
     * @return $this
     */
    function set($key, $value)
    {
        parent::set($key, $value);
        if ($this !== $this->client->users) {
            $this->client->users->set($key, $value);
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
        parent::delete($key);
        if ($this !== $this->client->users) {
            $this->client->users->delete($key);
        }

        return $this;
    }

    /**
     * Factory to create (or retrieve existing) users.
     *
     * @param  array  $data
     * @param  bool  $userFetched
     *
     * @return User
     * @internal
     */
    function factory(array $data, bool $userFetched = false)
    {
        if (parent::has($data['id'])) {
            $user = parent::get($data['id']);
            $user->_patch($data);

            return $user;
        }

        $user = new User($this->client, $data, false, $userFetched);
        $this->set($user->id, $user);

        return $user;
    }

    /**
     * Sweeps users falling out of scope (no mutual guilds). Returns the amount of sweeped users.
     *
     * @return int
     */
    function sweep()
    {
        $members = \array_unique(
            $this->client->guilds->reduce(
                function ($carry, $g) {
                    return \array_merge($carry, \array_keys($g->members->all()));
                },
                []
            )
        );

        $amount = 0;
        foreach ($this->data as $key => $val) {
            if ($val->id !== $this->client->user->id && ! $val->userFetched && ! \in_array($key, $members, true)) {
                $this->client->presences->delete($key);
                $this->delete($key);

                unset($val);
                $amount++;
            }
        }

        return $amount;
    }
}
