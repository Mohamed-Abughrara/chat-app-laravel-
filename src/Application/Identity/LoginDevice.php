<?php

declare(strict_types=1);

namespace Src\Application\Identity;

use Src\Application\Ports\PasswordHasher;
use Src\Application\Ports\ServerRegistry;
use Src\Application\Ports\TokenIssuer;
use Src\Domain\Identity\DeviceRepository;
use Src\Domain\Identity\UserRepository;
use Src\Domain\Shared\InvalidCredentials;

/** Verifies credentials, registers the device, issues a token and assigns a chat server (AssignChatServer). */
final class LoginDevice
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly DeviceRepository $devices,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
        private readonly ServerRegistry $registry,
    ) {}

    /** @return array<string,mixed> */
    public function execute(string $email, string $password, string $platform = 'web'): array
    {
        $user = $this->users->findByEmail(mb_strtolower(trim($email)));
        if ($user === null || ! $this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Invalid email or password.');
        }

        $device = $this->devices->register((int) $user->id, $platform);

        return [
            'token' => $this->tokens->issue((int) $user->id, $device->id),
            'user_id' => (int) $user->id,
            'name' => $user->name,
            'device_id' => $device->id,
            'chat_server' => $this->registry->assignChatServer(),
            'presence_server' => $this->registry->presenceServer(),
        ];
    }
}
