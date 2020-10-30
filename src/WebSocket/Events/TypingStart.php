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
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\TextChannel;
use CharlotteDunois\Yasmin\Models\User;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;
use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#typing-start
 * @internal
 */
class TypingStart implements WSEventInterface
{
    /**
     * The client.
     *
     * @var Client
     */
    protected $client;

    /**
     * Whether we saw the client going ready.
     *
     * @var bool
     */
    protected $ready = false;

    public function __construct(
        Client $client,
        WSManager $wsmanager
    ) {
        $this->client = $client;

        $this->client->once(
            'ready',
            function () {
                $this->ready = true;
            }
        );
    }

    public function handle(WSConnection $ws, $data): void
    {
        if (! $this->ready) {
            return;
        }

        $channel = $this->client->channels->get($data['channel_id']);
        if ($channel instanceof TextChannelInterface) {
            $user = $this->client->users->get($data['user_id']);
            if (! $user) {
                $user = $this->client->fetchUser($data['user_id']);
            } else {
                $user = resolve($user);
            }

            $user->done(
                function (User $user) use ($channel, $data) {
                    if (! empty($data['member']) && $channel instanceof TextChannel && ! $channel->getGuild(
                        )->members->has($user->id)) {
                        $member = $data['member'];
                        $member['user'] = ['id' => $user->id];
                        $channel->getGuild()->_addMember($member, true);
                    }

                    if ($channel->_updateTyping($user, $data['timestamp'])) {
                        $this->client->queuedEmit('typingStart', $channel, $user);
                    }
                },
                [$this->client, 'handlePromiseRejection']
            );
        }
    }
}
