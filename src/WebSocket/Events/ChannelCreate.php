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
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#channel-create
 * @internal
 */
class ChannelCreate implements WSEventInterface
{
    /**
     * The client.
     *
     * @var Client
     */
    protected $client;

    public function __construct(Client $client, WSManager $wsmanager)
    {
        $this->client = $client;
    }

    public function handle(WSConnection $ws, $data): void
    {
        $channel = $this->client->channels->factory($data);

        $prom = [];
        if ($channel instanceof GuildChannelInterface) {
            foreach ($channel->getPermissionOverwrites() as $overwrite) {
                if ($overwrite->type === 'member' && $overwrite->target === null) {
                    $prom[] = $channel->getGuild()->fetchMember($overwrite->id)->then(
                        function (GuildMember $member) use ($overwrite) {
                            $overwrite->_patch(['target' => $member]);
                        },
                        function () {
                            // Do nothing
                        }
                    );
                }
            }
        }

        \React\Promise\all($prom)->done(
            function () use ($channel) {
                $this->client->queuedEmit('channelCreate', $channel);
            },
            [$this->client, 'handlePromiseRejection']
        );
    }
}
