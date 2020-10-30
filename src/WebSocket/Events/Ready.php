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
use CharlotteDunois\Yasmin\Models\Guild;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#ready
 * @internal
 */
class Ready implements WSEventInterface
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
        if (empty($data['user']['bot'])) {
            $ws->emit('self.error', 'User accounts are not supported');

            return;
        }

        $ws->setAuthenticated(true);
        $ws->setSessionID($data['session_id']);

        $ws->emit('self.ready');

        if ($this->ready && $this->client->user !== null) {
            $this->client->user->_patch($data['user']);
            $this->client->wsmanager()->emit('ready');

            return;
        }

        if ($this->client->user === null) {
            $this->client->setClientUser($data['user']);
        }

        $unavailableGuilds = 0;

        foreach ($data['guilds'] as $guild) {
            if (! $this->client->guilds->has($guild['id'])) {
                $guild = new Guild($this->client, $guild);
                $this->client->guilds->set($guild->id, $guild);
            }

            $unavailableGuilds++;
        }

        // Already ready
        if ($unavailableGuilds === 0) {
            $this->client->wsmanager()->emit('self.ws.ready');

            return;
        }

        // Emit ready after waiting N guilds * 1.2 seconds - we waited long enough for Discord to get the guilds to us
        $gtime = ceil(($unavailableGuilds * 1.2));
        $timer = $this->client->addTimer(
            max(5, $gtime),
            function () use (&$unavailableGuilds) {
                if ($unavailableGuilds > 0) {
                    $this->client->wsmanager()->emit('self.ws.ready');
                }
            }
        );

        $listener = function () use ($timer, $ws, &$listener, &$unavailableGuilds) {
            $unavailableGuilds--;

            if ($unavailableGuilds <= 0) {
                $this->client->cancelTimer($timer);
                $ws->removeListener('guildCreate', $listener);

                $this->client->wsmanager()->emit('self.ws.ready');
            }
        };

        $ws->on('guildCreate', $listener);
    }
}
