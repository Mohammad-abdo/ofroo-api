<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Run demo / bulk seeders (offers, orders, wallet tx, reviews, …)
    |--------------------------------------------------------------------------
    |
    | Set to false to only run foundation seeders (roles, users, categories, …)
    | without generating random transactional data.
    |
    */

    'run_demo_seeders' => filter_var(env('SEED_DEMO', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Skip demo seeders if a previous run already completed
    |--------------------------------------------------------------------------
    |
    | When true, after the first successful demo seed we store a flag in
    | `settings` (key: seed_demo_completed). Further `php artisan db:seed` runs
    | skip duplicate-prone seeders unless SEED_FORCE=true.
    |
    | migrate:fresh clears the DB, so a full seed always runs demo again.
    |
    */

    'skip_demo_if_already_completed' => filter_var(env('SEED_SKIP_DEMO_IF_DONE', true), FILTER_VALIDATE_BOOL),

    /*
    |--------------------------------------------------------------------------
    | Force demo seeders to run again (creates duplicate rows)
    |--------------------------------------------------------------------------
    |
    | Use only when you intentionally want another batch of demo offers/orders/etc.
    |
    */

    'force_demo_repeat' => filter_var(env('SEED_FORCE', false), FILTER_VALIDATE_BOOL),

];
