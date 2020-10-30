<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\WebSocket\Compression;

use CharlotteDunois\Yasmin\Interfaces\WSCompressionInterface;
use CharlotteDunois\Yasmin\WebSocket\DiscordGatewayException;
use Exception;
use function function_exists;
use function inflate_add;
use function inflate_init;
use RuntimeException;
use const ZLIB_ENCODING_DEFLATE;

/**
 * Handles WS compression.
 *
 * @internal
 */
class ZlibStream implements WSCompressionInterface
{
    /**
     * @var resource
     */
    protected $context;

    /**
     * Checks if the system supports it.
     *
     * @return void
     * @throws Exception
     */
    public static function supported(): void
    {
        if (! function_exists('\inflate_init')) {
            throw new RuntimeException('Zlib is not supported by this PHP installation');
        }
    }

    /**
     * Returns compression name (for gateway query string).
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'zlib-stream';
    }

    /**
     * Returns a boolean for the OP code 2 IDENTIFY packet 'compress' parameter. The parameter is for payload compression.
     *
     * @return bool
     */
    public static function isPayloadCompression(): bool
    {
        return false;
    }

    /**
     * Initializes the context.
     *
     * @return void
     * @throws RuntimeException
     */
    public function init(): void
    {
        $this->context = inflate_init(ZLIB_ENCODING_DEFLATE);
        if (! $this->context) {
            throw new RuntimeException('Unable to initialize Zlib Inflate');
        }
    }

    /**
     * Destroys the context.
     *
     * @return void
     */
    public function destroy(): void
    {
        $this->context = null;
    }

    /**
     * Decompresses data.
     *
     * @param  string  $data
     *
     * @return string
     * @throws DiscordGatewayException
     */
    public function decompress(string $data): string
    {
        if (! $this->context) {
            throw new DiscordGatewayException('No inflate context initialized');
        }

        $uncompressed = inflate_add($this->context, $data);
        if ($uncompressed === false) {
            throw new DiscordGatewayException('The inflate context was unable to decompress the data');
        }

        return $uncompressed;
    }
}
