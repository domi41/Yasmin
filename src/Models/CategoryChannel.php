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
use CharlotteDunois\Yasmin\Interfaces\CategoryChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\StorageInterface;
use CharlotteDunois\Yasmin\Traits\GuildChannelTrait;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;
use DateTime;
use RuntimeException;

/**
 * Represents a guild's category channel.
 *
 * @property string $id                     The ID of the channel.
 * @property string $name                   The channel name.
 * @property Guild $guild                  The guild this category channel belongs to.
 * @property int $createdTimestamp       The timestamp of when this channel was created.
 * @property int $position               The channel position.
 * @property Collection $permissionOverwrites   A collection of PermissionOverwrite instances.
 *
 * @property DateTime $createdAt              The DateTime instance of createdTimestamp.
 * @method string getId()
 * @method int getCreatedTimestamp()
 * @method string getName()
 * @method Guild getGuild()
 * @method int getPosition()
 * @method Collection getPermissionOverwrites()
 * @method null getParent()
 */
class CategoryChannel extends ClientBase implements CategoryChannelInterface
{
    use GuildChannelTrait;

    /**
     * The guild this category channel belongs to.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * The ID of the channel.
     *
     * @var string
     */
    protected $id;

    /**
     * The channel name.
     *
     * @var string
     */
    protected $name;

    /**
     * The channel position.
     *
     * @var int
     */
    protected $position;

    /**
     * A collection of PermissionOverwrite instances.
     *
     * @var Collection
     */
    protected $permissionOverwrites;

    /**
     * The timestamp of when this channel was created.
     *
     * @var int
     */
    protected $createdTimestamp;

    /**
     * @param  Client  $client
     * @param  Guild  $guild
     * @param  array  $channel
     *
     * @internal
     */
    public function __construct(
        Client $client,
        Guild $guild,
        array $channel
    ) {
        parent::__construct($client);
        $this->guild = $guild;

        $this->id = (string) $channel['id'];
        $this->createdTimestamp = (int) Snowflake::deconstruct($this->id)->timestamp;
        $this->permissionOverwrites = new Collection();

        $this->_patch($channel);
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
        }

        return parent::__get($name);
    }

    /**
     * Returns all channels which are childrens of this category.
     *
     * @return StorageInterface
     */
    public function getChildren()
    {
        return $this->guild->channels->filter(
            function ($channel) {
                return $channel->parentID === $this->id;
            }
        );
    }

    /**
     * @return void
     * @internal
     */
    public function _patch(array $channel)
    {
        $this->name = (string) ($channel['name'] ?? $this->name ?? '');
        $this->position = (int) ($channel['position'] ?? $this->position ?? 0);

        if (isset($channel['permission_overwrites'])) {
            $this->permissionOverwrites->clear();

            foreach ($channel['permission_overwrites'] as $permission) {
                $this->permissionOverwrites->set(
                    $permission['id'],
                    new PermissionOverwrite(
                        $this->client,
                        $this,
                        $permission
                    )
                );
            }
        }
    }
}
