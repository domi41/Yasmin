<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\StorageInterface;
use Exception;
use InvalidArgumentException;
use RuntimeException;

/**
 * Base class for all storages.
 */
class Storage extends Collection implements StorageInterface
{
    /**
     * The client this storage belongs to.
     *
     * @var Client
     */
    protected $client;

    /**
     * Basic storage args.
     *
     * @var array
     */
    protected $baseStorageArgs;

    /**
     * @param  Client  $client
     * @param  array|null  $data
     *
     * @internal
     */
    function __construct(Client $client, array $data = null)
    {
        parent::__construct($data);
        $this->client = $client;

        $this->baseStorageArgs = [$this->client];
    }

    /**
     * @param  string  $name
     *
     * @return bool
     * @throws Exception
     * @internal
     */
    function __isset($name)
    {
        try {
            return $this->$name !== null;
        } catch (RuntimeException $e) {
            if ($e->getTrace()[0]['function'] === '__get') {
                return false;
            }

            throw $e;
        }
    }

    /**
     * @param  string  $name
     *
     * @return string
     * @throws RuntimeException
     * @internal
     */
    function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        throw new RuntimeException('Unknown property '.get_class($this).'::$'.$name);
    }

    /**
     * {@inheritdoc}
     * @return bool
     * @throws InvalidArgumentException
     */
    function has($key)
    {
        if (is_array($key) || is_object($key)) {
            throw new InvalidArgumentException('Key can not be an array or object');
        }

        $key = (string) $key;

        return parent::has($key);
    }

    /**
     * {@inheritdoc}
     * @return mixed|null
     * @throws InvalidArgumentException
     */
    function get($key)
    {
        if (is_array($key) || is_object($key)) {
            throw new InvalidArgumentException('Key can not be an array or object');
        }

        $key = (string) $key;

        return parent::get($key);
    }

    /**
     * {@inheritdoc}
     * @return $this
     * @throws InvalidArgumentException
     */
    function set($key, $value)
    {
        if (is_array($key) || is_object($key)) {
            throw new InvalidArgumentException('Key can not be an array or object');
        }

        $key = (string) $key;

        return parent::set($key, $value);
    }

    /**
     * {@inheritdoc}
     * @return $this
     * @throws InvalidArgumentException
     */
    function delete($key)
    {
        if (is_array($key) || is_object($key)) {
            throw new InvalidArgumentException('Key can not be an array or object');
        }

        $key = (string) $key;

        return parent::delete($key);
    }

    /**
     * {@inheritdoc}
     * @return StorageInterface
     */
    function copy()
    {
        $args = $this->baseStorageArgs;
        $args[] = $this->data;

        return (new static(...$args));
    }

    /**
     * {@inheritdoc}
     * @param  callable  $closure
     *
     * @return StorageInterface
     */
    function filter(callable $closure)
    {
        $args = $this->baseStorageArgs;
        $args[] = parent::filter($closure)->all();

        return (new static(...$args));
    }

    /**
     * {@inheritdoc}
     * @param  bool  $descending
     * @param  int  $options
     *
     * @return Collection
     */
    function sort(bool $descending = false, int $options = SORT_REGULAR)
    {
        $args = $this->baseStorageArgs;
        $args[] = parent::sort($descending, $options)->all();

        return (new static(...$args));
    }

    /**
     * {@inheritdoc}
     * @param  bool  $descending
     * @param  int  $options
     *
     * @return Collection
     */
    function sortKey(bool $descending = false, int $options = SORT_REGULAR)
    {
        $args = $this->baseStorageArgs;
        $args[] = parent::sortKey($descending, $options)->all();

        return (new static(...$args));
    }

    /**
     * {@inheritdoc}
     * @param  callable  $closure  Callback specification: `function ($a, $b): int`
     *
     * @return Collection
     */
    function sortCustom(callable $closure)
    {
        $args = $this->baseStorageArgs;
        $args[] = parent::sortCustom($closure)->all();

        return (new static(...$args));
    }

    /**
     * {@inheritDoc}
     * @param  callable  $closure  Callback specification: `function ($a, $b): int`
     *
     * @return Collection
     */
    function sortCustomKey(callable $closure)
    {
        $args = $this->baseStorageArgs;
        $args[] = parent::sortCustomKey($closure)->all();

        return (new static(...$args));
    }
}
