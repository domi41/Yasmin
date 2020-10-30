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
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#message-update
 * @internal
 */
class MessageUpdate implements WSEventInterface
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

    public function __construct(Client $client, WSManager $wsmanager)
    {
        $this->client = $client;

        $clones = $this->client->getOption('disableClones', []);
        $this->clones = ! ($clones === true || in_array('messageUpdate', (array) $clones));
    }

    public function handle(WSConnection $ws, $data): void
    {
        $channel = $this->client->channels->get($data['channel_id']);
        if ($channel instanceof TextChannelInterface) {
            $message = $channel->getMessages()->get($data['id']);
            if ($message instanceof Message) {
                $oldMessage = null;
                if ($this->clones) {
                    $oldMessage = clone $message;
                }

                $message->_patch($data);

                $this->client->queuedEmit('messageUpdate', $message, $oldMessage);
            } else {
                $this->client->queuedEmit('messageUpdateRaw', $channel, $data);
            }
        }
    }
}
