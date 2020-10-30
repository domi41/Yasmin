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
use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;
use function React\Promise\all;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#channel-update
 * @internal
 */
class ChannelUpdate implements WSEventInterface
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
        $this->clones = ! ($clones === true || in_array('channelUpdate', (array) $clones));
    }

    public function handle(WSConnection $ws, $data): void
    {
        $channel = $this->client->channels->get($data['id']);
        if ($channel instanceof ChannelInterface) {
            $oldChannel = null;
            if ($this->clones) {
                $oldChannel = clone $channel;
            }

            $channel->_patch($data);

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

            all($prom)->done(
                function () use ($channel, $oldChannel) {
                    $this->client->queuedEmit('channelUpdate', $channel, $oldChannel);
                },
                [$this->client, 'handlePromiseRejection']
            );
        }
    }
}
