<?php

return [
    /*
     * Snowflake worker id (0-1023). Every process type/node that generates ids must use a unique one.
     * Production leases these from ZooKeeper (PDR D-04); locally it is configured.
     */
    'worker_id' => (int) env('CHAT_WORKER_ID', 1),

    /* Presence: heartbeat every 5 s on the client, offline after 30 s without one (PDR D-06). */
    'presence_ttl' => (int) env('CHAT_PRESENCE_TTL', 30),

    /* Static stand-in for ZooKeeper service discovery. */
    'chat_servers' => [
        ['host' => env('REVERB_HOST', 'localhost'), 'port' => (int) env('REVERB_PORT', 8080)],
    ],
    'presence_server' => ['host' => env('REVERB_HOST', 'localhost'), 'port' => (int) env('REVERB_PORT', 8080)],
];
