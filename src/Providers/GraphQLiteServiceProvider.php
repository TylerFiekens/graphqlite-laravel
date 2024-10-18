<?php

namespace TheCodingMachine\GraphQLite\Laravel\Providers;

use GraphQL\Error\DebugFlag;
use GraphQL\Server\ServerConfig;
use GraphQL\Server\StandardServer;
use GraphQL\Type\Schema as WebonyxSchema;
use GraphQL\Validator\DocumentValidator;
use GraphQL\Validator\Rules\DisableIntrospection;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;
use Laminas\Diactoros\ResponseFactory;
use Laminas\Diactoros\ServerRequestFactory;
use Laminas\Diactoros\StreamFactory;
use Laminas\Diactoros\UploadedFileFactory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\Cache\Adapter\ApcuAdapter;
use Symfony\Component\Cache\Adapter\PhpFilesAdapter;
use Symfony\Component\Cache\Psr16Cache;
use TheCodingMachine\GraphQLite\Context\Context;
use TheCodingMachine\GraphQLite\Exceptions\WebonyxErrorHandler;
use TheCodingMachine\GraphQLite\Http\HttpCodeDecider;
use TheCodingMachine\GraphQLite\Http\HttpCodeDeciderInterface;
use TheCodingMachine\GraphQLite\Laravel\Console\Commands\GraphqliteExportSchema;
use TheCodingMachine\GraphQLite\Laravel\Controllers\GraphQLiteController;
use TheCodingMachine\GraphQLite\Laravel\Listeners\CachePurger;
use TheCodingMachine\GraphQLite\Laravel\Mappers\PaginatorTypeMapperFactory;
use TheCodingMachine\GraphQLite\Laravel\Mappers\Parameters\ValidateFieldMiddleware;
use TheCodingMachine\GraphQLite\Laravel\SanePsr11ContainerAdapter;
use TheCodingMachine\GraphQLite\Laravel\Security\AuthenticationService;
use TheCodingMachine\GraphQLite\Laravel\Security\AuthorizationService;
use TheCodingMachine\GraphQLite\Schema;
use TheCodingMachine\GraphQLite\SchemaFactory;
use TheCodingMachine\GraphQLite\Security\AuthenticationServiceInterface;

use function config;
use function extension_loaded;
use function ini_get;
use function is_array;
use function is_iterable;

class GraphQLiteServiceProvider extends ServiceProvider
{
    public function boot(Dispatcher $events): void
    {
        $this->publishes([
            __DIR__.'/../../config/graphqlite.php' => config_path('graphqlite.php'),
        ], 'config');

        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');
        $events->listen('cache:clearing', CachePurger::class);
    }

    public function register(): void
    {
        $this->commands([
            GraphqliteExportSchema::class,
        ]);

        $this->app->bind(WebonyxSchema::class, Schema::class);

        if (! $this->app->has(ServerRequestFactoryInterface::class)) {
            $this->app->bind(ServerRequestFactoryInterface::class, ServerRequestFactory::class);
        }
        if (! $this->app->has(StreamFactoryInterface::class)) {
            $this->app->bind(StreamFactoryInterface::class, StreamFactory::class);
        }
        if (! $this->app->has(UploadedFileFactoryInterface::class)) {
            $this->app->bind(UploadedFileFactoryInterface::class, UploadedFileFactory::class);
        }
        if (! $this->app->has(ResponseFactoryInterface::class)) {
            $this->app->bind(ResponseFactoryInterface::class, ResponseFactory::class);
        }
        if (! $this->app->has(HttpCodeDeciderInterface::class)) {
            $this->app->bind(HttpCodeDeciderInterface::class, HttpCodeDecider::class);
        }

        $this->app->bind(HttpMessageFactoryInterface::class, PsrHttpFactory::class);

        $this->app->singleton(GraphQLiteController::class, function (Application $app) {
            $debug = config('graphqlite.debug', DebugFlag::RETHROW_UNSAFE_EXCEPTIONS);
            $decider = config('graphqlite.http_code_decider');
            if (! $decider) {
                $httpCodeDecider = $app[HttpCodeDeciderInterface::class];
            } else {
                $httpCodeDecider = $app[$decider];
            }

            return new GraphQLiteController($app[StandardServer::class], $httpCodeDecider, $app[HttpMessageFactoryInterface::class], $debug);
        });

        $this->app->singleton(StandardServer::class, static function (Application $app) {
            return new StandardServer($app[ServerConfig::class]);
        });

        $this->app->singleton(ServerConfig::class, static function (Application $app) {
            $serverConfig = new ServerConfig;
            $serverConfig->setSchema($app[Schema::class]);
            $serverConfig->setErrorFormatter([WebonyxErrorHandler::class, 'errorFormatter']);
            $serverConfig->setErrorsHandler([WebonyxErrorHandler::class, 'errorHandler']);
            $serverConfig->setContext(new Context);

            return $serverConfig;
        });

        $this->app->singleton('graphqliteCache', static function () {
            if (extension_loaded('apcu') && ini_get('apc.enabled')) {
                return new Psr16Cache(new ApcuAdapter);
            } else {
                return new Psr16Cache(new PhpFilesAdapter);
            }
        });

        $this->app->singleton(CachePurger::class, static function (Application $app) {
            return new CachePurger($app['graphqliteCache']);
        });

        $this->app->singleton(AuthenticationService::class, function (Application $app) {
            $guard = config('graphqlite.guard', $this->app['config']['auth.defaults.guard']);
            if (! is_array($guard)) {
                $guard = [$guard];
            }

            return new AuthenticationService($app[AuthFactory::class], $guard);
        });

        $this->app->bind(AuthenticationServiceInterface::class, AuthenticationService::class);

        $this->app->singleton(SchemaFactory::class, function (Application $app) {
            $service = new SchemaFactory($app->make('graphqliteCache'), new SanePsr11ContainerAdapter($app));
            $service->setAuthenticationService($app[AuthenticationService::class]);
            $service->setAuthorizationService($app[AuthorizationService::class]);
            $service->addParameterMiddleware($app[ValidateFieldMiddleware::class]);
            $service->addTypeMapperFactory($app[PaginatorTypeMapperFactory::class]);

            $queries = config('graphqlite.queries', 'App\\GraphQL\\Queries');
            if (! is_iterable($queries)) {
                $queries = [$queries];
            }
            foreach ($queries as $namespace) {
                $service->addControllerNamespace($namespace);
            }

            $mutations = config('graphqlite.mutations', 'App\\GraphQL\\Mutations');
            if (! is_iterable($mutations)) {
                $mutations = [$mutations];
            }
            foreach ($mutations as $namespace) {
                $service->addControllerNamespace($namespace);
            }

            $types = config('graphqlite.types', 'App\\GraphQL\\Types');
            if (! is_iterable($types)) {
                $types = [$types];
            }
            foreach ($types as $namespace) {
                $service->addTypeNamespace($namespace);
            }

            if ($this->app->environment('production')) {
                $service->prodMode();
            }

            return $service;
        });

        $this->app->singleton(Schema::class, function (Application $app) {
            /** @var SchemaFactory $schemaFactory */
            $schemaFactory = $app->make(SchemaFactory::class);

            return $schemaFactory->createSchema();
        });

        DocumentValidator::addRule(new DisableIntrospection(config('graphqlite.disable_introspection', false)));
    }

    public function provides(): array
    {
        return [
            SchemaFactory::class,
            Schema::class,
        ];
    }
}
