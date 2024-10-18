<?php

use GraphQL\Error\DebugFlag;
use TheCodingMachine\GraphQLite\Http\HttpCodeDecider;

return [
    /*
     |--------------------------------------------------------------------------
     | GraphQLite Configuration
     |--------------------------------------------------------------------------
     |
     | Use this configuration to customize the namespace of the controllers and
     | types.
     | These namespaces must be autoloadable from Composer.
     | GraphQLite will find the path of the files based on composer.json settings.
     |
     | You can put a single namespace, or an array of namespaces.
     |
     */
    'queries' => 'App\\GraphQL\\Queries',
    'mutations' => 'App\\GraphQL\\Mutations',
    'types' => 'App\\GraphQL\\Types',

    'disable_introspection' => env('GRAPHQLITE_DISABLE_INTROSPECTION', false),

    'debug' => env('GRAPHQLITE_DEBUG', DebugFlag::NONE),
    'uri' => env('GRAPHQLITE_URI', '/graphql'),
    'middleware' => ['web'],

    // Sets the status code in the HTTP request where operations have errors.
    'http_code_decider' => HttpCodeDecider::class,
];
