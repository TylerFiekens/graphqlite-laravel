<?php


namespace TheCodingMachine\GraphQLite\Laravel\Controllers;


use GraphQL\Upload\UploadMiddleware;
use Illuminate\Http\Request;
use GraphQL\Error\DebugFlag;
use GraphQL\Executor\ExecutionResult;
use GraphQL\Executor\Promise\Promise;
use GraphQL\Server\StandardServer;
use Laminas\Diactoros\ServerRequestFactory;
use TheCodingMachine\GraphQLite\Http\HttpCodeDeciderInterface;
use function array_map;
use function json_decode;
use function json_last_error;
use Psr\Http\Message\ServerRequestInterface;
use RuntimeException;
use Symfony\Bridge\PsrHttpMessage\Factory\DiactorosFactory;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use function max;


class GraphQLiteController
{
    /**
     * @var HttpMessageFactoryInterface
     */
    private $httpMessageFactory;
    /** @var StandardServer */
    private $standardServer;
    /** @var bool|int */
    private $debug;
    /** @var HttpCodeDeciderInterface */
    private $httpCodeDecider;

    public function __construct(StandardServer $standardServer, HttpCodeDeciderInterface $httpCodeDecider, HttpMessageFactoryInterface $httpMessageFactory = null, ?int $debug = DebugFlag::RETHROW_UNSAFE_EXCEPTIONS)
    {
        $this->standardServer = $standardServer;
        $this->httpCodeDecider = $httpCodeDecider;
        $this->httpMessageFactory = $httpMessageFactory ?: new DiactorosFactory();
        $this->debug = $debug === null ? false : $debug;
    }

    public function index(Request $request): JsonResponse
    {
        if (empty($request->all())) {
            return response()->json([
                'errors' => [
                    [
                        'message' => 'POST body is empty'
                    ],
                ],
            ], 400);
        }

        if ($request->files->count() > 0) {
            return response()->json([
                'errors' => [
                    [
                        'message' => 'File uploads are not supported. Sorry, I was not able to find a way to do that in Laravel. Help would be appreciated!'
                    ],
                ],
            ], 400);
        }

        $psr7Request = $this->httpMessageFactory->createRequest($request);
        return $this->handlePsr7Request($psr7Request);
    }

    private function handlePsr7Request(ServerRequestInterface $request): JsonResponse
    {
        $result = $this->standardServer->executePsrRequest($request);
        $httpCodeDecider = $this->httpCodeDecider;
        if ($result instanceof ExecutionResult) {
            return response()->json($result->toArray($this->debug), $httpCodeDecider->decideHttpStatusCode($result));
        }

        if (is_array($result)) {
            $finalResult =  array_map(function (ExecutionResult $executionResult) {
                return new JsonResponse($executionResult->toArray($this->debug));
            }, $result);

            $statuses = array_map([$httpCodeDecider, 'decideHttpStatusCode'], $result);
            $status = max($statuses);
            return new JsonResponse($finalResult, $status);
        }

        if ($result instanceof Promise) {
            throw new RuntimeException('Only SyncPromiseAdapter is supported');
        }

        throw new RuntimeException('Unexpected response from StandardServer::executePsrRequest'); // @codeCoverageIgnore
    }
}
