<?php

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Src\Infrastructure\Realtime\ChatEvent;

/** Registers and logs in a user; returns [user_id, auth headers, device_id]. */
function signUp(string $name): array
{
    $email = strtolower($name).'@example.com';
    test()->postJson('/api/v1/auth/register', ['name' => $name, 'email' => $email, 'password' => 'password123'])->assertCreated();
    $login = test()->postJson('/api/v1/auth/login', ['email' => $email, 'password' => 'password123'])->assertOk();

    return [$login['user_id'], ['Authorization' => 'Bearer '.$login['token']], $login['device_id'], $email];
}

function send(array $h, array $body, ?string $clientId = null)
{
    return test()->postJson('/api/v1/messages', ['client_msg_id' => $clientId ?? uniqid('c-'), ...$body], $h);
}

beforeEach(function () {
    Event::fake([ChatEvent::class]); // capture broadcasts; no Reverb needed
    app('auth')->forgetGuards();     // each request authenticates from its own bearer token
});

it('logs in with a token, chat server and presence server', function () {
    test()->postJson('/api/v1/auth/register', ['name' => 'Ann', 'email' => 'ann@example.com', 'password' => 'password123'])->assertCreated();
    test()->postJson('/api/v1/auth/login', ['email' => 'ann@example.com', 'password' => 'password123'])
        ->assertOk()->assertJsonStructure(['token', 'user_id', 'device_id', 'chat_server' => ['host', 'port'], 'presence_server' => ['host', 'port']]);
    test()->postJson('/api/v1/auth/login', ['email' => 'ann@example.com', 'password' => 'wrong-password'])->assertStatus(401)
        ->assertJsonPath('error.code', 'invalid_credentials');
});

it('rejects unauthenticated access', function () {
    test()->getJson('/api/v1/me')->assertUnauthorized();
});

it('delivers a 1-on-1 message: ack, history, inbox sync and live event', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');

    $ack = send($ha, ['to_user_id' => $b, 'content' => 'Hi Bob'], 'c-1')->assertStatus(202)->json();
    expect($ack['message_id'])->toBeString()->and($ack['duplicate'])->toBeFalse();

    // Bob is offline (no heartbeat) so he gets a push, not a live event; Ann's other devices get message.new.
    Event::assertDispatched(ChatEvent::class, fn ($e) => $e->userId === $a && $e->name === 'message.new');
    Event::assertNotDispatched(ChatEvent::class, fn ($e) => $e->userId === $b);

    $conv = test()->getJson('/api/v1/conversations', $hb)->assertOk()->json('direct.0.id');
    $page = test()->getJson("/api/v1/conversations/$conv/messages", $hb)->assertOk()->json();
    expect($page['messages'])->toHaveCount(1)->and($page['messages'][0]['content'])->toBe('Hi Bob');

    $sync = test()->getJson('/api/v1/sync?after=0', $hb)->assertOk()->json();
    expect($sync['messages'])->toHaveCount(1)->and($sync['cur_max_message_id'])->toBe($ack['message_id']);
    // Nothing newer than cur_max.
    expect(test()->getJson('/api/v1/sync?after='.$ack['message_id'], $hb)->json('messages'))->toBeEmpty();
});

it('pushes message.new to an online recipient', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    test()->postJson('/api/v1/presence/heartbeat', [], $hb)->assertOk();

    send($ha, ['to_user_id' => $b, 'content' => 'ping']);

    Event::assertDispatched(ChatEvent::class, fn ($e) => $e->userId === $b && $e->name === 'message.new' && $e->payload['content'] === 'ping');
});

it('stores a retried message exactly once (idempotent client_msg_id)', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');

    $first = send($ha, ['to_user_id' => $b, 'content' => 'once'], 'c-same')->json();
    $second = send($ha, ['to_user_id' => $b, 'content' => 'once'], 'c-same')->json();

    expect($second['message_id'])->toBe($first['message_id'])->and($second['duplicate'])->toBeTrue();
    expect(DB::table('messages')->count())->toBe(1);
});

it('rejects invalid sends', function () {
    [$a, $ha] = signUp('Ann');
    send($ha, ['to_user_id' => $a, 'content' => 'self'])->assertStatus(422);
    send($ha, ['to_user_id' => 999999, 'content' => 'x'])->assertStatus(404);
    send($ha, ['to_user_id' => 1, 'content' => str_repeat('a', 100_001)])->assertStatus(422);
    send($ha, ['content' => 'nobody'])->assertStatus(422);
});

it('fans a group message out to every member inbox and stores the body once', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    [$c, $hc] = signUp('Cy');

    $group = test()->postJson('/api/v1/groups', ['name' => 'Team', 'member_ids' => [$b, $c]], $ha)->assertCreated()->json();
    send($ha, ['group_id' => $group['id'], 'content' => 'hello team'])->assertStatus(202);

    expect(DB::table('group_messages')->count())->toBe(1);
    expect(DB::table('user_inbox')->count())->toBe(3);
    foreach ([$hb, $hc] as $h) {
        expect(test()->getJson('/api/v1/sync?after=0', $h)->json('messages.0.content'))->toBe('hello team');
    }
    expect(test()->getJson("/api/v1/groups/{$group['id']}/messages", $hb)->json('messages'))->toHaveCount(1);
});

it('blocks non-members from sending to or reading a group', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    [$x, $hx] = signUp('Eve');
    $group = test()->postJson('/api/v1/groups', ['name' => 'Private', 'member_ids' => [$b]], $ha)->json();

    send($hx, ['group_id' => $group['id'], 'content' => 'let me in'])->assertStatus(403);
    test()->getJson("/api/v1/groups/{$group['id']}/messages", $hx)->assertStatus(403);
});

it('only lets participants read a 1-on-1 conversation', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    [$x, $hx] = signUp('Eve');
    send($ha, ['to_user_id' => $b, 'content' => 'secret']);
    $conv = test()->getJson('/api/v1/conversations', $ha)->json('direct.0.id');

    test()->getJson("/api/v1/conversations/$conv/messages", $hx)->assertStatus(403);
});

it('enforces the 100 member group cap', function () {
    [$a, $ha] = signUp('Ann');
    $ids = [];
    for ($i = 0; $i < 100; $i++) {
        $ids[] = DB::table('users')->insertGetId(['name' => "u$i", 'email' => "u$i@x.test", 'password' => 'x', 'created_at' => now(), 'updated_at' => now()]);
    }

    test()->postJson('/api/v1/groups', ['name' => 'Big', 'member_ids' => array_slice($ids, 0, 99)], $ha)->assertCreated(); // 99 + owner = 100
    $group = DB::table('groups')->first();
    test()->postJson("/api/v1/groups/{$group->id}/members", ['user_ids' => [$ids[99]]], $ha)
        ->assertStatus(422)->assertJsonPath('error.code', 'group_limit_exceeded');
});

it('pages history backwards with before and limit', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    for ($i = 1; $i <= 5; $i++) {
        send($ha, ['to_user_id' => $b, 'content' => "m$i"]);
    }
    $conv = test()->getJson('/api/v1/conversations', $ha)->json('direct.0.id');

    $p1 = test()->getJson("/api/v1/conversations/$conv/messages?limit=2", $ha)->json();
    expect(array_column($p1['messages'], 'content'))->toBe(['m5', 'm4']);
    $p2 = test()->getJson("/api/v1/conversations/$conv/messages?limit=2&before={$p1['next_before']}", $ha)->json();
    expect(array_column($p2['messages'], 'content'))->toBe(['m3', 'm2']);
});

it('marks delivered and notifies the sender', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    $ack = send($ha, ['to_user_id' => $b, 'content' => 'x'])->json();

    test()->postJson("/api/v1/messages/{$ack['message_id']}/delivered", [], $hb)->assertOk();
    Event::assertDispatched(ChatEvent::class, fn ($e) => $e->userId === $a && $e->name === 'message.status' && $e->payload['status'] === 'delivered');
    test()->postJson("/api/v1/messages/{$ack['message_id']}/delivered", [], $ha)->assertStatus(403); // sender cannot ack own message
});

it('tracks presence: online on heartbeat, offline after 30 s, friends are told', function () {
    [$a, $ha] = signUp('Ann');
    [$b, $hb] = signUp('Bob');
    test()->postJson('/api/v1/friends', ['email' => 'bob@example.com'], $ha)->assertCreated();

    test()->postJson('/api/v1/presence/heartbeat', [], $hb)->assertOk();
    Event::assertDispatched(ChatEvent::class, fn ($e) => $e->userId === $a && $e->name === 'presence.changed' && $e->payload['status'] === 'online');
    expect(test()->getJson('/api/v1/friends', $ha)->json('friends.0.status'))->toBe('online');

    $this->travel(31)->seconds();
    $this->artisan('chat:sweep-presence')->assertSuccessful();

    Event::assertDispatched(ChatEvent::class, fn ($e) => $e->userId === $a && $e->name === 'presence.changed' && $e->payload['status'] === 'offline');
    expect(test()->getJson('/api/v1/friends', $ha)->json('friends.0.status'))->toBe('offline');
});

it('logout revokes the device token and marks the user offline', function () {
    [$a, $ha] = signUp('Ann');
    test()->postJson('/api/v1/presence/heartbeat', [], $ha)->assertOk();

    test()->postJson('/api/v1/auth/logout', [], $ha)->assertOk();
    app('auth')->forgetGuards();

    test()->getJson('/api/v1/me', $ha)->assertUnauthorized();
    expect(app(Src\Application\Ports\PresenceStore::class)->isOnline($a))->toBeFalse();
});

it('authorises only the owner on private-user.{id}', function () {
    [$a, $ha] = signUp('Ann');
    [$b] = signUp('Bob');
    app('auth')->forgetGuards();

    test()->postJson('/api/broadcasting/auth', ['socket_id' => '123.456', 'channel_name' => "private-user.$b"], $ha)->assertForbidden();
});
