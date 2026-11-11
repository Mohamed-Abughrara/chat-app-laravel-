# Real-Time Chat System (Laravel 11, Clean Architecture)

Implementation of `PDR Real-Time Chat System (Laravel, Clean Architecture).md` as a **runnable local MVP**.
1-on-1 and group chat (≤100 members), presence, multi-device sync, delivery acks, idempotent sends,
history paging, Reverb WebSockets, Sanctum device tokens, and a small web client.

## Run it

Needs PHP 8.2+, Composer, Node 20+, MySQL (XAMPP is fine).

```bash
composer install && npm install
cp .env.example .env && php artisan key:generate   # then set DB_*; create DBs chat_app and chat_app_test
php artisan migrate
npm run build                                       # or `npm run dev`

php artisan serve                  # HTTP API + web client  -> http://127.0.0.1:8000
php artisan reverb:start           # WebSocket server (port 8080)
php artisan queue:work             # sync queue consumer: persists + delivers messages
php artisan schedule:work          # presence sweep every 5 s
```

Use `REVERB_HOST=127.0.0.1` (not `localhost`): on Windows `localhost` resolves to IPv6 first and may hit another
local server on `[::1]:8080`.

Open the app in two browsers, sign up two users, add each other as friends by email, and chat.

## Checks

```bash
vendor/bin/pest              # 27 tests: domain rules, Snowflake, REST flows, presence, channel auth
vendor/bin/deptrac analyse   # Clean Architecture layer rules (Domain/Application have no framework imports)
```

## Layout (PDR 6.3)

| Layer | Path | Depends on |
| --- | --- | --- |
| Domain | `src/Domain` – entities, value objects (`MessageBody` ≤100K chars), events, repository interfaces | nothing |
| Application | `src/Application` – use cases (`SendMessage`, `DeliverMessage`, `SyncDevice`, `FetchHistory`, `CreateGroup`, `RecordHeartbeat` …) and ports | Domain |
| Infrastructure | `src/Infrastructure` – adapters for each port | Application, Domain, Laravel |
| Presentation | `app/Http`, `app/Console`, `routes/` | everything |

`app/Providers/ChatServiceProvider.php` is the only place that binds ports to adapters.

## PDR → MVP adapter mapping

The PDR's production stack isn't available locally, so each is replaced behind the same port. Swap by editing the provider.

| PDR component | Port | Local adapter | Production adapter (not built) |
| --- | --- | --- | --- |
| Kafka sync queue | `MessageQueue` | Laravel database queue (`DeliverMessageJob`, 5 tries then `failed_jobs`) | Kafka |
| ScyllaDB/Cassandra history | `MessageRepository`, `InboxRepository` | MySQL tables | Scylla |
| PostgreSQL users/groups | `UserRepository`, `GroupRepository` … | MySQL | PostgreSQL shards |
| Redis presence / idempotency | `PresenceStore`, `IdempotencyStore` | Laravel cache (database store) | Redis |
| ZooKeeper discovery | `ServerRegistry` | `config/chat.php` static list | ZooKeeper |
| APNs / FCM | `PushNotifier` | Log (counts only, never content) | APNs/FCM |
| Snowflake (ZK-leased worker id) | `IdGenerator` | `SnowflakeIdGenerator`, worker id from config | ZK lease |

## Deviations from the PDR

- **Client → server events go over HTTP, not WebSocket.** Reverb client events are only relayed to other
  subscribers and never run server code, so `message.send`, `presence.heartbeat` and `message.delivered` use
  authenticated `POST /api/v1/messages`, `/presence/heartbeat`, `/messages/{id}/delivered`. They call the same use
  cases. Server → client events (`message.new`, `message.status`, `presence.changed`) use WebSocket on `private-user.{id}`.
- Ack is returned when the message is accepted into the durable queue (PDR 9.2); it is stored by the queue consumer.
- Not built: Kafka/Scylla/Redis/ZooKeeper/FCM adapters, Reverb scale-out and the 1M-socket load-test spike (R-01).

## API (`/api/v1`, Sanctum bearer token per device)

`auth/register`, `auth/login` (returns token + `chat_server` + `presence_server`), `auth/logout`, `me`, `friends`
(GET, POST by email), `groups` (POST), `groups/{id}/members` (POST/DELETE), `conversations`,
`conversations/{id}/messages?before=&limit=`, `groups/{id}/messages`, `sync?after=`, `messages` (POST),
`messages/{id}/delivered`, `presence/heartbeat`, `devices/push-token`. Message ids are strings (64-bit).
Errors: `{ "error": { "code", "message", "retryable" } }`.
