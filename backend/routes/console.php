<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('outbox:dispatch')->everyMinute()->withoutOverlapping();
Schedule::command('retention:run')->hourly()->withoutOverlapping();
Schedule::command('operations:heartbeat')->everyMinute()->withoutOverlapping();
