<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Src\Application\Ports\IdempotencyStore;
use Src\Application\Ports\IdGenerator;
use Src\Application\Ports\MessageQueue;
use Src\Application\Ports\PasswordHasher;
use Src\Application\Ports\PresenceStore;
use Src\Application\Ports\PushNotifier;
use Src\Application\Ports\RealtimeGateway;
use Src\Application\Ports\ServerRegistry;
use Src\Application\Ports\TokenIssuer;
use Src\Domain\Chat\Repositories\ConversationRepository;
use Src\Domain\Chat\Repositories\GroupRepository;
use Src\Domain\Chat\Repositories\InboxRepository;
use Src\Domain\Chat\Repositories\MessageRepository;
use Src\Domain\Chat\Repositories\ReceiptRepository;
use Src\Domain\Identity\DeviceRepository;
use Src\Domain\Identity\UserRepository;
use Src\Infrastructure\Discovery\StaticServerRegistry;
use Src\Infrastructure\Messaging\LaravelMessageQueue;
use Src\Infrastructure\Persistence\CacheIdempotencyStore;
use Src\Infrastructure\Persistence\EloquentConversationRepository;
use Src\Infrastructure\Persistence\EloquentDeviceRepository;
use Src\Infrastructure\Persistence\EloquentGroupRepository;
use Src\Infrastructure\Persistence\EloquentInboxRepository;
use Src\Infrastructure\Persistence\EloquentMessageRepository;
use Src\Infrastructure\Persistence\EloquentReceiptRepository;
use Src\Infrastructure\Persistence\EloquentUserRepository;
use Src\Infrastructure\Presence\CachePresenceStore;
use Src\Infrastructure\Push\LogPushNotifier;
use Src\Infrastructure\Realtime\ReverbRealtimeGateway;
use Src\Infrastructure\Support\LaravelPasswordHasher;
use Src\Infrastructure\Support\SanctumTokenIssuer;
use Src\Infrastructure\Support\SnowflakeIdGenerator;

/**
 * The only place that knows which adapter fulfils which port. Swap Kafka, Redis, Scylla or
 * ZooKeeper adapters in here without touching a use case.
 */
class ChatServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Domain repositories
        $this->app->bind(MessageRepository::class, EloquentMessageRepository::class);
        $this->app->bind(InboxRepository::class, EloquentInboxRepository::class);
        $this->app->bind(GroupRepository::class, EloquentGroupRepository::class);
        $this->app->bind(ConversationRepository::class, EloquentConversationRepository::class);
        $this->app->bind(ReceiptRepository::class, EloquentReceiptRepository::class);
        $this->app->bind(UserRepository::class, EloquentUserRepository::class);
        $this->app->bind(DeviceRepository::class, EloquentDeviceRepository::class);

        // Application ports
        $this->app->singleton(IdGenerator::class, fn () => new SnowflakeIdGenerator((int) config('chat.worker_id')));
        $this->app->bind(MessageQueue::class, LaravelMessageQueue::class);
        $this->app->bind(IdempotencyStore::class, CacheIdempotencyStore::class);
        $this->app->bind(PresenceStore::class, fn () => new CachePresenceStore((int) config('chat.presence_ttl')));
        $this->app->bind(PushNotifier::class, LogPushNotifier::class);
        $this->app->bind(RealtimeGateway::class, ReverbRealtimeGateway::class);
        $this->app->bind(PasswordHasher::class, LaravelPasswordHasher::class);
        $this->app->bind(TokenIssuer::class, SanctumTokenIssuer::class);
        $this->app->bind(ServerRegistry::class, fn () => new StaticServerRegistry(
            config('chat.chat_servers'),
            config('chat.presence_server'),
        ));
    }

    public function boot(): void
    {
        // Rate limits (PDR section 12).
        RateLimiter::for('messages', fn (Request $r) => Limit::perSecond(20)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(10)->by($r->ip().'|'.$r->input('email')));
        RateLimiter::for('group-create', fn (Request $r) => Limit::perHour(5)->by($r->user()?->id ?: $r->ip()));
        RateLimiter::for('chat-api', fn (Request $r) => Limit::perMinute(300)->by($r->user()?->id ?: $r->ip()));
    }
}
