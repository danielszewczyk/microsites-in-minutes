<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Webmaster emails
    |--------------------------------------------------------------------------
    |
    | Accounts whose email is on this list can reach the /admin dashboard and
    | see every site on the service. Set WEBMASTER_EMAILS to a comma-separated
    | list of addresses; each is lower-cased and trimmed for comparison. An
    | empty list means no one is a webmaster, so the area is closed by default.
    |
    */

    'emails' => array_values(array_filter(array_map(
        fn (string $email): string => strtolower(trim($email)),
        explode(',', (string) env('WEBMASTER_EMAILS', '')),
    ))),

];
