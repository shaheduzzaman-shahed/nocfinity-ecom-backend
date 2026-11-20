<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Queue Activity Logs
    |--------------------------------------------------------------------------
    |
    | When set to true, the StoreActivityLog listener will push log storage
    | to the active queue connection for asynchronous background processing.
    |
    */
    'queue' => env('ACTIVITY_LOG_QUEUE', false),
];
