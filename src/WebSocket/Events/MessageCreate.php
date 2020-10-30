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
use CharlotteDunois\Yasmin\Interfaces\TextChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\WSEventInterface;
use CharlotteDunois\Yasmin\Models\GuildMember;
use CharlotteDunois\Yasmin\Models\TextChannel;
use CharlotteDunois\Yasmin\WebSocket\WSConnection;
use CharlotteDunois\Yasmin\WebSocket\WSManager;

use function React\Promise\all;
use function React\Promise\resolve;

/**
 * WS Event.
 *
 * @see https://discordapp.com/developers/docs/topics/gateway#message-create
 * @internal
 */
class MessageCreate implements WSEventInterface
{
    /**
     * The client.
     *
     * @var Client
     */
    protected $client;

    public function __construct(Client $client, WSManager $wsmanager)
    {
        $this->client = $client;
    }

    public function handle(WSConnection $ws, $data): void
    {
        $channel = $this->client->channels->get($data['channel_id']);
        if ($channel instanceof TextChannelInterface) {
            $user = $this->client->users->patch($data['author']);

            if (! empty($data['member']) && $channel instanceof TextChannel && ! $channel->getGuild()->members->has(
                    $user->id
                )) {
                $member = $data['member'];
                $member['user'] = ['id' => $user->id];
                $channel->getGuild()->_addMember($member, true);
            }

            $message = $channel->_createMessage($data);

            if ($message->guild && $message->mentions->users->count() > 0 && $message->mentions->users->count(
                ) > $message->mentions->members->count()) {
                $promise = [];

                foreach ($message->mentions->users as $user) {
                    $promise[] = $message->guild->fetchMember($user->id)->then(
                        function (GuildMember $member) use ($message) {
                            $message->mentions->members->set($member->id, $member);
                        },
                        function () {
                            // Ignore failure
                        }
                    );
                }

                $prm = all($promise);
            } else {
                $prm = resolve();
            }

            $prm->done(
                function () use ($message) {
                    if ($message->guild && ! ($message->member instanceof GuildMember) && ! $message->author->webhook) {
                        return $message->guild->fetchMember($message->author->id)->then(
                            null,
                            function () {
                                // Ignore failure
                            }
                        );
                    }

                    $this->client->queuedEmit('message', $message);
                },
                [$this->client, 'handlePromiseRejection']
            );
        }
    }
}
