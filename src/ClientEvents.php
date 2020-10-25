<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Models\Guild;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use CharlotteDunois\Yasmin\Models\Presence;
use CharlotteDunois\Yasmin\Models\Role;
use CharlotteDunois\Yasmin\Models\Shard;
use CharlotteDunois\Yasmin\Models\User;

/**
 * Documents all Client events. ($client->on('name here', callable)).
 *
 * The second parameter of *Update events is null, if cloning for that event is disabled.
 */
interface ClientEvents
{
    /**
     * Emitted each time the client turns ready.
     *
     * @return void
     */
    public function ready();

    /**
     * Emitted when the shard gets disconnected from the gateway.
     *
     * @return void
     */
    public function disconnect(Shard $shard, int $code, string $reason);

    /**
     * Emitted when the shard tries to reconnect.
     *
     * @return void
     */
    public function reconnect(Shard $shard);

    /**
     * Emitted when we receive a message from the gateway.
     *
     * @param  mixed  $message
     *
     * @return void
     */
    public function raw($message);

    /**
     * Emitted when an uncached message gets deleted.
     *
     * @return void
     */
    public function messageDeleteRaw(TextChannelInterface $channel, string $messageID);

    /**
     * Emitted when multple uncached messages gets deleted.
     *
     * @return void
     */
    public function messageDeleteBulkRaw(TextChannelInterface $channel, array $messageIDs);

    /**
     * Emitted when an uncached message gets updated (does not mean the message got edited, check the edited timestamp for that).
     *
     * @return void
     * @see https://discordapp.com/developers/docs/topics/gateway#message-update
     * @see https://discordapp.com/developers/docs/resources/channel#message-object
     */
    public function messageUpdateRaw(TextChannelInterface $channel, array $data);

    /**
     * Emitted when an error happens (inside the library or any listeners). You should always listen on this event.
     * Failing to listen on this event will result in an exception when an error event gets emitted.
     *
     * @return void
     */
    public function error(\Throwable $error);

    /**
     * Debug messages.
     *
     * @param  string|mixed  $message
     *
     * @return void
     */
    public function debug($message);

    /**
     * Ratelimit information.
     *
     * The array has the following format:
     * ```
     * array(
     *     'endpoint' => string,
     *     'global' => bool,
     *     'limit' => int|float, (float = \INF)
     *     'remaining => int,
     *     'resetTime' => float|null
     * )
     * ```
     *
     * @return void
     */
    public function ratelimit(array $data);

    /**
     * Emitted when a channel gets created.
     *
     * @return void
     */
    public function channelCreate(ChannelInterface $channel);

    /**
     * Emitted when a channel gets updated.
     *
     * @return void
     */
    public function channelUpdate(ChannelInterface $new, ?ChannelInterface $old);

    /**
     * Emitted when a channel gets deleted.
     *
     * @return void
     */
    public function channelDelete(ChannelInterface $channel);

    /**
     * Emitted when a channel's pins gets updated. Due to the nature of the event, it's not possible to do much.
     *
     * @return void
     */
    public function channelPinsUpdate(ChannelInterface $channel, ?\DateTime $time);

    /**
     * Emitted when a guild gets joined.
     *
     * @return void
     */
    public function guildCreate(Guild $guild);

    /**
     * Emitted when a guild gets updated.
     *
     * @return void
     */
    public function guildUpdate(Guild $new, ?Guild $old);

    /**
     * Emitted when a guild gets left.
     *
     * @return void
     */
    public function guildDelete(Guild $guild);

    /**
     * Emitted when a guild becomes (un)available.
     *
     * @return void
     */
    public function guildUnavailable(Guild $guild);

    /**
     * Emitted when someone gets banned.
     *
     * @return void
     */
    public function guildBanAdd(Guild $guild, User $user);

    /**
     * Emitted when someone gets unbanned.
     *
     * @return void
     */
    public function guildBanRemove(Guild $guild, User $user);

    /**
     * Emitted when an user joins a guild.
     *
     * @return void
     */
    public function guildMemberAdd(GuildMember $member);

    /**
     * Emitted when a member gets updated.
     *
     * @return void
     */
    public function guildMemberUpdate(GuildMember $new, ?GuildMember $old);

    /**
     * Emitted when an user leaves a guild.
     *
     * @return void
     */
    public function guildMemberRemove(GuildMember $member);

    /**
     * Emitted when the gateway sends requested members. The collection consists of GuildMember instances, mapped by their user ID.
     *
     * @return void
     * @see \CharlotteDunois\Yasmin\Models\GuildMember
     */
    public function guildMembersChunk(Guild $guild, Collection $members);

    /**
     * Emitted when a role gets created.
     *
     * @return void
     */
    public function roleCreate(Role $role);

    /**
     * Emitted when a role gets updated.
     *
     * @return void
     */
    public function roleUpdate(Role $new, ?Role $old);

    /**
     * Emitted when a role gets deleted.
     *
     * @return void
     */
    public function roleDelete(Role $role);

    /**
     * Emitted when a message gets received.
     *
     * @return void
     */
    public function message(Message $message);

    /**
     * Emitted when a (cached) message gets updated (does not mean the message got edited, check the edited timestamp for that).
     *
     * @return void
     */
    public function messageUpdate(Message $new, ?Message $old);

    /**
     * Emitted when a (cached) message gets deleted.
     *
     * @return void
     */
    public function messageDelete(Message $message);

    /**
     * Emitted when multiple (cached) message gets deleted. The collection consists of Message instances, mapped by their ID.
     *
     * @return void
     * @see \CharlotteDunois\Yasmin\Models\Message
     */
    public function messageDeleteBulk(Collection $messages);

    /**
     * Emitted when someone reacts to a (cached) message.
     *
     * @return void
     */
    public function messageReactionAdd(MessageReaction $reaction, User $user);

    /**
     * Emitted when a reaction from a (cached) message gets removed.
     *
     * @return void
     */
    public function messageReactionRemove(MessageReaction $reaction, User $user);

    /**
     * Emitted when all reactions from a (cached) message gets removed.
     *
     * @return void
     */
    public function messageReactionRemoveAll(Message $message);

    /**
     * Emitted when a presence updates.
     *
     * @return void
     */
    public function presenceUpdate(Presence $new, ?Presence $old);

    /**
     * Emitted when someone starts typing in the channel.
     *
     * @return void
     */
    public function typingStart(TextChannelInterface $channel, User $user);

    /**
     * Emitted when someone stops typing in the channel.
     *
     * @return void
     */
    public function typingStop(TextChannelInterface $channel, User $user);

    /**
     * Emitted when someone updates their user account (username/avatar/etc.).
     *
     * @return void
     */
    public function userUpdate(User $new, ?User $old);

    /**
     * Emitted when Discord responds to the user's Voice State Update event.
     * If you get `null` for `$data`, then this means that there's no endpoint yet and need to await it = Awaiting Endpoint.
     *
     * @return void
     * @see https://discordapp.com/developers/docs/topics/gateway#voice-server-update
     */
    public function voiceServerUpdate(?array $data);

    /**
     * Emitted when a member's voice state changes (leaves/joins/etc.).
     *
     * @return void
     */
    public function voiceStateUpdate(GuildMember $new, ?GuildMember $old);
}
