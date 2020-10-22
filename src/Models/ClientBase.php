<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Client;
use Exception;

/**
 * Something all Models, with the need for a client, extend.
 *
 * @property Client $client  The client which initiated the instance.
 */
abstract class ClientBase extends Base
{
    /**
     * @var Client
     * @internal
     */
    protected $client;

    /**
     * The client which will be used to unserialize.
     *
     * @var Client|null
     */
    public static $serializeClient;

    /**
     * @param  Client  $client
     *
     * @internal
     */
    function __construct(Client $client)
    {
        parent::__construct();

        $this->client = $client;
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @internal
     */
    function __get($name)
    {
        switch ($name) {
            case 'client':
                return $this->client;
                break;
        }

        return parent::__get($name);
    }

    /**
     * @return mixed
     * @internal
     */
    function __debugInfo()
    {
        $vars = get_object_vars($this);
        unset($vars['client']);

        return $vars;
    }

    /**
     * @return mixed
     * @internal
     */
    function jsonSerialize()
    {
        $vars = parent::jsonSerialize();
        unset($vars['client']);

        return $vars;
    }

    /**
     * @return string
     * @internal
     */
    function serialize()
    {
        $vars = get_object_vars($this);
        unset($vars['client']);

        return serialize($vars);
    }

    /**
     * @return void
     * @throws Exception
     * @internal
     */
    function unserialize($data)
    {
        if (self::$serializeClient === null) {
            throw new Exception('Unable to unserialize a class without ClientBase::$serializeClient being set');
        }

        parent::unserialize($data);

        $this->client = self::$serializeClient;
    }
}
