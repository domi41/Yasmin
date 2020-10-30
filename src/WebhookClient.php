<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin;

use CharlotteDunois\Yasmin\Models\User;
use CharlotteDunois\Yasmin\Models\Webhook;
use React\EventLoop\LoopInterface;

/**
 * The webhook client.
 *
 * @property string $id         The webhook ID.
 * @property string|null $name       The webhook default name, or null.
 * @property string|null $avatar     The webhook default avatar, or null.
 * @property string|null $channelID  The channel the webhook belongs to.
 * @property string|null $guildID    The guild the webhook belongs to, or null.
 * @property User|null $owner      The owner of the webhook, or null.
 * @property string $token      The webhook token.
 */
class WebhookClient extends Webhook
{
    /**
     * Constructor.
     *
     * @param  string  $id  The webhook ID.
     * @param  string  $token  The webhook token.
     * @param  array  $options  Any Client Options.
     * @param  LoopInterface|null  $loop  The ReactPHP Event Loop.
     */
    public function __construct(string $id, string $token, array $options = [], ?LoopInterface $loop = null)
    {
        $options['internal.ws.disable'] = true;

        $client = new Client($options, $loop);

        parent::__construct(
            $client,
            [
                'id'    => $id,
                'token' => $token,
            ]
        );
    }
}
