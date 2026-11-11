<?php

use Src\Domain\Chat\Entities\Group;
use Src\Domain\Chat\ValueObjects\MessageBody;
use Src\Domain\Shared\GroupLimitExceeded;
use Src\Domain\Shared\InvalidMessage;
use Src\Infrastructure\Support\SnowflakeIdGenerator;

it('accepts a message of exactly 100,000 characters', function () {
    expect(MessageBody::fromString(str_repeat('a', 100_000))->value)->toHaveLength(100_000);
});

it('counts characters, not bytes', function () {
    expect(MessageBody::fromString(str_repeat('é', 100_000))->value)->not->toBeEmpty();
});

it('rejects a message over 100,000 characters', function () {
    MessageBody::fromString(str_repeat('a', 100_001));
})->throws(InvalidMessage::class);

it('rejects empty and invalid UTF-8 bodies', function (string $bad) {
    MessageBody::fromString($bad);
})->with(['empty' => [''], 'blank' => ["  \n"], 'bad utf8' => ["\xB1\x31"]])->throws(InvalidMessage::class);

it('caps a group at 100 members including the owner', function () {
    $group = new Group(null, 'g', 1, range(2, 100)); // 99 + owner = 100
    expect($group->memberIds())->toHaveCount(100);
    $group->addMember(101);
})->throws(GroupLimitExceeded::class);

it('does not count duplicates and keeps the owner', function () {
    $group = new Group(null, 'g', 1, [1, 2, 2, 3]);
    expect($group->memberIds())->toBe([1, 2, 3]);
    expect(fn () => $group->removeMember(1))->toThrow(InvalidMessage::class);
});

it('generates strictly increasing unique snowflake ids, even within one millisecond', function () {
    $gen = new SnowflakeIdGenerator(7, fn () => 1_800_000_000_000, 0); // frozen clock
    $ids = [];
    for ($i = 0; $i < 3000; $i++) {
        $ids[] = $gen->next();
    }
    expect(count(array_unique($ids)))->toBe(3000);
    expect($ids)->toBe(array_values(array_unique($ids)));
    expect($ids[0])->toBeGreaterThan(0)->and($ids[2999])->toBeLessThan(PHP_INT_MAX);
});

it('waits for the next millisecond when the sequence is exhausted', function () {
    $calls = 0;
    $clock = function () use (&$calls) {
        $calls++;

        return $calls <= 4096 ? 1_800_000_000_000 : 1_800_000_000_001;
    };
    $gen = new SnowflakeIdGenerator(7, $clock, 0);
    $ids = [];
    for ($i = 0; $i < 4097; $i++) {
        $ids[] = $gen->next();
    }
    expect(count(array_unique($ids)))->toBe(4097);
    expect($ids[4096])->toBeGreaterThan($ids[4095]);
});

it('orders ids by time', function () {
    $t = 1_800_000_000_000;
    $gen = new SnowflakeIdGenerator(1, function () use (&$t) { return $t; });
    $a = $gen->next();
    $t++;
    $b = $gen->next();
    expect($b)->toBeGreaterThan($a);
});

it('refuses to issue ids when the clock moves backwards', function () {
    $t = 1_800_000_000_000;
    $gen = new SnowflakeIdGenerator(1, function () use (&$t) { return $t; });
    $gen->next();
    $t -= 5;
    $gen->next();
})->throws(RuntimeException::class);
