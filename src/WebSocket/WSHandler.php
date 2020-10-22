<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\WebSocket;

use CharlotteDunois\Yasmin\WebSocket\Handlers\Dispatch;
use CharlotteDunois\Yasmin\WebSocket\Handlers\Heartbeat;
use CharlotteDunois\Yasmin\WebSocket\Handlers\HeartbeatAck;
use CharlotteDunois\Yasmin\WebSocket\Handlers\Hello;
use CharlotteDunois\Yasmin\WebSocket\Handlers\InvalidSession;
use CharlotteDunois\Yasmin\WebSocket\Handlers\Reconnect;

/**
 * Handles WS messages.
 *
 * @property WSManager $wsmanager
 * @internal
 */
class WSHandler
{
    /**
     * The WS manager.
     *
     * @var WSManager
     */
    protected $wsmanager;

    /**
     * The handlers for WS messages, mapped by name.
     *
     * @var \CharlotteDunois\Yasmin\Interfaces\WSHandlerInterface[]
     */
    protected $handlers = [];

    /**
     * DO NOT initialize this class yourself.
     *
     * @param  WSManager  $wsmanager
     */
    function __construct(WSManager $wsmanager)
    {
        $this->wsmanager = $wsmanager;

        $this->register(WSManager::OPCODES['DISPATCH'], Dispatch::class);
        $this->register(WSManager::OPCODES['HEARTBEAT'], Heartbeat::class);
        $this->register(WSManager::OPCODES['RECONNECT'], Reconnect::class);
        $this->register(
            WSManager::OPCODES['INVALID_SESSION'],
            InvalidSession::class
        );
        $this->register(WSManager::OPCODES['HELLO'], Hello::class);
        $this->register(
            WSManager::OPCODES['HEARTBEAT_ACK'],
            HeartbeatAck::class
        );
    }

    function __get($name)
    {
        if (\property_exists($this, $name)) {
            return $this->$name;
        }

        throw new \RuntimeException('Undefined property: '.\get_class($this).'::$'.$name);
    }

    /**
     * Returns a WS handler.
     *
     * @return \CharlotteDunois\Yasmin\Interfaces\WSHandlerInterface
     */
    function getHandler(int $name)
    {
        if (isset($this->handlers[$name])) {
            return $this->handlers[$name];
        }

        throw new \Exception('Unable to find handler');
    }

    /**
     * Handles a message.
     *
     * @return void
     */
    function handle(WSConnection $ws, $message)
    {
        $packet = $this->wsmanager->encoding->decode($message);
        $this->wsmanager->client->emit('raw', $packet);

        if (isset($packet['s'])) {
            $ws->setSequence($packet['s']);
        }

        $this->wsmanager->emit('debug', 'Shard '.$ws->shardID.' received WS packet with OP code '.$packet['op']);

        if (isset($this->handlers[$packet['op']])) {
            $this->handlers[$packet['op']]->handle($ws, $packet);
        }
    }

    /**
     * Registers a handler.
     *
     * @return void
     * @throws \RuntimeException
     */
    function register(int $op, string $class)
    {
        if (! \in_array('CharlotteDunois\Yasmin\Interfaces\WSHandlerInterface', \class_implements($class))) {
            throw new \RuntimeException('Specified handler class does not implement interface');
        }

        $this->handlers[$op] = new $class($this);
    }
}
