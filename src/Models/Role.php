<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;
use DateTime;
use InvalidArgumentException;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use RuntimeException;

/**
 * Represents a role.
 *
 * @property Guild $guild               The guild the role belongs to.
 * @property string $id                  The role ID.
 * @property string $name                The role name.
 * @property int $createdTimestamp    The timestamp of when the role was created.
 * @property int $color               The color of the role.
 * @property bool $hoist               Whether the role gets displayed separately in the member list.
 * @property int $position            The position of the role in the API.
 * @property Permissions $permissions         The permissions of the role.
 * @property bool $managed             Whether the role is managed by an integration.
 * @property bool $mentionable         Whether the role is mentionable.
 *
 * @property DateTime $createdAt           The DateTime instance of createdTimestamp.
 * @property string $hexColor            Returns the hex color of the role color.
 * @property Collection $members             A collection of all (cached) guild members which have the role.
 */
class Role extends ClientBase
{
    /**
     * The default discord role colors. Mapped by uppercase string to integer.
     *
     * @var array
     * @source
     */
    const DISCORD_COLORS = [
        'AQUA'        => 1752220,
        'BLUE'        => 3447003,
        'GREEN'       => 3066993,
        'PURPLE'      => 10181046,
        'GOLD'        => 15844367,
        'ORANGE'      => 15105570,
        'RED'         => 15158332,
        'GREY'        => 9807270,
        'DARKER_GREY' => 8359053,
        'NAVY'        => 3426654,
        'DARK_AQUA'   => 1146986,
        'DARK_GREEN'  => 2067276,
        'DARK_BLUE'   => 2123412,
        'DARK_GOLD'   => 12745742,
        'DARK_PURPLE' => 7419530,
        'DARK_ORANGE' => 11027200,
        'DARK_GREY'   => 9936031,
        'DARK_RED'    => 10038562,
        'LIGHT_GREY'  => 12370112,
        'DARK_NAVY'   => 2899536,
    ];

    /**
     * The guild the role belongs to.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * The role ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The role name.
     *
     * @var string
     */
    protected $name;

    /**
     * The color of the role.
     *
     * @var int
     */
    protected $color;

    /**
     * Whether the role gets displayed separately in the member list.
     *
     * @var bool
     */
    protected $hoist;

    /**
     * The position of the role in the API.
     *
     * @var int
     */
    protected $position;

    /**
     * The permissions of the role.
     *
     * @var Permissions
     */
    protected $permissions;

    /**
     * Whether the role is managed by an integration.
     *
     * @var bool
     */
    protected $managed;

    /**
     * Whether the role is mentionable.
     *
     * @var bool
     */
    protected $mentionable;

    /**
     * The timestamp of when the role was created.
     *
     * @var int
     */
    protected $createdTimestamp;

    /**
     * @internal
     */
    public function __construct(Client $client, Guild $guild, array $role)
    {
        parent::__construct($client);
        $this->guild = $guild;

        $this->id = (string) $role['id'];
        $this->createdTimestamp = (int) Snowflake::deconstruct($this->id)->timestamp;

        $this->_patch($role);
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @throws RuntimeException
     * @internal
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        switch ($name) {
            case 'createdAt':
                return DataHelpers::makeDateTime($this->createdTimestamp);
                break;
            case 'hexColor':
                return '#'.dechex($this->color);
                break;
            case 'members':
                if ($this->id === $this->guild->id) {
                    return $this->guild->members->copy();
                }

                return $this->guild->members->filter(
                    function ($member) {
                        return $member->roles->has($this->id);
                    }
                );
                break;
        }

        return parent::__get($name);
    }

    /**
     * Compares the position from the role to the given role.
     *
     * @param  Role  $role
     *
     * @return int
     */
    public function comparePositionTo(Role $role)
    {
        if ($this->position === $role->position) {
            return $role->id <=> $this->id;
        }

        return $this->position <=> $role->position;
    }

    /**
     * Edits the role with the given options. Resolves with $this.
     *
     * Options are as following (only one is required):
     *
     * ```
     * array(
     *   'name' => string,
     *   'color' => int|string,
     *   'hoist' => bool,
     *   'position' => int,
     *   'permissions' => int|\CharlotteDunois\Yasmin\Models\Permissions,
     *   'mentionable' => bool
     * )
     * ```
     *
     * @param  array  $options
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveColor()
     */
    public function edit(array $options, string $reason = '')
    {
        if (empty($options)) {
            throw new InvalidArgumentException('Unable to edit role with zero information');
        }

        $data = DataHelpers::applyOptions(
            $options,
            [
                'name'        => ['type' => 'string'],
                'color'       => ['parse' => [DataHelpers::class, 'resolveColor']],
                'hoist'       => ['type' => 'bool'],
                'position'    => ['type' => 'int'],
                'permissions' => null,
                'mentionable' => ['type' => 'bool'],
            ]
        );

        return new Promise(
            function (callable $resolve, callable $reject) use ($data, $reason) {
                $this->client->apimanager()->endpoints->guild->modifyGuildRole(
                    $this->guild->id,
                    $this->id,
                    $data,
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
     * Deletes the role.
     *
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     */
    public function delete(string $reason = '')
    {
        return new Promise(
            function (callable $resolve, callable $reject) use ($reason) {
                $this->client->apimanager()->endpoints->guild->deleteGuildRole(
                    $this->guild->id,
                    $this->id,
                    $reason
                )->done(
                    function () use ($resolve) {
                        $resolve();
                    },
                    $reject
                );
            }
        );
    }

    /**
     * Calculates the positon of the role in the Discord client.
     *
     * @return int
     */
    public function getCalculatedPosition()
    {
        $sorted = $this->guild->roles->sortCustom(
            function (Role $a, Role $b) {
                return $b->comparePositionTo($a);
            }
        );

        return $sorted->indexOf($this);
    }

    /**
     * Whether the role can be edited by the client user.
     *
     * @return bool
     */
    public function isEditable()
    {
        if ($this->managed) {
            return false;
        }

        $member = $this->guild->me;
        if (! $member->permissions->has(Permissions::PERMISSIONS['MANAGE_ROLES'])) {
            return false;
        }

        return $member->getHighestRole()->comparePositionTo($this) > 0;
    }

    /**
     * Set the color of the role. Resolves with $this.
     *
     * @param  int|string  $color
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     * @see \CharlotteDunois\Yasmin\Utils\DataHelpers::resolveColor()
     */
    public function setColor($color, string $reason = '')
    {
        return $this->edit(['color' => $color], $reason);
    }

    /**
     * Set whether or not the role should be hoisted. Resolves with $this.
     *
     * @param  bool  $hoist
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setHoist(bool $hoist, string $reason = '')
    {
        return $this->edit(['hoist' => $hoist], $reason);
    }

    /**
     * Set whether the role is mentionable. Resolves with $this.
     *
     * @param  bool  $mentionable
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setMentionable(bool $mentionable, string $reason = '')
    {
        return $this->edit(['mentionable' => $mentionable], $reason);
    }

    /**
     * Set a new name for the role. Resolves with $this.
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
     * Set the permissions of the role. Resolves with $this.
     *
     * @param  int|Permissions  $permissions
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setPermissions($permissions, string $reason = '')
    {
        return $this->edit(['permissions' => $permissions], $reason);
    }

    /**
     * Set the position of the role. Resolves with $this.
     *
     * @param  int  $position
     * @param  string  $reason
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public function setPosition(int $position, string $reason = '')
    {
        return $this->edit(['position' => $position], $reason);
    }

    /**
     * Automatically converts to a mention.
     *
     * @return string
     */
    public function __toString()
    {
        return '<@&'.$this->id.'>';
    }

    /**
     * @return void
     * @internal
     */
    public function _patch(array $role)
    {
        $this->name = (string) $role['name'];
        $this->color = (int) $role['color'];
        $this->hoist = (bool) $role['hoist'];
        $this->position = (int) $role['position'];
        $this->permissions = new Permissions($role['permissions']);
        $this->managed = (bool) $role['managed'];
        $this->mentionable = (bool) $role['mentionable'];
    }
}
