<?php

return [
    'gate_title' => 'Scheduler and queue execution evidence',
    'gate_help' => 'The scheduler and each configured queue must have handled a private probe issued within the last five minutes. This is local execution evidence, not a claim that every worker is alive.',
    'states' => ['ok' => 'Recent evidence available', 'degraded' => 'Evidence is stale', 'unknown' => 'Evidence is unavailable'],
];
