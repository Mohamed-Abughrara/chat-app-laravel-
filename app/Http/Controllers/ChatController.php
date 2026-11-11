<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Src\Application\Chat\UseCases\ChangeGroupMembers;
use Src\Application\Chat\UseCases\CreateGroup;
use Src\Application\Chat\UseCases\FetchHistory;
use Src\Application\Chat\UseCases\ListConversations;
use Src\Application\Chat\UseCases\MarkDelivered;
use Src\Application\Chat\UseCases\SendMessage;
use Src\Application\Chat\UseCases\SyncDevice;
use Src\Application\Presence\RecordHeartbeat;

class ChatController extends Controller
{
    /** message.send: returns the message.ack payload (202: accepted into the durable queue). */
    public function send(Request $request, SendMessage $useCase): JsonResponse
    {
        $data = $request->validate([
            'client_msg_id' => ['required', 'string', 'max:64'],
            'to_user_id' => ['nullable', 'integer', 'required_without:group_id'],
            'group_id' => ['nullable', 'integer', 'required_without:to_user_id'],
            'content' => ['required', 'string'], // length and encoding enforced by the MessageBody value object
        ]);

        $ack = $useCase->execute(
            $request->user()->id,
            $data['client_msg_id'],
            $data['content'],
            isset($data['to_user_id']) ? (int) $data['to_user_id'] : null,
            isset($data['group_id']) ? (int) $data['group_id'] : null,
        );

        return response()->json($ack, 202);
    }

    public function delivered(Request $request, string $messageId, MarkDelivered $useCase): JsonResponse
    {
        $useCase->execute($request->user()->id, (int) $messageId);

        return response()->json(['ok' => true]);
    }

    public function heartbeat(Request $request, RecordHeartbeat $useCase): JsonResponse
    {
        $useCase->execute($request->user()->id, $this->deviceId($request));

        return response()->json(['ok' => true]);
    }

    public function sync(Request $request, SyncDevice $useCase): JsonResponse
    {
        $data = $request->validate(['after' => ['nullable', 'regex:/^\d+$/'], 'limit' => ['nullable', 'integer', 'min:1', 'max:500']]);

        return response()->json($useCase->execute(
            $request->user()->id,
            (int) ($data['after'] ?? 0),
            (int) ($data['limit'] ?? SyncDevice::MAX_BATCH),
        ));
    }

    public function conversations(Request $request, ListConversations $useCase): JsonResponse
    {
        return response()->json($useCase->execute($request->user()->id));
    }

    public function history(Request $request, string $id, FetchHistory $useCase): JsonResponse
    {
        return $this->page($request, $useCase, false, (int) $id);
    }

    public function groupHistory(Request $request, string $id, FetchHistory $useCase): JsonResponse
    {
        return $this->page($request, $useCase, true, (int) $id);
    }

    public function createGroup(Request $request, CreateGroup $useCase): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'member_ids' => ['array', 'max:99'],
            'member_ids.*' => ['integer'],
        ]);

        return response()->json($useCase->execute($request->user()->id, $data['name'], $data['member_ids'] ?? []), 201);
    }

    public function addMembers(Request $request, string $id, ChangeGroupMembers $useCase): JsonResponse
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer']]);

        return response()->json($useCase->execute($request->user()->id, (int) $id, add: $data['user_ids']));
    }

    public function removeMembers(Request $request, string $id, ChangeGroupMembers $useCase): JsonResponse
    {
        $data = $request->validate(['user_ids' => ['required', 'array', 'min:1'], 'user_ids.*' => ['integer']]);

        return response()->json($useCase->execute($request->user()->id, (int) $id, remove: $data['user_ids']));
    }

    private function page(Request $request, FetchHistory $useCase, bool $isGroup, int $id): JsonResponse
    {
        $data = $request->validate(['before' => ['nullable', 'regex:/^\d+$/'], 'limit' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return response()->json($useCase->execute(
            $request->user()->id,
            $isGroup,
            $id,
            isset($data['before']) ? (int) $data['before'] : null,
            (int) ($data['limit'] ?? 50),
        ));
    }
}
