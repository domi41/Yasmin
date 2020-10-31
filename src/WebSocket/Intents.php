<?php

namespace CharlotteDunois\Yasmin\WebSocket;

class Intents
{
    /**
     * All : 32767
     * Without GUILD_MEMBERS, GUILD_PRESENCES and typing : 14077 (default)
     * Without typing : 14335.
     *
     * @var array
     *
     * @see https://discord.com/developers/docs/topics/gateway#gateway-intents
     */
    protected const INTENTS = [
        'GUILDS'                   => (1 << 0),
        'GUILD_MEMBERS'            => (1 << 1),
        'GUILD_BANS'               => (1 << 2),
        'GUILD_EMOJIS'             => (1 << 3),
        'GUILD_INTEGRATIONS'       => (1 << 4),
        'GUILD_WEBHOOKS'           => (1 << 5),
        'GUILD_INVITES'            => (1 << 6),
        'GUILD_VOICE_STATES'       => (1 << 7),
        'GUILD_PRESENCES'          => (1 << 8),
        'GUILD_MESSAGES'           => (1 << 9),
        'GUILD_MESSAGE_REACTIONS'  => (1 << 10),
        'GUILD_MESSAGE_TYPING'     => (1 << 11),
        'DIRECT_MESSAGES'          => (1 << 12),
        'DIRECT_MESSAGE_REACTIONS' => (1 << 13),
        'DIRECT_MESSAGE_TYPING'    => (1 << 14),
    ];

    /**
     * @return array|int[]
     */
    public static function all(): array
    {
        return static::INTENTS;
    }

    /**
     * @return array|int[]
     */
    public static function default(): array
    {
        return static::except([
            'GUILD_MEMBERS',
            'GUILD_PRESENCES',
            'GUILD_MESSAGE_TYPING',
            'DIRECT_MESSAGE_TYPING',
        ]);
    }

    /**
     * @param  array  $only
     * @return array|int[]
     */
    public static function only(array $only): array
    {
        return array_filter(static::INTENTS, function ($key) use ($only) {
            return in_array($key, $only);
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * @param  array  $except
     * @return array|int[]
     */
    public static function except(array $except): array
    {
        return array_filter(static::INTENTS, function ($key) use ($except) {
            return ! in_array($key, $except);
        }, ARRAY_FILTER_USE_KEY);
    }
}
