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
use CharlotteDunois\Yasmin\Utils\ImageHelpers;
use CharlotteDunois\Yasmin\Utils\Snowflake;
use DateTime;
use InvalidArgumentException;
use RuntimeException;


/**
 * Represents a partial guild.
 *
 * @property string $id                The guild ID.
 * @property string $name              The guild name.
 * @property int $createdTimestamp  The timestamp when this guild was created.
 * @property string|null $icon              The guild icon, or null.
 * @property string|null $splash            The guild splash, or null.
 *
 * @property DateTime $createdAt          The DateTime instance of createdTimestamp.
 */
class PartialGuild extends ClientBase
{
    /**
     * The guild ID.
     *
     * @var string
     */
    protected $id;

    /**
     * The guild name.
     *
     * @var string
     */
    protected $name;

    /**
     * The guild icon, or null.
     *
     * @var string
     */
    protected $icon;

    /**
     * The guild splash, or null.
     *
     * @var string
     */
    protected $splash;

    /**
     * The timestamp when this guild was created.
     *
     * @var int
     */
    protected $createdTimestamp;

    /**
     * @internal
     */
    public function __construct(Client $client, array $guild)
    {
        parent::__construct($client);

        $this->id = (string) $guild['id'];
        $this->name = (string) $guild['name'];
        $this->icon = $guild['icon'] ?? null;
        $this->splash = $guild['splash'] ?? null;

        $this->createdTimestamp = (int) Snowflake::deconstruct($this->id)->timestamp;
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
     * Returns the guild's icon URL, or null.
     *
     * @param  int|null  $size  One of 128, 256, 512, 1024 or 2048.
     * @param  string  $format  One of png, jpg or webp.
     *
     * @return string|null
     * @throws InvalidArgumentException Thrown if $size is not a power of 2
     */
    public function getIconURL(?int $size = null, string $format = '')
    {
        if (! ImageHelpers::isPowerOfTwo($size)) {
            throw new InvalidArgumentException('Invalid size "'.$size.'", expected any powers of 2');
        }

        if ($this->icon === null) {
            return null;
        }

        if (empty($format)) {
            $format = ImageHelpers::getImageExtension($this->icon);
        }

        return APIEndpoints::CDN['url'].APIEndpoints::format(
                APIEndpoints::CDN['icons'],
                $this->id,
                $this->icon,
                $format
            ).(! empty($size) ? '?size='.$size : '');
    }

    /**
     * Returns the guild's splash URL, or null.
     *
     * @param  int|null  $size  One of 128, 256, 512, 1024 or 2048.
     * @param  string  $format  One of png, jpg or webp.
     *
     * @return string|null
     */
    public function getSplashURL(?int $size = null, string $format = 'png')
    {
        if (! ImageHelpers::isPowerOfTwo($size)) {
            throw new InvalidArgumentException('Invalid size "'.$size.'", expected any powers of 2');
        }

        if ($this->splash !== null) {
            return APIEndpoints::CDN['url'].APIEndpoints::format(
                    APIEndpoints::CDN['splashes'],
                    $this->id,
                    $this->splash,
                    $format
                ).(! empty($size) ? '?size='.$size : '');
        }

        return null;
    }

    /**
     * Automatically converts to the guild name.
     *
     * @return string
     */
    public function __toString()
    {
        return $this->name;
    }
}
