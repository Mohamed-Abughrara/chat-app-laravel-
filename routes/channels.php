<?php

use Illuminate\Support\Facades\Broadcast;

// private-user.{id}: only that user may subscribe (PDR section 12).
Broadcast::channel('user.{id}', fn ($user, $id) => (int) $user->id === (int) $id);
