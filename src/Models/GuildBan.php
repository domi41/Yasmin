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
use React\Promise\ExtendedPromiseInterface;
use RuntimeException;

/**
 * Represents a guild ban.
 *
 * @property Guild $guild   The guild this ban is from.
 * @property User $user    The banned user.
 * @property string|null $reason  The ban reason, or null.
 */
class GuildBan extends ClientBase
{
    /**
     * The guild this ban is from.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * The banned user.
     *
     * @var User
     */
    protected $user;

    /**
     * The ban reason, or null.
     *
     * @var string|null
     */
    protected $reason;

    /**
     * @param  Client  $client
     * @param  Guild  $guild
     * @param  User  $user
     * @param  string|null  $reason
     *
     * @internal
     */
    function __construct(
        Client $client,
        Guild $guild,
        User $user,
        ?string $reason
    ) {
        parent::__construct($client);

        $this->guild = $guild;
        $this->user = $user;
        $this->reason = $reason;
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
     * Unbans the user.
     *
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     */
    function unban(string $reason = '')
    {
        return $this->guild->unban($this->user, $reason);
    }
}
