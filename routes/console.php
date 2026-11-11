<?php

use Illuminate\Support\Facades\Schedule;

// Presence sweep every 5 s (PDR R-05). Needs `php artisan schedule:work`.
Schedule::command('chat:sweep-presence')->everyFiveSeconds()->withoutOverlapping();
