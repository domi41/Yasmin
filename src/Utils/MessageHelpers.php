<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Utils;

use CharlotteDunois\Yasmin\Client;
use CharlotteDunois\Yasmin\Interfaces\ChannelInterface;
use CharlotteDunois\Yasmin\Interfaces\GuildChannelInterface;
use CharlotteDunois\Yasmin\Models\Message;
use CharlotteDunois\Yasmin\Models\MessageAttachment;
use CharlotteDunois\Yasmin\Models\Role;
use CharlotteDunois\Yasmin\Models\User;
use InvalidArgumentException;
use function React\Promise\all;
use React\Promise\ExtendedPromiseInterface;
use React\Promise\Promise;
use function React\Promise\resolve;

/**
 * Message Helper methods.
 */
class MessageHelpers
{
    /**
     * Cleans the text from mentions, by providing a context message.
     *
     * @param  Message  $message
     * @param  string  $text
     *
     * @return string
     */
    public static function cleanContent(Message $message, string $text)
    {
        /** @var ChannelInterface $channel */
        foreach ($message->mentions->channels as $channel) {
            $text = str_replace('<#'.$channel->getId().'>', '#'.$channel->name, $text);
        }

        /** @var Role $role */
        foreach ($message->mentions->roles as $role) {
            $text = str_replace($role->__toString(), $role->name, $text);
        }

        /** @var User $user */
        foreach ($message->mentions->users as $user) {
            $guildCheck = ($message->channel instanceof GuildChannelInterface && $message->channel->getGuild(
                )->members->has($user->id));
            $text = preg_replace(
                '/<@!?'.$user->id.'>/',
                ($guildCheck ? $message->channel->getGuild()->members->get($user->id)->displayName : $user->username),
                $text
            );
        }

        return $text;
    }

    /**
     * Escapes any Discord-flavour markdown in a string.
     *
     * @param  string  $text  Content to escape.
     * @param  bool  $onlyCodeBlock  Whether to only escape codeblocks (takes priority).
     * @param  bool  $onlyInlineCode  Whether to only escape inline code.
     *
     * @return string
     */
    public static function escapeMarkdown(string $text, bool $onlyCodeBlock = false, bool $onlyInlineCode = false)
    {
        if ($onlyCodeBlock) {
            return preg_replace('/(```)/miu', '\\`\\`\\`', $text);
        }

        if ($onlyInlineCode) {
            return preg_replace('/(`)/miu', '\\\\$1', $text);
        }

        return preg_replace('/(\\*|_|`|~)/miu', '\\\\$1', $text);
    }

    /**
     * Parses mentions in a text. Resolves with an array of `[ 'type' => string, 'ref' => Models ]` arrays, in the order they were parsed.
     * For mentions not available to this method or the client (e.g. mentioning a channel with no access to), `ref` will be the parsed mention (string).
     * Includes everyone and here mentions.
     *
     * @param  Client|null  $client
     * @param  string  $content
     *
     * @return ExtendedPromiseInterface
     */
    public static function parseMentions(?Client $client, string $content)
    {
        return new Promise(
            function (callable $resolve) use ($client, $content) {
                $bucket = [];
                $promises = [];

                preg_match_all('/(?:<(@&|@!?|#|a?:.+?:)(\d+)>)|@everyone|@here/su', $content, $matches);
                foreach ($matches[0] as $key => $val) {
                    if ($val === '@everyone') {
                        $bucket[$key] = ['type' => 'everyone', 'ref' => $val];
                    } elseif ($val === '@here') {
                        $bucket[$key] = ['type' => 'here', 'ref' => $val];
                    } elseif ($matches[1][$key] === '@&') {
                        $type = 'role';

                        if ($client) {
                            $role = $val;

                            foreach ($client->guilds as $guild) {
                                if ($guild->roles->has($matches[2][$key])) {
                                    $role = $guild->roles->get($matches[2][$key]);
                                    break 1;
                                }
                            }

                            $bucket[$key] = ['type' => $type, 'ref' => $role];
                        } else {
                            $bucket[$key] = ['type' => $type, 'ref' => $val];
                        }
                    } elseif ($matches[1][$key] === '#') {
                        $type = 'channel';

                        if ($client) {
                            if ($client->channels->has($matches[2][$key])) {
                                $channel = $client->channels->get($matches[2][$key]);
                            } else {
                                $channel = $val;
                            }

                            $bucket[$key] = ['type' => $type, 'ref' => $channel];
                        } else {
                            $bucket[$key] = ['type' => $type, 'ref' => $val];
                        }
                    } elseif (substr_count($matches[1][$key], ':') === 2) {
                        $type = 'emoji';

                        if ($client) {
                            $emoji = $val;

                            foreach ($client->guilds as $guild) {
                                if ($guild->emojis->has($matches[2][$key])) {
                                    $emoji = $guild->emojis->get($matches[2][$key]);
                                    break 1;
                                }
                            }

                            $bucket[$key] = ['type' => $type, 'ref' => $emoji];
                        } else {
                            $bucket[$key] = ['type' => $type, 'ref' => $val];
                        }
                    } else {
                        $type = 'user';

                        if ($client) {
                            $promises[] = $client->fetchUser($matches[2][$key])->then(
                                function (User $user) use (&$bucket, $key, $type) {
                                    $bucket[$key] = ['type' => $type, 'ref' => $user];
                                },
                                function () use (&$bucket, $key, $val, $type) {
                                    $bucket[$key] = ['type' => $type, 'ref' => $val];
                                }
                            );
                        } else {
                            $bucket[$key] = ['type' => $type, 'ref' => $val];
                        }
                    }
                }

                all($promises)->done(
                    function () use (&$bucket, $resolve) {
                        $resolve($bucket);
                    }
                );
            }
        );
    }

    /**
     * Resolves files of Message Options.
     *
     * @param  array  $options
     *
     * @return ExtendedPromiseInterface
     * @throws InvalidArgumentException
     */
    public static function resolveMessageOptionsFiles(array $options)
    {
        if (empty($options['files'])) {
            return resolve([]);
        }

        $promises = [];
        foreach ($options['files'] as $file) {
            if ($file instanceof MessageAttachment) {
                $file = $file->_getMessageFilesArray();
            }

            if (is_string($file)) {
                if (filter_var($file, FILTER_VALIDATE_URL)) {
                    $promises[] = URLHelpers::resolveURLToData($file)->then(
                        function ($data) use ($file) {
                            return ['name' => basename($file), 'data' => $data];
                        }
                    );
                } elseif (realpath($file)) {
                    $promises[] = FileHelpers::resolveFileResolvable($file)->then(
                        function ($data) use ($file) {
                            return ['name' => basename($file), 'data' => $data];
                        }
                    );
                } else {
                    $promises[] = resolve(
                        ['name' => 'file-'.bin2hex(random_bytes(3)).'.jpg', 'data' => $file]
                    );
                }

                continue;
            }

            if (! is_array($file)) {
                continue;
            }

            if (! isset($file['data']) && ! isset($file['path'])) {
                throw new InvalidArgumentException(
                    'Invalid file array passed, missing data and path, one is required'
                );
            }

            if (! isset($file['name'])) {
                if (isset($file['path'])) {
                    $file['name'] = basename($file['path']);
                } else {
                    $file['name'] = 'file-'.bin2hex(random_bytes(3)).'.jpg';
                }
            }

            if (isset($file['path'])) {
                if (filter_var($file['path'], FILTER_VALIDATE_URL)) {
                    $promises[] = URLHelpers::resolveURLToData($file['path'])->then(
                        function ($data) use ($file) {
                            $file['data'] = $data;

                            return $file;
                        }
                    );
                } elseif (FileHelpers::getFilesystem()) {
                    $promises[] = FileHelpers::getFilesystem()->getContents($file['path'])->then(
                        function ($data) use ($file) {
                            $file['data'] = $data;

                            return $file;
                        }
                    );
                } else {
                    $file['data'] = file_get_contents($file['path']);
                    $promises[] = resolve($file);
                }
            } else {
                $promises[] = resolve($file);
            }
        }

        return all($promises);
    }

    /**
     * Splits a string into multiple chunks at a designated character that do not exceed a specific length.
     *
     * Options will be merged into default split options (see Message), so missing elements will get added.
     * ```
     * array(
     *     'before' => string, (the string to add before the chunk)
     *     'after' => string, (the string to add after the chunk)
     *     'char' => string, (the string to split on)
     *     'maxLength' => int (the max. length of each chunk)
     * )
     * ```
     *
     * @param  string  $text
     * @param  array  $options  Options controlling the behaviour of the split.
     *
     * @return string[]
     * @see \CharlotteDunois\Yasmin\Models\Message
     */
    public static function splitMessage(string $text, array $options = [])
    {
        $options = array_merge(Message::DEFAULT_SPLIT_OPTIONS, $options);

        if (mb_strlen($text) > $options['maxLength']) {
            $i = 0;
            $messages = [];

            $parts = explode($options['char'], $text);
            foreach ($parts as $part) {
                if (! isset($messages[$i])) {
                    $messages[$i] = '';
                }

                if ((mb_strlen($messages[$i]) + mb_strlen($part) + 2) >= $options['maxLength']) {
                    $i++;
                    $messages[$i] = '';
                }

                $messages[$i] .= $part.$options['char'];
            }

            return $messages;
        }

        return [$text];
    }
}
