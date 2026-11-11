<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /** The device id is carried in the Sanctum token name ("device:{id}"). */
    protected function deviceId(Request $request): string
    {
        return substr((string) $request->user()->currentAccessToken()->name, strlen('device:'));
    }
}
