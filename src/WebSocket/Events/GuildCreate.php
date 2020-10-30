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
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;
use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#guild-create
 * @internal
 */
class GuildCreate implements WSEventInterface
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
        $guild = $this->client->guilds->get($data['id']);
        if ($guild) {
            if (empty($data['unavailable'])) {
                $guild->_patch($data);
            }

            if ($this->ready) {
                $this->client->queuedEmit('guildUnavailable', $guild);
            } else {
                $ws->emit('guildCreate');
            }
        } else {
            $guild = $this->client->guilds->factory($data, $ws->shardID);

            if (((bool) $this->client->getOption('fetchAllMembers', false)) && $guild->members->count(
                ) < $guild->memberCount) {
                $fetchAll = $guild->fetchMembers();
            } elseif ($guild->me === null) {
                $fetchAll = $guild->fetchMember($this->client->user->id);
            } else {
                $fetchAll = resolve();
            }

            $fetchAll->done(
                function () use ($guild, $ws) {
                    if ($this->ready) {
                        $this->client->queuedEmit('guildCreate', $guild);
                    } else {
                        $ws->emit('guildCreate');
                    }
                },
                [$this->client, 'handlePromiseRejection']
            );
        }
    }
}
