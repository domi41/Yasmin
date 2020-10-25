<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\WebSocket\Handlers;

use function ceil;
use CharlotteDunois\Yasmin\Interfaces\WSHandlerInterface;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSHandler;
use function time;

/**
 * WS Event handler.
 *
 * @internal
 */
class Hello implements WSHandlerInterface
{
    /**
     * @var WSHandler
     */
    protected $wshandler;

    public function __construct(WSHandler $wshandler)
    {
        $this->wshandler = $wshandler;
    }

    public function handle(WSConnection $ws, $packet): void
    {
        $this->wshandler->wsmanager->client->emit('debug', 'Shard '.$ws->shardID.' connected to Gateway');

        $this->wshandler->wsmanager->setLastIdentified(time());
        $ws->sendIdentify();

        $interval = $packet['d']['heartbeat_interval'] / 1000;
        $ws->ratelimits['heartbeatRoom'] = (int) ceil($ws->ratelimits['total'] / $interval);

        $ws->heartbeat = $this->wshandler->wsmanager->client->loop->addPeriodicTimer(
            $interval,
            function () use (&$ws) {
                $ws->heartbeat();
            }
        );
    }
}
