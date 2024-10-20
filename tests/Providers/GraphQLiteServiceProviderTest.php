<?php

namespace TheCodingMachine\GraphQLite\Laravel\Providers;

use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Server\StandardServer;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use TheCodingMachine\GraphQLite\Http\HttpCodeDeciderInterface;
use TheCodingMachine\GraphQLite\Laravel\Controllers\GraphQLiteController;
use TheCodingMachine\GraphQLite\Laravel\Listeners\CachePurger;
use TheCodingMachine\GraphQLite\Schema;

class GraphQLiteServiceProviderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [GraphQLiteServiceProvider::class];
    }

    public function testServiceProvider()
    {
        $schema = $this->app->make(Schema::class);
        $this->assertInstanceOf(Schema::class, $schema);
    }

    public function testHttpQuery()
    {
        $response = $this->json('POST', '/graphql', ['query' => '{ test }']);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $response->assertJson(['data' => ['test' => 'foo']]);
    }

    public function testAuthentication()
    {
        $response = $this->json('POST', '/graphql', ['query' => '{ testLogged }']);
        $this->assertSame(401, $response->getStatusCode(), $response->getContent());
        $response->assertJson(['errors' => [['message' => 'You need to be logged to access this field']]]);
    }

    public function testPagination()
    {
        $response = $this->json('POST', '/graphql', ['query' => <<<'GQL'
{
    testPaginator {
        items
        firstItem
        lastItem
        hasMorePages
        perPage
        hasPages
        currentPage
        isEmpty
        isNotEmpty
        totalCount
        lastPage
    }
}
GQL
        ]);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
        $response->assertJson([
            'data' => [
                'testPaginator' => [
                    'items' => [
                        1,
                        2,
                        3,
                        4,
                    ],
                    'firstItem' => 5,
                    'lastItem' => 8,
                    'hasMorePages' => true,
                    'perPage' => 4,
                    'hasPages' => true,
                    'currentPage' => 2,
                    'isEmpty' => false,
                    'isNotEmpty' => true,
                    'totalCount' => 42,
                    'lastPage' => 11,
                ],
            ],
        ]);
    }

    public function testValidator()
    {
        $response = $this->json('POST', '/graphql', ['query' => '{ testValidator(foo:"a", bar:0) }']);
        $response->assertJson([
            'errors' => [
                [
                    'extensions' => [
                        'argument' => 'foo',
                    ],
                ],
                [
                    'extensions' => [
                        'argument' => 'bar',
                    ],
                ],
            ],
        ]);
        $this->assertStringContainsString('must be a valid email address.', $response->json('errors')[0]['message']);
        $this->assertStringContainsString('must be greater than 42.', $response->json('errors')[1]['message']);

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());
    }

    public function testValidatorMultiple()
    {
        $response = $this->json('POST', '/graphql', ['query' => '{ testValidatorMultiple(foo:"191.168.1") }']);
        $response->assertJson([
            'errors' => [
                [
                    'extensions' => [
                        'argument' => 'foo',
                    ],
                ],
                [
                    'extensions' => [
                        'argument' => 'foo',
                    ],
                ],
            ],
        ]);

        $this->assertStringContainsString('must start with one of the following: 192', $response->json('errors')[0]['message']);
        $this->assertStringContainsString('must be a valid IPv4 address.', $response->json('errors')[1]['message']);

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());
        $response = $this->json('POST', '/graphql', ['query' => '{ testValidatorMultiple(foo:"192.168.1") }']);
        $response->assertJson([
            'errors' => [
                [
                    'extensions' => [
                        'argument' => 'foo',
                    ],
                ],
            ],
        ]);
        $this->assertStringContainsString('must be a valid IPv4 address.', $response->json('errors')[0]['message']);

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());
        $response = $this->json('POST', '/graphql', ['query' => '{ testValidatorMultiple(foo:"191.168.1.1") }']);
        $response->assertJson([
            'errors' => [
                [
                    'extensions' => [
                        'argument' => 'foo',
                    ],
                ],
            ],
        ]);
        $this->assertStringContainsString('must start with one of the following: 192', $response->json('errors')[0]['message']);

        $this->assertSame(400, $response->getStatusCode(), $response->getContent());

        $response = $this->json('POST', '/graphql', ['query' => '{ testValidatorMultiple(foo:"192.168.1.1") }']);
        $this->assertSame(200, $response->getStatusCode(), $response->getContent());
    }

    public function testCachePurger(): void
    {
        $cachePurger = $this->app->make(CachePurger::class);
        $cachePurger->handle();
        $this->assertTrue(true);
    }

    /**
     * Asserts that the status code has been taken from the HttpCodeDeciderInterface.
     */
    public function testChangeTheCodeDecider()
    {
        $this->app->instance(HttpCodeDeciderInterface::class, $this->newCodeDecider(418));

        $controller = $this->newGraphQLiteController();

        $response = $controller->index($this->newRequest());

        $this->assertEquals(418, $response->getStatusCode());
    }

    private function newCodeDecider(int $statusCode): HttpCodeDeciderInterface
    {
        return new class implements HttpCodeDeciderInterface
        {
            public function decideHttpStatusCode(ExecutionResult $result): int
            {
                return 418;
            }
        };
    }

    private function newGraphQLiteController(): GraphQLiteController
    {
        $server = $this->app->make(StandardServer::class);
        $httpCodeDecider = $this->app->make(HttpCodeDeciderInterface::class);
        $messageFactory = $this->app->make(PsrHttpFactory::class);

        return new GraphQLiteController($server, $httpCodeDecider, $messageFactory, DebugFlag::RETHROW_UNSAFE_EXCEPTIONS);
    }

    private function newRequest(): Request
    {
        $baseRequest = SymfonyRequest::create('https://localhost', 'GET', [
            'query' => '{ testValidatorMultiple(foo:"192.168.1.1") }',
        ]);

        return Request::createFromBase($baseRequest);
    }
}
