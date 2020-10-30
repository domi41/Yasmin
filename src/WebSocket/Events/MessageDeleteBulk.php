<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\WebSocket\Events;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#message-delete-bulk
 * @internal
 */
class MessageDeleteBulk implements WSEventInterface
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
            $messages = new Collection();
            $messagesRaw = [];

            foreach ($data['ids'] as $id) {
                $message = $channel->getMessages()->get($id);
                if ($message instanceof Message) {
                    $channel->getMessages()->delete($message->id);
                    $messages->set($message->id, $message);
                } else {
                    $messagesRaw[] = $id;
                }
            }

            if ($messages->count() > 0) {
                $this->client->queuedEmit('messageDeleteBulk', $messages);
            }

            if (count($messagesRaw) > 0) {
                $this->client->queuedEmit('messageDeleteBulkRaw', $channel, $messagesRaw);
            }
        }
    }
}
