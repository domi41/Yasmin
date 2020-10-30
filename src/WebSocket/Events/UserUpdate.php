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

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#user-update
 * @internal
 */
class UserUpdate implements WSEventInterface
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
        $this->clones = ! ($clones === true || in_array('userUpdate', (array) $clones));
    }

    public function handle(WSConnection $ws, $data): void
    {
        $user = $this->client->users->get($data['id']);
        if ($user) {
            $oldUser = null;
            if ($this->clones) {
                $oldUser = clone $user;
            }

            $user->_patch($data);

            $this->client->queuedEmit('userUpdate', $user, $oldUser);
        }
    }
}
