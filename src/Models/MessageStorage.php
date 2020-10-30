<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\MessageStorageInterface;
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use React\EventLoop\Timer\TimerInterface;


/**
 * Message Storage to store and handle messages, utilizes Collection.
 */
class MessageStorage extends Storage implements MessageStorageInterface
{
    /**
     * The channel this storage belongs to.
     *
     * @var TextChannelInterface
     */
    protected $channel;

    /**
     * The sweep timer, or null.
     *
     * @var \React\EventLoop\TimerInterface|TimerInterface|null
     */
    protected $timer;

    /**
     * Whether the message cache is enabled.
     *
     * @var bool
     */
    protected $enabled;

    /**
     * @param  Client  $client
     * @param  TextChannelInterface  $channel
     * @param  array|null  $data
     *
     * @internal
     */
    public function __construct(Client $client, TextChannelInterface $channel, ?array $data = null)
    {
        parent::__construct($client, $data);
        $this->channel = $channel;

        $this->baseStorageArgs[] = $this->channel;

        $this->enabled = (bool) $this->client->getOption('messageCache', true);
        if ($this->enabled) {
            $time = (int) $this->client->getOption('messageCacheLifetime', 0);
            $inv = (int) $this->client->getOption('messageSweepInterval', $time);

            if ($inv > 0) {
                $this->timer = $this->client->addPeriodicTimer(
                    $inv,
                    function () use ($time) {
                        $this->sweep($time);
                    }
                );
            }
        }
    }

    /**
     * @internal
     */
    public function __destruct()
    {
        if ($this->timer) {
            $this->client->cancelTimer($this->timer);
        }
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return bool
     */
    public function has($key)
    {
        return parent::has($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return Message|null
     */
    public function get($key)
    {
        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     * @param  Message  $value
     *
     * @return $this
     */
    public function set($key, $value)
    {
        if (! $this->enabled) {
            return $this;
        }

        parent::set($key, $value);

        return $this;
    }

    /**
     * {@inheritdoc}
     * @param  string  $key
     *
     * @return $this
     */
    public function delete($key)
    {
        parent::delete($key);

        return $this;
    }

    /**
     * Sweeps messages, deletes messages older than the parameter (timestamp - $time). Returns the amount of sweeped messages.
     *
     * @param  int  $time  0 = clear all
     *
     * @return int
     */
    public function sweep(int $time)
    {
        if ($time <= 0) {
            $this->clear();

            return;
        }

        $amount = 0;
        foreach ($this->data as $key => $msg) {
            if ($msg->createdTimestamp > (time() - $time)) {
                $this->delete($msg->id);
                unset($msg);

                $amount++;
            }
        }

        return $amount;
    }
}
