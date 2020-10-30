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
use CharlotteDunois\Yasmin\Models\User;

use CharlotteDunois\Yasmin\WebSocket\WSConnection;

use CharlotteDunois\Yasmin\WebSocket\WSManager;

use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#presence-update
 * @internal
 */
class PresenceUpdate implements WSEventInterface
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

    /**
     * Whether we ignore events from unknown users.
     *
     * @var bool
     */
    protected $ignoreUnknown = false;

    public function __construct(
        Client $client,
        WSManager $wsmanager
    ) {
        $this->client = $client;

        $clones = $this->client->getOption('disableClones', []);
        $this->clones = ! ($clones === true || in_array('presenceUpdate', (array) $clones));
        $this->ignoreUnknown = (bool) $this->client->getOption('ws.presenceUpdate.ignoreUnknownUsers', false);
    }

    public function handle(WSConnection $ws, $data): void
    {
        $user = $this->client->users->get($data['user']['id']);

        if (($data['status'] ?? null) === 'offline' && $user === null) {
            return;
        }

        if ($user === null) {
            if ($this->ignoreUnknown) {
                return;
            }

            $user = $this->client->fetchUser($data['user']['id']);
        } else {
            if (count($data['user']) > 1 && $user->_shouldUpdate($data['user'])) {
                $oldUser = null;
                if ($this->clones) {
                    $oldUser = clone $user;
                }

                $user->_patch($data['user']);

                $this->client->queuedEmit('userUpdate', $user, $oldUser);

                return;
            }

            $user = resolve($user);
        }

        $user->done(
            function (User $user) use ($data) {
                $guild = $this->client->guilds->get($data['guild_id']);
                if ($guild) {
                    $presence = $guild->presences->get($user->id);
                    $oldPresence = null;

                    if ($presence) {
                        if ($data['status'] === 'offline' && $presence->status === 'offline') {
                            return;
                        }

                        if ($this->clones) {
                            $oldPresence = clone $presence;
                        }

                        $presence->_patch($data);
                    } else {
                        $presence = $guild->presences->factory($data);
                    }

                    $this->client->queuedEmit('presenceUpdate', $presence, $oldPresence);
                }
            },
            [$this->client, 'handlePromiseRejection']
        );
    }
}
