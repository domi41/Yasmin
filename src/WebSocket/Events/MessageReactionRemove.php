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
use CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\Emoji;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageReaction;
use CharlotteDunois\Yasmin\Models\User;

use CharlotteDunois\Yasmin\WebSocket\WSConnection;

use CharlotteDunois\Yasmin\WebSocket\WSManager;

use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#message-reaction-remove
 * @internal
 */
class MessageReactionRemove implements WSEventInterface
{
    /**
     * The client.
     *
     * @var Client
     */
    protected $client;

    public function __construct(
        Client $client,
        WSManager $wsmanager
    ) {
        $this->client = $client;
    }

    public function handle(WSConnection $ws, $data): void
    {
        $channel = $this->client->channels->get($data['channel_id']);
        if ($channel instanceof TextChannelInterface) {
            $id = (! empty($data['emoji']['id']) ? ((string) $data['emoji']['id']) : $data['emoji']['name']);

            $message = $channel->getMessages()->get($data['message_id']);
            $reaction = null;

            if ($message) {
                $reaction = $message->reactions->get($id);
                if ($reaction !== null) {
                    $reaction->_decrementCount();

                    if ($reaction->users->has($data['user_id'])) {
                        $reaction->_patch(['me' => false]);
                    }
                }

                $message = resolve($message);
            } else {
                $message = $channel->fetchMessage($data['message_id']);
            }

            $message->done(
                function (Message $message) use ($data, $channel, $id, $reaction) {
                    if (! $reaction) {
                        $reaction = $message->reactions->get($id);
                        if (! $reaction) {
                            $emoji = $this->client->emojis->get($id);
                            if (! $emoji) {
                                $guild = ($channel instanceof GuildChannelInterface ? $channel->getGuild(
                                ) : null);

                                $emoji = new Emoji(
                                    $this->client, $guild, $data['emoji']
                                );
                                if ($channel instanceof GuildChannelInterface) {
                                    $channel->guild->emojis->set($id, $emoji);
                                }

                                $this->client->emojis->set($id, $emoji);
                            }

                            $reaction = new MessageReaction(
                                $this->client, $message, $emoji, [
                                'count' => 0,
                                'me'    => false,
                                'emoji' => $emoji,
                            ]
                            );
                        }
                    }

                    $this->client->fetchUser($data['user_id'])->done(
                        function (User $user) use ($id, $message, $reaction) {
                            $reaction->users->delete($user->id);
                            if ($reaction->count === 0) {
                                $message->reactions->delete($id);
                            }

                            $this->client->queuedEmit('messageReactionRemove', $reaction, $user);
                        },
                        [$this->client, 'handlePromiseRejection']
                    );
                },
                function () {
                    // Don't handle it
                }
            );
        }
    }
}
