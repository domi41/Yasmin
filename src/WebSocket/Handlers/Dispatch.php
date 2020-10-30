<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\WebSocket\Handlers;

use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Interfaces\WSHandlerInterface;
use CharlotteDunois\Yasmin\WebSocket\Events\ChannelCreate;
use CharlotteDunois\Yasmin\WebSocket\Events\ChannelDelete;
use CharlotteDunois\Yasmin\WebSocket\Events\ChannelPinsUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\ChannelUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildBanAdd;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildBanRemove;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildCreate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildDelete;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildEmojisUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildIntegrationsUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildMemberAdd;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildMemberRemove;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildMembersChunk;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildMemberUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildRoleCreate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildRoleDelete;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildRoleUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\GuildUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageCreate;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageDelete;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageDeleteBulk;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageReactionAdd;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageReactionRemove;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageReactionRemoveAll;
use CharlotteDunois\Yasmin\WebSocket\Events\MessageUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\PresenceUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\Ready;
use CharlotteDunois\Yasmin\WebSocket\Events\Resumed;
use CharlotteDunois\Yasmin\WebSocket\Events\TypingStart;
use CharlotteDunois\Yasmin\WebSocket\Events\UserUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\VoiceServerUpdate;
use CharlotteDunois\Yasmin\WebSocket\Events\VoiceStateUpdate;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSHandler;
use Exception;
use RuntimeException;

/**
 * WS Event handler.
 *
 * @internal
 */
class Dispatch implements WSHandlerInterface
{
    private $wsevents = [];
    protected $wshandler;

    public function __construct(WSHandler $wshandler)
    {
        $this->wshandler = $wshandler;

        $allEvents = [
            'RESUMED'                     => Resumed::class,
            'READY'                       => Ready::class,
            'CHANNEL_CREATE'              => ChannelCreate::class,
            'CHANNEL_UPDATE'              => ChannelUpdate::class,
            'CHANNEL_DELETE'              => ChannelDelete::class,
            'CHANNEL_PINS_UPDATE'         => ChannelPinsUpdate::class,
            'GUILD_CREATE'                => GuildCreate::class,
            'GUILD_UPDATE'                => GuildUpdate::class,
            'GUILD_DELETE'                => GuildDelete::class,
            'GUILD_BAN_ADD'               => GuildBanAdd::class,
            'GUILD_BAN_REMOVE'            => GuildBanRemove::class,
            'GUILD_EMOJIS_UPDATE'         => GuildEmojisUpdate::class,
            'GUILD_INTEGRATIONS_UPDATE'   => GuildIntegrationsUpdate::class,
            'GUILD_MEMBER_ADD'            => GuildMemberAdd::class,
            'GUILD_MEMBER_UPDATE'         => GuildMemberUpdate::class,
            'GUILD_MEMBER_REMOVE'         => GuildMemberRemove::class,
            'GUILD_MEMBERS_CHUNK'         => GuildMembersChunk::class,
            'GUILD_ROLE_CREATE'           => GuildRoleCreate::class,
            'GUILD_ROLE_UPDATE'           => GuildRoleUpdate::class,
            'GUILD_ROLE_DELETE'           => GuildRoleDelete::class,
            'MESSAGE_CREATE'              => MessageCreate::class,
            'MESSAGE_UPDATE'              => MessageUpdate::class,
            'MESSAGE_DELETE'              => MessageDelete::class,
            'MESSAGE_DELETE_BULK'         => MessageDeleteBulk::class,
            'MESSAGE_REACTION_ADD'        => MessageReactionAdd::class,
            'MESSAGE_REACTION_REMOVE'     => MessageReactionRemove::class,
            'MESSAGE_REACTION_REMOVE_ALL' => MessageReactionRemoveAll::class,
            'PRESENCE_UPDATE'             => PresenceUpdate::class,
            'TYPING_START'                => TypingStart::class,
            'USER_UPDATE'                 => UserUpdate::class,
            'VOICE_STATE_UPDATE'          => VoiceStateUpdate::class,
            'VOICE_SERVER_UPDATE'         => VoiceServerUpdate::class,
        ];

        $events = array_diff_key(
            $allEvents,
            array_flip(
                (array) $this->wshandler->wsmanager->client->getOption('ws.disabledEvents', [])
            )
        );
        foreach ($events as $name => $class) {
            $this->register($name, $class);
        }
    }

    /**
     * Returns a WS event.
     *
     * @return WSEventInterface
     */
    public function getEvent(string $name)
    {
        if (isset($this->wsevents[$name])) {
            return $this->wsevents[$name];
        }

        throw new Exception('Unable to find WS event');
    }

    public function handle(WSConnection $ws, $packet): void
    {
        if (isset($this->wsevents[$packet['t']])) {
            $this->wshandler->wsmanager->emit('debug', 'Shard '.$ws->shardID.' handling WS event '.$packet['t']);
            $this->wsevents[$packet['t']]->handle($ws, $packet['d']);
        } else {
            $this->wshandler->wsmanager->emit('debug', 'Shard '.$ws->shardID.' received WS event '.$packet['t']);
        }
    }

    /**
     * Registers an event.
     *
     * @return void
     * @throws RuntimeException
     */
    public function register(string $name, string $class)
    {
        if (! in_array('CharlotteDunois\Yasmin\Interfaces\WSEventInterface', class_implements($class))) {
            throw new RuntimeException('Specified event class does not implement interface');
        }

        $this->wsevents[$name] = new $class($this->wshandler->wsmanager->client, $this->wshandler->wsmanager);
    }
}
