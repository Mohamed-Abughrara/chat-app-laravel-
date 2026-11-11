<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Identity\LoginDevice;
use Src\Application\Identity\LogoutDevice;
use Src\Application\Identity\RegisterUser;

class AuthController extends Controller
{
    public function register(Request $request, RegisterUser $useCase): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:255'],
            'password' => ['required', 'string', 'min:8', 'max:200'],
        ]);

        return response()->json($useCase->execute($data['name'], $data['email'], $data['password']), 201);
    }

    public function login(Request $request, LoginDevice $useCase): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'platform' => ['nullable', 'in:web,ios,android'],
        ]);

        return response()->json($useCase->execute($data['email'], $data['password'], $data['platform'] ?? 'web'));
    }

    public function logout(Request $request, LogoutDevice $useCase): JsonResponse
    {
        $useCase->execute($request->user()->id, $this->deviceId($request));

        return response()->json(['ok' => true]);
    }
}
