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
use CharlotteDunois\Yasmin\Interfaces\GuildStoreChannelInterface;
use CharlotteDunois\Yasmin\Traits\GuildChannelTrait;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;

/**
 * Represents a guild's store channel.
 *
 * @property string $id                     The channel ID.
 * @property Guild $guild                  The associated guild.
 * @property int $createdTimestamp       The timestamp of when this channel was created.
 * @property string $name                   The channel name.
 * @property bool $nsfw                   Whether the channel is marked as NSFW or not.
 * @property string|null $parentID               The ID of the parent channel, or null.
 * @property int $position               The channel position.
 * @property Collection $permissionOverwrites   A collection of PermissionOverwrite instances, mapped by their ID.
 *
 * @property \DateTime $createdAt              The DateTime instance of createdTimestamp.
 * @property \CharlotteDunois\Yasmin\Models\CategoryChannel|null $parent                 The channel's parent, or null.
 * @method string getId()
 * @method int getCreatedTimestamp()
 * @method string getName()
 * @method Guild getGuild()
 * @method int getPosition()
 * @method Collection getPermissionOverwrites()
 * @method null getParent()
 */
class GuildStoreChannel extends ClientBase implements GuildStoreChannelInterface
{
    use GuildChannelTrait;

    /**
     * The associated guild.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * The channel ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The ID of the parent channel, or null.
     *
     * @var string|null
     */
    protected $parentID;

    /**
     * The channel name.
     *
     * @var string
     */
    protected $name;

    /**
     * Whether the channel is marked as NSFW or not.
     *
     * @var bool
     */
    protected $nsfw;

    /**
     * The channel position.
     *
     * @var int
     */
    protected $position;

    /**
     * A collection of PermissionOverwrite instances, mapped by their ID.
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
     * @throws \RuntimeException
     * @internal
     */
    public function __get($name)
    {
        if (\property_exists($this, $name)) {
            return $this->$name;
        }

        switch ($name) {
            case 'createdAt':
                return DataHelpers::makeDateTime($this->createdTimestamp);
                break;
            case 'parent':
                return $this->guild->channels->get($this->parentID);
                break;
        }

        return parent::__get($name);
    }

    /**
     * Automatically converts to a mention.
     *
     * @return string
     */
    public function __toString()
    {
        return '<#'.$this->id.'>';
    }

    /**
     * @param  array  $channel
     *
     * @return void
     * @internal
     */
    public function _patch(array $channel)
    {
        $this->name = (string) ($channel['name'] ?? $this->name ?? '');
        $this->nsfw = (bool) ($channel['nsfw'] ?? $this->nsfw ?? false);
        $this->parentID = DataHelpers::typecastVariable(
            ($channel['parent_id'] ?? $this->parentID ?? null),
            'string'
        );
        $this->position = (int) ($channel['position'] ?? $this->position ?? 0);

        if (isset($channel['permission_overwrites'])) {
            $this->permissionOverwrites->clear();

            foreach ($channel['permission_overwrites'] as $permission) {
                $overwrite = new PermissionOverwrite($this->client, $this, $permission);
                $this->permissionOverwrites->set($overwrite->id, $overwrite);
            }
        }
    }
}
