<?php

namespace Tests\Feature;

use Illuminate\Http\UploadedFile;
use Orchestra\Testbench\TestCase;
use TheCodingMachine\GraphQLite\Laravel\Providers\GraphQLiteServiceProvider;

class GraphqlTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [GraphQLiteServiceProvider::class];
    }

    public function test_it_needs_to_be_a_post_request(): void
    {
        $this->get(route('graphqlite.index'))->assertStatus(405);
    }

    public function test_it_must_have_a_valid_json_body(): void
    {
        $this
            ->post(route('graphqlite.index'))
            ->assertStatus(400)
            ->assertJson([
                'errors' => [
                    [
                        'message' => 'POST body is empty'
                    ],
                ],
            ]);
    }

    public function test_it_returns_an_error_for_files_without_a_json_body(): void
    {
        $this
            ->post(route('graphqlite.index'), [
                'file' => UploadedFile::fake()->image('avatar.jpg')
            ])
            ->assertStatus(400)
            ->assertJson([
                'errors' => [
                    [
                        'message' => 'File uploads are not supported. Sorry, I was not able to find a way to do that in Laravel. Help would be appreciated!'
                    ],
                ],
            ]);
    }

    public function test_it_returns_correct_data_for_simple_query(): void
    {
        $this
            ->post(route('graphqlite.index'), [
                'query' => 'query { test }'
            ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'test' => 'foo'
                ]
            ]);
    }

    public function test_it_can_handle_multiple_queries(): void
    {
        $this
            ->post(route('graphqlite.index'), [
                'query' => 'query {
                    test
                    testInt
                }'
            ])
            ->assertOk()
            ->assertJson([
                'data' => [
                    'test' => 'foo',
                    'testInt' => 42
                ]
            ]);
    }
}