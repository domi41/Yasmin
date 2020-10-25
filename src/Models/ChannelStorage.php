<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\DiscordException;
use CharlotteDunois\Yasmin\Interfaces\CategoryChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\ChannelStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\DMChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\GroupDMChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildNewsChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildStoreChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildVoiceChannelInterface;

/**
 * Channel Storage to store channels, which utilizes Collection.
 */
class ChannelStorage extends Storage implements ChannelStorageInterface
{
    /**
     * Channel Types.
     *
     * @var array
     * @source
     */
    const CHANNEL_TYPES = [
        0 => 'text',
        1 => 'dm',
        2 => 'voice',
        3 => 'group',
        4 => 'category',
        5 => 'news',
        6 => 'store',

        'text'     => 0,
        'dm'       => 1,
        'voice'    => 2,
        'group'    => 3,
        'category' => 4,
        'news'     => 5,
        'store'    => 6,
    ];

    /**
     * Resolves given data to a channel.
     *
     * @param  ChannelInterface|string|int  $channel  string/int = channel ID
     *
     * @return ChannelInterface
     * @throws \InvalidArgumentException
     */
    public function resolve($channel)
    {
        if ($channel instanceof ChannelInterface) {
            return $channel;
        }

        if (\is_int($channel)) {
            $channel = (string) $channel;
        }

        if (\is_string($channel) && parent::has($channel)) {
            return parent::get($channel);
        }

        throw new \InvalidArgumentException('Unable to resolve unknown channel');
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return bool
     */
    public function has($key)
    {
        return parent::has($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return ChannelInterface|null
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     * @param  ChannelInterface  $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        parent::set($key, $value);
        if ($this !== $this->client->channels) {
            $this->client->channels->set($key, $value);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return $this
     */
    public function delete($key)
    {
        parent::delete($key);
        if ($this !== $this->client->channels) {
            $this->client->channels->delete($key);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     * @return $this
     */
    public function clear()
    {
        if ($this !== $this->client->channels) {
            foreach ($this->data as $key => $val) {
                $this->client->channels->delete($key);
                unset($val);
            }
        }

        parent::clear();

        return $this;
    }

    /**
     * Factory to create (or retrieve existing) channels.
     *
     * @param  array  $data
     * @param  Guild|null  $guilds
     *
     * @return ChannelInterface
     * @throws DiscordException
     * @internal
     */
    public function factory(array $data, ?Guild $guild = null)
    {
        if ($guild === null) {
            $guild = (! empty($data['guild_id']) ? $this->client->guilds->get($data['guild_id']) : null);
        }

        if (parent::has($data['id'])) {
            $channel = parent::get($data['id']);
            $channel->_patch($data);

            return $channel;
        }

        switch ($data['type']) {
            default:
                throw new DiscordException('Unknown channel type');
                break;
            case 0:
                if ($guild === null) {
                    throw new DiscordException('Unknown guild for guild channel');
                }

                $channel = new TextChannel($this->client, $guild, $data);
                break;
            case 1:
                $channel = new DMChannel($this->client, $data);
                break;
            case 2:
                if ($guild === null) {
                    throw new DiscordException('Unknown guild for guild channel');
                }

                $channel = new VoiceChannel($this->client, $guild, $data);
                break;
            case 3:
                $channel = new GroupDMChannel($this->client, $data);
                break;
            case 4:
                if ($guild === null) {
                    throw new DiscordException('Unknown guild for guild channel');
                }

                $channel = new CategoryChannel($this->client, $guild, $data);
                break;
            case 6:
                if ($guild === null) {
                    throw new DiscordException('Unknown guild for guild channel');
                }

                $channel = new GuildStoreChannel($this->client, $guild, $data);
                break;
        }

        $this->set($channel->id, $channel);

        if ($guild) {
            $guild->channels->set($channel->id, $channel);
        }

        return $channel;
    }

    /**
     * Get the type for the channel.
     *
     * @param  ChannelInterface  $channel
     *
     * @return int
     */
    public static function getTypeForChannel(ChannelInterface $channel)
    {
        if ($channel instanceof GroupDMChannelInterface) {
            return self::CHANNEL_TYPES['group'];
        } elseif ($channel instanceof DMChannelInterface) {
            return self::CHANNEL_TYPES['dm'];
        } elseif ($channel instanceof GuildVoiceChannelInterface) {
            return self::CHANNEL_TYPES['voice'];
        } elseif ($channel instanceof CategoryChannelInterface) {
            return self::CHANNEL_TYPES['category'];
        } elseif ($channel instanceof GuildNewsChannelInterface) {
            return self::CHANNEL_TYPES['news'];
        } elseif ($channel instanceof GuildStoreChannelInterface) {
            return self::CHANNEL_TYPES['store'];
        }

        return self::CHANNEL_TYPES['text'];
    }
}
