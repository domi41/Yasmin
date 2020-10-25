<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Interfaces;

use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSHandler;

/**
 * WS Handler interface.
 *
 * @internal
 */
interface WSHandlerInterface
{
    /**
     * Constructor.
     */
    public function __construct(WSHandler $wshandler);

    /**
     * Handles packets.
     *
     * @return void
     */
    public function handle(WSConnection $ws, $packet): void;
}
