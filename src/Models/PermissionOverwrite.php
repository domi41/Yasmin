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
use CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface;
use React\Promise\Promise;

/**
 * Represents a permission overwrite.
 *
 * @property GuildChannelInterface $channel   The channel this Permission Overwrite belongs to.
 * @property string $id        The ID of the Permission Overwrite.
 * @property string $type      The type of the overwrite (member or role).
 * @property Permissions $allow     The allowed Permissions instance.
 * @property Permissions $deny      The denied Permissions instance.
 *
 * @property \CharlotteDunois\Yasmin\Models\Guild $guild     The guild this Permission Overwrite belongs to.
 * @property \CharlotteDunois\Yasmin\Models\Role|\CharlotteDunois\Yasmin\Models\GuildMember|null $target    The role or guild member, or null if not a cached member.
 */
class PermissionOverwrite extends ClientBase
{
    /**
     * The channel this Permission Overwrite belongs to.
     *
     * @var GuildChannelInterface
     */
    protected $channel;

    /**
     * The ID of the Permission Overwrite.
     *
     * @var string
     */
    protected $id;

    /**
     * The type of the overwrite (member or role).
     *
     * @var string
     */
    protected $type;

    /**
     * The allowed Permissions instance.
     *
     * @var Permissions
     */
    protected $allow;

    /**
     * The denied Permissions instance.
     *
     * @var Permissions
     */
    protected $deny;

    /**
     * @param  Client  $client
     * @param  GuildChannelInterface  $channel
     * @param  array  $permission
     *
     * @internal
     */
    function __construct(Client $client, GuildChannelInterface $channel, array $permission)
    {
        parent::__construct($client);
        $this->channel = $channel;

        $this->id = (string) $permission['id'];
        $this->type = (string) $permission['type'];
        $this->allow = new Permissions(($permission['allow'] ?? 0));
        $this->deny = new Permissions(($permission['deny'] ?? 0));
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @throws \RuntimeException
     * @internal
     */
    function __get($name)
    {
        if (\property_exists($this, $name)) {
            return $this->$name;
        }

        switch ($name) {
            case 'guild':
                return $this->channel->getGuild();
                break;
            case 'target':
                return ($this->type === 'role' ? $this->channel->getGuild()->roles->get(
                    $this->id
                ) : $this->channel->getGuild()->members->get($this->id));
                break;
        }

        return parent::__get($name);
    }

    /**
     * Edits the permission overwrite. Resolves with $this.
     *
     * @param  Permissions|int|null  $allow  Which permissions should be allowed?
     * @param  Permissions|int|null  $deny  Which permissions should be denied?
     * @param  string  $reason  The reason for this.
     *
     * @return \React\Promise\ExtendedPromiseInterface
     * @throws \InvalidArgumentException
     */
    function edit($allow, $deny = null, string $reason = '')
    {
        $options = [
            'type' => $this->type,
        ];

        $allow = ($allow !== null ? $allow : $this->allow);
        $deny = ($deny !== null ? $deny : $this->deny);

        if ($allow instanceof Permissions) {
            $allow = $allow->bitfield;
        }

        if ($deny instanceof Permissions) {
            $deny = $deny->bitfield;
        }

        if ($allow === $this->allow->bitfield && $deny === $this->deny->bitfield) {
            throw new \InvalidArgumentException('One of allow or deny has to be changed');
        }

        if (\json_encode($allow) === \json_encode($deny)) {
            throw new \InvalidArgumentException('Allow and deny must have different permissions');
        }

        $options['allow'] = $allow;
        $options['deny'] = $deny;

        return (new Promise(
            function (callable $resolve, callable $reject) use ($options, $reason) {
                $this->client->apimanager()->endpoints->channel->editChannelPermissions(
                    $this->channel->getId(),
                    $this->id,
                    $options,
                    $reason
                )->done(
                    function () use ($options, $resolve) {
                        $this->allow = new Permissions(($options['allow'] ?? 0));
                        $this->deny = new Permissions(($options['deny'] ?? 0));
                        $resolve($this);
                    },
                    $reject
                );
            }
        ));
    }

    /**
     * Deletes the permission overwrite.
     *
     * @param  string  $reason
     *
     * @return \React\Promise\ExtendedPromiseInterface
     */
    function delete(string $reason = '')
    {
        return (new Promise(
            function (callable $resolve, callable $reject) use ($reason) {
                $this->client->apimanager()->endpoints->channel->deleteChannelPermission(
                    $this->channel->getId(),
                    $this->id,
                    $reason
                )->then(
                    function () use ($resolve) {
                        $resolve();
                    },
                    $reject
                );
            }
        ));
    }

    /**
     * @return mixed
     * @internal
     */
    function jsonSerialize()
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
            'allow' => $this->allow,
            'deny' => $this->deny,
        ];
    }
}
