<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\WebSocket\Events;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\User;
use CharlotteDunois\Yasmin\Models\VoiceChannel;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;
use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#voice-state-update
 * @internal
 */
class VoiceStateUpdate implements WSEventInterface
{
    /**
     * The client.
     *
     * @var Client
     */
    protected $client;

    /**
     * Whether we do clones.
     *
     * @var bool
     */
    protected $clones = false;

    public function __construct(
        Client $client,
        WSManager $wsmanager
    ) {
        $this->client = $client;

        $clones = $this->client->getOption('disableClones', []);
        $this->clones = ! ($clones === true || in_array('voiceStateUpdate', (array) $clones));
    }

    public function handle(WSConnection $ws, $data): void
    {
        $this->client->fetchUser($data['user_id'])->done(
            function (User $user) use ($data) {
                if (empty($data['channel_id'])) {
                    if (empty($data['guild_id'])) {
                        return;
                    }

                    $guild = $this->client->guilds->get($data['guild_id']);
                    if ($guild) {
                        if ($guild->members->has($data['user_id'])) {
                            $member = resolve($guild->members->get($data['user_id']));
                        } elseif (! empty($data['member'])) {
                            $member = $data['member'];
                            $member['user'] = ['id' => $user->id];

                            $member = resolve($guild->_addMember($member, true));
                        } else {
                            $member = $guild->fetchMember($user->id);
                        }

                        $member->done(
                            function (GuildMember $member) use ($data) {
                                $oldMember = null;
                                if ($this->clones) {
                                    $oldMember = clone $member;
                                }

                                if ($member->voiceChannel) {
                                    $member->voiceChannel->members->delete($member->id);
                                }

                                $member->_setVoiceState($data);
                                $this->client->queuedEmit('voiceStateUpdate', $member, $oldMember);
                            },
                            function () use ($guild, $user) {
                                foreach ($guild->channels as $channel) {
                                    if ($channel instanceof VoiceChannel) {
                                        $channel->members->delete($user->id);
                                    }
                                }
                            }
                        );
                    }
                } else {
                    $channel = $this->client->channels->get($data['channel_id']);
                    if ($channel instanceof VoiceChannel) {
                        if ($channel->getGuild() === null) {
                            return;
                        }

                        if ($channel->guild->members->has($data['user_id'])) {
                            $member = resolve($channel->guild->members->get($data['user_id']));
                        } elseif (! empty($data['member'])) {
                            $member = $data['member'];
                            $member['user'] = ['id' => $user->id];

                            $member = resolve($channel->guild->_addMember($member, true));
                        } else {
                            $member = $channel->guild->fetchMember($user->id);
                        }

                        $member->done(
                            function (GuildMember $member) use ($data, $channel) {
                                $oldMember = null;
                                if ($this->clones) {
                                    $oldMember = clone $member;
                                }

                                if ($member->voiceChannel) {
                                    $member->voiceChannel->members->delete($member->id);
                                }

                                $member->_setVoiceState($data);
                                $channel->members->set($member->id, $member);

                                $this->client->queuedEmit('voiceStateUpdate', $member, $oldMember);
                            },
                            function () use ($channel, $user) {
                                $channel->members->delete($user->id);
                            }
                        );
                    }
                }
            },
            [$this->client, 'handlePromiseRejection']
        );
    }
}
