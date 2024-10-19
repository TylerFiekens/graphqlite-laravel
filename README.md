[![Latest Stable Version](https://poser.pugx.org/thecodingmachine/graphqlite-laravel/v/stable)](https://packagist.org/packages/thecodingmachine/graphqlite-laravel)
[![Latest Unstable Version](https://poser.pugx.org/thecodingmachine/graphqlite-laravel/v/unstable)](https://packagist.org/packages/thecodingmachine/graphqlite-laravel)
[![License](https://poser.pugx.org/thecodingmachine/graphqlite-laravel/license)](https://packagist.org/packages/thecodingmachine/graphqlite-laravel)
[![Build Status](https://github.com/thecodingmachine/graphqlite/workflows/Continuous%20Integration/badge.svg)](https://github.com/thecodingmachine/graphqlite/actions)


Laravel GraphQLite bindings
===========================

GraphQLite integration package for Laravel.

**This package is based on the work of the friendly folks over at [TheCodingMachine](https://github.com/thecodingmachine/graphqlite-laravel).**

## Installation

```bash
composer require tylerfiekens/graphqlite-laravel
```

## Publish the configuration file

```bash
php artisan vendor:publish --provider="TheCodingMachine\GraphQLite\Laravel\Providers\GraphQLiteServiceProvider"
```

## Usage

The package will automatically register the routes for the GraphQL endpoint. You can access the GraphQL endpoint at `/graphql`.

By default, the package will look for your GraphQL types in the `App\GraphQL\Types` namespace, and your queries and mutations in the `App\GraphQL\Queries` and `App\GraphQL\Mutations` namespaces respectively.
You can change these settings in the `config/graphqlite.php` configuration file.

### Types

To create a new type, you can just create a new class in the `App\GraphQL\Types` namespace. 
The only requirement is that the class must have a `#[Type]` annotation, which is used to register the type in the schema.

To add fields to the type, you can create public methods with a `#[Field]` annotation. The method name will be used as the field name.


```php
<?php

namespace App\GraphQL\Types;

use TheCodingMachine\GraphQLite\Annotations\Type;
use TheCodingMachine\GraphQLite\Annotations\Field;

#[Type(name: 'Example')]
class ExampleType
{
    public function __construct(
        private readonly string $name
        private readonly array $data
    ) {
    }
    
    #[Field]
    public function name(): string
    {
        return $this->name;
    }
    
    #[Field]
    public function data(): array
    {
        return $this->data;
    }
}
```

That's it! The type will be automatically registered in the schema.

### Queries

Now let's create a query to fetch an example object.

By default, the package will look for queries in the `App\GraphQL\Queries` namespace.

Queries are created in the same way as types, but with a `#[Query]` annotation instead of a `#[Type]` annotation.

```php
<?php

namespace App\GraphQL\Queries;

use App\GraphQL\Types\ExampleType;
use TheCodingMachine\GraphQLite\Annotations\Query;

class ExampleQuery
{
    #[Query]
    public function example(): ExampleType
    {
        return new ExampleType('Example', ['data' => 'example']);
    }
}
```

The query will be automatically registered in the schema. You can now query it in your GraphQL client.

```graphql
query {
    example {
        name
        data
    }
}
```

### Mutations

Now let's create a mutation to create a new example object.

By default, the package will look for mutations in the `App\GraphQL\Mutations` namespace.

Mutations are created in the same way as queries, but with a `#[Mutation]` annotation instead of a `#[Query]` annotation.

```php
<?php

namespace App\GraphQL\Mutations;

use App\GraphQL\Types\ExampleType;
use TheCodingMachine\GraphQLite\Annotations\Mutation;

class CreateExampleMutation
{
    #[Mutation]
    public function createExample(string $name, array $data): ExampleType
    {
        return new ExampleType($name, $data);
    }
}
```

The mutation will be automatically registered in the schema. You can now call it in your GraphQL client.

```graphql
mutation {
    createExample(name: "Example", data: {data: "example"}) {
        name
        data
    }
}
```
