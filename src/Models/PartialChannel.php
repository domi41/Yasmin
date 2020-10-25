<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\HTTP\APIEndpoints;
use CharlotteDunois\Yasmin\Utils\DataHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;

/**
 * Represents a partial channel (of a guild or a group DM).
 *
 * @property string $id                The channel ID.
 * @property string|null $name              The channel name, or null.
 * @property string $type              The type of the channel.
 * @property string|null $icon              The icon of the channel, or null.
 * @property int $createdTimestamp  The timestamp when this channel was created.
 *
 * @property \DateTime $createdAt         The DateTime instance of createdTimestamp.
 */
class PartialChannel extends ClientBase
{
    /**
     * The channel ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The channel name, or null.
     *
     * @var string|null
     */
    protected $name;

    /**
     * The type of the channel.
     *
     * @var string
     */
    protected $type;

    /**
     * The icon of the channel, or null.
     *
     * @var string|null
     */
    protected $icon;

    /**
     * The timestamp when this channel was created.
     *
     * @var int
     */
    protected $createdTimestamp;

    /**
     * @param  Client  $client
     * @param  array  $channel
     *
     * @internal
     */
    public function __construct(Client $client, array $channel)
    {
        parent::__construct($client);

        $this->id = (string) $channel['id'];
        $this->name = $channel['name'] ?? null;
        $this->type = ChannelStorage::CHANNEL_TYPES[$channel['type']];
        $this->icon = $channel['icon'] ?? null;

        $this->createdTimestamp = (int) Snowflake::deconstruct($this->id)->timestamp;
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
        }

        return parent::__get($name);
    }

    /**
     * Returns the group DM's icon URL, or null.
     *
     * @param  int|null  $size  One of 128, 256, 512, 1024 or 2048.
     * @param  string  $format  One of png, jpg or webp.
     *
     * @return string|null
     */
    public function getIconURL(?int $size = null, string $format = 'png')
    {
        if ($size & ($size - 1)) {
            throw new \InvalidArgumentException('Invalid size "'.$size.'", expected any powers of 2');
        }

        if ($this->icon !== null) {
            return APIEndpoints::CDN['url'].APIEndpoints::format(
                    APIEndpoints::CDN['channelicons'],
                    $this->id,
                    $this->icon,
                    $format
                ).(! empty($size) ? '?size='.$size : '');
        }

        return null;
    }

    /**
     * Automatically converts to the channel name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
