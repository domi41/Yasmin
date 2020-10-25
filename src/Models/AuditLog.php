<?php
/**
 * Yasmin
 * Copyright 2017-2019 Charlotte Dunois, All Rights Reserved.
 *
 * Website: https://charuru.moe
 * License: https://github.com/CharlotteDunois/Yasmin/blob/master/LICENSE
 */

namespace CharlotteDunois\Yasmin\Models;

use CharlotteDunois\Collect\Collection;
use CharlotteDunois\Yasmin\Client;

/**
 * Represents a guild audit log.
 *
 * @property Guild $guild     Which guild this audit log is for.
 * @property Collection $entries   Holds the entries, mapped by their ID.
 * @property Collection $users     Holds the found users in the audit log, mapped by their ID.
 * @property Collection $webhooks  Holds the found webhooks in the audit log, mapped by their ID.
 */
class AuditLog extends ClientBase
{
    /**
     * Which guild this audit log is for.
     *
     * @var Guild
     */
    protected $guild;

    /**
     * Holds the entries, mapped by their ID.
     *
     * @var Collection
     */
    protected $entries;

    /**
     * Holds the found users in the audit log, mapped by their ID.
     *
     * @var Collection
     */
    protected $users;

    /**
     * Holds the found webhooks in the audit log, mapped by their ID.
     *
     * @var Collection
     */
    protected $webhooks;

    /**
     * @param  Client  $client
     * @param  Guild  $guild
     * @param  array  $audit
     *
     * @internal
     */
    public function __construct(Client $client, Guild $guild, array $audit)
    {
        parent::__construct($client);
        $this->guild = $guild;

        $this->entries = new Collection();
        $this->users = new Collection();
        $this->webhooks = new Collection();

        foreach ($audit['users'] as $user) {
            $usr = $this->client->users->patch($user);
            $this->users->set($usr->id, $usr);
        }

        foreach ($audit['webhooks'] as $webhook) {
            $hook = new Webhook($this->client, $webhook);
            $this->webhooks->set($hook->id, $hook);
        }

        foreach ($audit['audit_log_entries'] as $entry) {
            $log = new AuditLogEntry($this->client, $this, $entry);
            $this->entries->set($log->id, $log);
        }
    }

    /**
     * {@inheritdoc}
     * @return mixed
     * @internal
     */
    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        return parent::__get($name);
    }
}
