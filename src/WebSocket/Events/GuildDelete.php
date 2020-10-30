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
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#guild-delete
 * @internal
 */
class GuildDelete implements WSEventInterface
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
        $guild = $this->client->guilds->get($data['id']);
        if ($guild) {
            foreach ($guild->channels as $channel) {
                if ($channel instanceof TextChannelInterface) {
                    $channel->stopTyping(true);
                }
            }

            if (! empty($data['unavailable'])) {
                $guild->_patch(['unavailable' => true]);
                $this->client->queuedEmit('guildUnavailable', $guild);
            } else {
                foreach ($guild->channels as $channel) {
                    $this->client->channels->delete($channel->getId());
                }

                foreach ($guild->emojis as $emoji) {
                    $this->client->emojis->delete($emoji->id);
                }

                $this->client->guilds->delete($guild->id);
                $this->client->queuedEmit('guildDelete', $guild);
            }
        }
    }
}
