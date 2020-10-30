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
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\User;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#message-reaction-add
 * @internal
 */
class MessageReactionAdd implements WSEventInterface
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
            $message = $channel->getMessages()->get($data['message_id']);
            $reaction = null;

            if ($message) {
                $reaction = $message->_addReaction($data);
                $message = resolve($message);
            } else {
                $message = $channel->fetchMessage($data['message_id']);
            }

            $message->done(
                function (Message $message) use ($data, $reaction) {
                    if ($reaction === null) {
                        $id = (! empty($data['emoji']['id']) ? ((string) $data['emoji']['id']) : $data['emoji']['name']);
                        $reaction = $message->reactions->get($id);
                    }

                    $this->client->fetchUser($data['user_id'])->done(
                        function (User $user) use ($reaction) {
                            $reaction->users->set($user->id, $user);
                            $this->client->queuedEmit('messageReactionAdd', $reaction, $user);
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
