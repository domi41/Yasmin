<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Traits;

use BadMethodCallException;
use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Interfaces\VoiceChannelInterface;
use CharlotteDunois\Yasmin\Models\CategoryChannel;
use CharlotteDunois\Yasmin\Models\ChannelStorage;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\Invite;
use CharlotteDunois\Yasmin\Models\PermissionOverwrite;
use CharlotteDunois\Yasmin\Models\Permissions;
use CharlotteDunois\Yasmin\Models\Role;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use InvalidArgumentException;
use function React\Promise\all;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use function React\Promise\resolve;

/**
 * The trait all guild channels use.
 */
trait GuildChannelTrait
{
    /**
     * Creates an invite. Resolves with an instance of Invite.
     *
     * Options are as following (all are optional).
     *
     * ```
     * array(
     *    'maxAge' => int,
     *    'maxUses' => int, (0 = unlimited)
     *    'temporary' => bool,
     *    'unique' => bool
     * )
     * ```
     *
     * @param  array  $options
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Invite
     */
    public function createInvite(array $options = [])
    {
        $data = [
            'max_uses'  => ($options['maxUses'] ?? 0),
            'temporary' => ($options['temporary'] ?? false),
            'unique'    => ($options['unique'] ?? false),
        ];

        if (isset($options['maxAge'])) {
            $data['max_age'] = $options['maxAge'];
        }

        return new Promise(
            function (callable $resolve, callable $reject) use ($data) {
                $this->client->apimanager()->endpoints->channel->createChannelInvite($this->id, $data)->done(
                    function ($data) use ($resolve) {
                        $invite = new Invite($this->client, $data);
                        $resolve($invite);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Clones a guild channel. Resolves with an instance of GuildChannelInterface.
     *
     * @param  string  $name  Optional name for the new channel, otherwise it has the name of this channel.
     * @param  bool  $withPermissions  Whether to clone the channel with this channel's permission overwrites
     * @param  bool  $withTopic  Whether to clone the channel with this channel's topic.
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface
     */
    public function clone(
        string $name = null,
        bool $withPermissions = true,
        bool $withTopic = true,
        string $reason = ''
    ) {
        $data = [
            'name' => (! empty($name) ? ((string) $name) : $this->name),
            'type' => ChannelStorage::getTypeForChannel($this),
        ];

        if ($withPermissions) {
            $data['permission_overwrites'] = array_values($this->permissionOverwrites->all());
        }

        if ($withTopic) {
            $data['topic'] = $this->topic;
        }

        if ($this->parentID) {
            $data['parent_id'] = $this->parentID;
        }

        if ($this instanceof VoiceChannelInterface) {
            $data['bitrate'] = $this->bitrate;
            $data['user_limit'] = $this->userLimit;
        } else {
            $data['nsfw'] = $this->nsfw;
        }

        return new Promise(
            function (callable $resolve, callable $reject) use ($data, $reason) {
                $this->client->apimanager()->endpoints->guild->createGuildChannel(
                    $this->guild->id,
                    $data,
                    $reason
                )->done(
                    function ($data) use ($resolve) {
                        $channel = $this->guild->channels->factory($data, $this->guild);
                        $resolve($channel);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Edits the channel. Resolves with $this.
     *
     * Options are as following (at least one is required).
     *
     * ```
     * array(
     *    'name' => string,
     *    'position' => int,
     *    'topic' => string, (text channels only)
     *    'nsfw' => bool, (text channels only)
     *    'bitrate' => int, (voice channels only)
     *    'userLimit' => int, (voice channels only)
     *    'slowmode' => int, (text channels only, 0-21600)
     *    'parent' => \CharlotteDunois\Yasmin\Models\CategoryChannel|string, (string = channel ID)
     *    'permissionOverwrites' => \CharlotteDunois\Collect\Collection|array (an array or Collection of PermissionOverwrite instances or permission overwrite arrays)
     * )
     * ```
     *
     * @param  array  $options
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function edit(array $options, string $reason = '')
    {
        if (empty($options)) {
            throw new InvalidArgumentException('Unable to edit channel with zero information');
        }

        $data = DataHelpers::applyOptions(
            $options,
            [
                'name'                 => ['type' => 'string'],
                'position'             => ['type' => 'int'],
                'topic'                => ['type' => 'string'],
                'nsfw'                 => ['type' => 'bool'],
                'bitrate'              => ['type' => 'int'],
                'userLimit'            => ['key' => 'user_limit', 'type' => 'int'],
                'slowmode'             => ['key' => 'rate_limit_per_user', 'type' => 'int'],
                'parent'               => [
                    'key'   => 'parent_id',
                    'parse' => function ($val) {
                        return $val instanceof CategoryChannel ? $val->id : $val;
                    },
                ],
                'permissionOverwrites' => [
                    'key'   => 'permission_overwrites',
                    'parse' => function ($val) {
                        if ($val instanceof Collection) {
                            $val = $val->all();
                        }

                        return array_values($val);
                    },
                ],
            ]
        );

        return new Promise(
            function (callable $resolve, callable $reject) use ($data, $reason) {
                $this->client->apimanager()->endpoints->channel->modifyChannel($this->id, $data, $reason)->done(
                    function () use ($resolve) {
                        $resolve($this);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Deletes the channel.
     *
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     */
    public function delete(string $reason = '')
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($reason) {
                $this->client->apimanager()->endpoints->channel->deleteChannel($this->id, $reason)->done(
                    function () use ($resolve) {
                        $resolve();
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Fetches all invites of this channel. Resolves with a Collection of Invite instances, mapped by their code.
     *
     * @return ExtendedPromiseInterface
     * @see \CharlotteDunois\Yasmin\Models\Invite
     */
    public function fetchInvites()
    {
        return new Promise(
            function (callable $resolve, callable $reject) {
                $this->client->apimanager()->endpoints->channel->getChannelInvites($this->id)->done(
                    function ($data) use ($resolve) {
                        $collection = new Collection();

                        foreach ($data as $invite) {
                            $inv = new Invite($this->client, $invite);
                            $collection->set($inv->code, $inv);
                        }

                        $resolve($collection);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Whether the permission overwrites match the parent channel (permissions synced).
     *
     * @return bool
     * @throws BadMethodCallException
     */
    public function isPermissionsLocked()
    {
        if ($this->parentID === null) {
            throw new BadMethodCallException('This channel does not have a parent');
        }

        $parent = $this->parent;

        if ($parent->permissionOverwrites->count() !== $this->permissionOverwrites->count()) {
            return false;
        }

        return ! ((bool) $this->permissionOverwrites->first(
            function ($perm) use ($parent) {
                $permp = $parent->permissionOverwrites->get($perm->id);

                return ! $permp || $perm->allowed->bitfield !== $permp->allowed->bitfield || $perm->denied->bitfield !== $permp->denied->bitfield;
            }
        ));
    }

    /**
     * Returns the permissions for the given member.
     *
     * @param  GuildMember|string  $member
     *
     * @return Permissions
     * @throws InvalidArgumentException
     * @see https://discordapp.com/developers/docs/topics/permissions#permission-overwrites
     */
    public function permissionsFor($member)
    {
        $member = $this->guild->members->resolve($member);

        if ($member->id === $this->guild->ownerID) {
            return new Permissions(Permissions::ALL);
        }

        $permissions = $member->permissions;

        if ($permissions->has('ADMINISTRATOR')) {
            return new Permissions(Permissions::ALL);
        }

        $overwrites = $this->overwritesFor($member);

        if ($overwrites['everyone']) {
            $permissions->remove(
                ($overwrites['everyone']->deny->bitfield & ~Permissions::CHANNEL_UNACCESSIBLE_PERMISSIONS)
            );
            $permissions->add(
                ($overwrites['everyone']->allow->bitfield & ~Permissions::CHANNEL_UNACCESSIBLE_PERMISSIONS)
            );
        }

        foreach ($overwrites['roles'] as $role) {
            $permissions->remove(
                ($role->deny->bitfield & ~Permissions::CHANNEL_UNACCESSIBLE_PERMISSIONS)
            );
            $permissions->add(
                ($role->allow->bitfield & ~Permissions::CHANNEL_UNACCESSIBLE_PERMISSIONS)
            );
        }

        if ($overwrites['member']) {
            $permissions->remove(
                ($overwrites['member']->deny->bitfield & ~Permissions::CHANNEL_UNACCESSIBLE_PERMISSIONS)
            );
            $permissions->add(
                ($overwrites['member']->allow->bitfield & ~Permissions::CHANNEL_UNACCESSIBLE_PERMISSIONS)
            );
        }

        return $permissions;
    }

    /**
     * Returns the permissions overwrites for the given member as an associative array.
     *
     * ```
     * array(
     *     'everyone' => \CharlotteDunois\Yasmin\Models\PermissionOverwrite|null,
     *     'member' => \CharlotteDunois\Yasmin\Models\PermissionOverwrite|null,
     *     'roles' => \CharlotteDunois\Yasmin\Models\PermissionOverwrite[]
     * )
     * ```
     *
     * @param  GuildMember|string  $member
     *
     * @return array
     * @throws InvalidArgumentException
     */
    public function overwritesFor($member)
    {
        $member = $this->guild->members->resolve($member);

        $everyoneOverwrites = null;
        $memberOverwrites = null;
        $rolesOverwrites = [];

        foreach ($this->permissionOverwrites as $overwrite) {
            if ($overwrite->id === $this->guild->id) {
                $everyoneOverwrites = $overwrite;
            } elseif ($overwrite->id === $member->id) {
                $memberOverwrites = $overwrite;
            } elseif ($member->roles->has($overwrite->id)) {
                $rolesOverwrites[] = $overwrite;
            }
        }

        return [
            'everyone' => $everyoneOverwrites,
            'member'   => $memberOverwrites,
            'roles'    => $rolesOverwrites,
        ];
    }

    /**
     * Overwrites the permissions for a member or role in this channel. Resolves with an instance of PermissionOverwrite.
     *
     * @param  GuildMember|Role|string  $memberOrRole  The member or role.
     * @param  Permissions|int  $allow  Which permissions should be allowed?
     * @param  Permissions|int  $deny  Which permissions should be denied?
     * @param  string  $reason  The reason for this.
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\Models\PermissionOverwrite
     */
    public function overwritePermissions($memberOrRole, $allow, $deny = 0, string $reason = '')
    {
        [$memberOrRole, $options] = $this->validateOverwritePermissions($memberOrRole, $allow, $deny);

        return new Promise(
            function (callable $resolve, callable $reject) use ($memberOrRole, $options, $reason) {
                $this->client->apimanager()->endpoints->channel->editChannelPermissions(
                    $this->id,
                    $memberOrRole,
                    $options,
                    $reason
                )->done(
                    function () use ($memberOrRole, $options, $resolve, $reject) {
                        $options['id'] = $memberOrRole;

                        if ($options['type'] === 'member') {
                            $fetch = $this->guild->fetchMember($options['id']);
                        } else {
                            $fetch = resolve();
                        }

                        $fetch->done(
                            function () use ($options, $resolve) {
                                if ($options['allow'] instanceof Permissions) {
                                    $options['allow'] = $options['allow']->bitfield;
                                }

                                if ($options['deny'] instanceof Permissions) {
                                    $options['deny'] = $options['deny']->bitfield;
                                }

                                $overwrite = new PermissionOverwrite(
                                    $this->client,
                                    $this,
                                    $options
                                );
                                $this->permissionOverwrites->set($overwrite->id, $overwrite);

                                $resolve($overwrite);
                            },
                            $reject
                        );
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Locks in the permission overwrites from the parent channel. Resolves with $this.
     *
     * @param  string  $reason  The reason for this.
     *
     * @return ExtendedPromiseInterface
     * @throws BadMethodCallException
     */
    public function lockPermissions(string $reason = '')
    {
        if (! $this->parent) {
            throw new BadMethodCallException('This channel does not have a parent');
        }

        $overwrites = array_values(
            $this->parent->permissionOverwrites->map(
                function ($overwrite) {
                    return [
                        'id'    => $overwrite->id,
                        'type'  => $overwrite->type,
                        'allow' => $overwrite->allow->bitfield,
                        'deny'  => $overwrite->deny->bitfield,
                    ];
                }
            )->all()
        );

        return new Promise(
            function (callable $resolve, callable $reject) use ($overwrites, $reason) {
                $promises = [];

                $overwritesID = array_column($overwrites, 'id');
                foreach ($this->permissionOverwrites as $perm) {
                    if (! in_array($perm->id, $overwritesID)) {
                        $promises[] = $perm->delete($reason);
                    }
                }

                foreach ($overwrites as $overwrite) {
                    $promises[] = $this->client->apimanager()->endpoints->channel->editChannelPermissions(
                        $this->id,
                        $overwrite['id'],
                        $overwrite,
                        $reason
                    );
                }

                all($promises)->done(
                    function () use ($resolve) {
                        $resolve($this);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Sets the name of the channel. Resolves with $this.
     *
     * @param  string  $name
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setName(string $name, string $reason = '')
    {
        return $this->edit(['name' => $name], $reason);
    }

    /**
     * Sets the nsfw flag of the channel. Resolves with $this.
     *
     * @param  bool  $nsfw
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setNSFW(bool $nsfw, string $reason = '')
    {
        return $this->edit(['nsfw' => $nsfw], $reason);
    }

    /**
     * Sets the parent of the channel. Resolves with $this.
     *
     * @param  CategoryChannel|string  $parent  An instance of CategoryChannel or the channel ID.
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setParent($parent, string $reason = '')
    {
        return $this->edit(['parent' => $parent], $reason);
    }

    /**
     * Sets the permission overwrites of the channel. Resolves with $this.
     *
     * @param  Collection|array  $permissionOverwrites  An array or Collection of PermissionOverwrite instances or permission overwrite arrays.
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setPermissionOverwrites($permissionOverwrites, string $reason = '')
    {
        return $this->edit(['permissionOverwrites' => $permissionOverwrites], $reason);
    }

    /**
     * Sets the position of the channel. Resolves with $this.
     *
     * @param  int  $position
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setPosition(int $position, string $reason = '')
    {
        if ($position < 0) {
            throw new InvalidArgumentException('Position can not be below 0');
        }

        $newPositions = [];
        $newPositions[] = ['id' => $this->id, 'position' => $position];

        $count = $this->guild->channels->count();
        $channels = $this->guild->channels->sortCustom(
            function ($a, $b) {
                if ($a->position === $b->position) {
                    return $a->id <=> $b->id;
                }

                return $a->position <=> $b->position;
            }
        )->values()->all();

        $pos = 0;
        for ($i = 0; $i < $count; $i++) {
            if ($pos === $position) {
                $pos++;
            }

            $channel = $channels[$i];

            if ($pos === $channel->position) {
                continue;
            }

            $newPositions[] = ['id' => $channel, 'position' => $pos];
            $pos++;
        }

        return new Promise(
            function (callable $resolve, callable $reject) use ($newPositions, $reason) {
                $this->client->apimanager()->endpoints->guild->modifyGuildChannelPositions(
                    $this->guild->id,
                    $newPositions,
                    $reason
                )->done(
                    function () use ($resolve) {
                        $resolve($this);
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Sets the topic of the channel. Resolves with $this.
     *
     * @param  string  $topic
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setTopic(string $topic, string $reason = '')
    {
        return $this->edit(['topic' => $topic], $reason);
    }

    /**
     * Validates the given overwritePermissions arguments.
     *
     * @param  GuildMember|Role|string  $memberOrRole  The member or role.
     * @param  Permissions|int  $allow  Which permissions should be allowed?
     * @param  Permissions|int  $deny  Which permissions should be denied?
     *
     * @return array
     * @throws InvalidArgumentException
     */
    protected function validateOverwritePermissions($memberOrRole, $allow, $deny = 0)
    {
        $options = [];

        if ($memberOrRole instanceof GuildMember) {
            $memberOrRole = $memberOrRole->id;
            $options['type'] = 'member';
        } elseif ($memberOrRole instanceof Role) {
            $memberOrRole = $memberOrRole->id;
            $options['type'] = 'role';
        } else {
            try {
                $memberOrRole = $this->guild->roles->resolve($memberOrRole)->id;
                $options['type'] = 'role';
            } catch (InvalidArgumentException $e) {
                $options['type'] = 'member';
            }
        }

        if (! is_int($allow) && ! ($allow instanceof Permissions)) {
            throw new InvalidArgumentException('Allow has to be an int or an instance of Permissions');
        }

        if (! is_int($deny) && ! ($deny instanceof Permissions)) {
            throw new InvalidArgumentException('Deny has to be an int or an instance of Permissions');
        }

        if (json_encode($allow) === json_encode($deny)) {
            throw new InvalidArgumentException('Allow and deny must have different permissions');
        }

        $options['allow'] = $allow;
        $options['deny'] = $deny;

        return [$memberOrRole, $options];
    }
}
