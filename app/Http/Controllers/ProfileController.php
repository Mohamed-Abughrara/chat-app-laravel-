<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Identity\Friends;
use Src\Application\Identity\Profile;
use Src\Application\Identity\RegisterPushToken;

class ProfileController extends Controller
{
    public function show(Request $request, Profile $profile): JsonResponse
    {
        return response()->json($profile->get($request->user()->id));
    }

    public function update(Request $request, Profile $profile): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:100']]);

        return response()->json($profile->update($request->user()->id, $data['name']));
    }

    public function friends(Request $request, Friends $friends): JsonResponse
    {
        return response()->json(['friends' => $friends->list($request->user()->id)]);
    }

    public function addFriend(Request $request, Friends $friends): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email']]);

        return response()->json(['friends' => $friends->addByEmail($request->user()->id, $data['email'])], 201);
    }

    public function pushToken(Request $request, RegisterPushToken $useCase): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'max:255']]);
        $useCase->execute($request->user()->id, $this->deviceId($request), $data['token']);

        return response()->json(['ok' => true]);
    }
}
