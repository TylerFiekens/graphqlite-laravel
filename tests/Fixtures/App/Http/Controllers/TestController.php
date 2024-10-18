<?php


namespace App\Http\Controllers;


use Illuminate\Pagination\LengthAwarePaginator;
use Psr\Http\Message\UploadedFileInterface;
use TheCodingMachine\GraphQLite\Annotations\Logged;
use TheCodingMachine\GraphQLite\Annotations\Mutation;
use TheCodingMachine\GraphQLite\Annotations\Query;
use TheCodingMachine\GraphQLite\Laravel\Annotations\Validate;

class TestController
{
    #[Query]
    public function test(): string
    {
        return 'foo';
    }

    #[Query]
    public function testInt(): int
    {
        return 42;
    }

    #[Query]
    #[Logged]
    public function testLogged(): string
    {
        return 'foo';
    }

    /**
     * @return int[]
     */
    #[Query]
    public function testPaginator(): LengthAwarePaginator
    {
        return new LengthAwarePaginator([1,2,3,4], 42, 4, 2);
    }

    #[Query]
    public function testValidator(#[Validate(for: "foo", rule: "email")] string $foo,     #[Validate(for: "bar", rule: "gt:42")] int $bar): string
    {
        return 'success';
    }

    #[Query]
    public function testValidatorMultiple(#[Validate(for: 'foo', rule: 'starts_with:192|ipv4')]string $foo): string
    {
        return 'success';
    }

    #[Mutation]
    public function uploadFile(#[Validate(for: 'file', rule: 'file')] UploadedFileInterface $file): string
    {
        return 'success';
    }
}
