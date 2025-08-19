<?php

declare(strict_types=1);

namespace FSM\Infrastructure\Transport\Rest;

use OpenSwoole\HTTP\Server;
use OpenSwoole\HTTP\Request;
use OpenSwoole\HTTP\Response;
use FSM\Application\Handler\CreateFSMHandler;
use FSM\Application\Handler\ExecuteFSMHandler;
use FSM\Application\Handler\GetFSMStateHandler;
use FSM\Application\Service\FSMExecutor;
use FSM\Infrastructure\Persistence\InMemoryFSMRepository;
use FSM\Infrastructure\Event\NullEventDispatcher;

/**
 * REST API server using OpenSwoole
 */
final class RestServer
{
    private Server $server;
    private FSMController $controller;
    private Router $router;
    
    public function __construct(
        string $host = '0.0.0.0',
        int $port = 8080
    ) {
        $this->server = new Server($host, $port);
        $this->initializeController();
        $this->initializeRouter();
        $this->configureServer();
    }
    
    private function initializeController(): void
    {
        // Initialize dependencies
        $repository = new InMemoryFSMRepository();
        $eventDispatcher = new NullEventDispatcher();
        $executor = new FSMExecutor();
        
        // Create handlers
        $createHandler = new CreateFSMHandler($repository, $eventDispatcher);
        $executeHandler = new ExecuteFSMHandler($repository, $executor);
        $getStateHandler = new GetFSMStateHandler($repository);
        
        // Create controller
        $this->controller = new FSMController(
            $createHandler,
            $executeHandler,
            $getStateHandler
        );
    }
    
    private function initializeRouter(): void
    {
        $this->router = new Router();
        
        // Define routes
        $this->router->post('/api/fsm', [$this->controller, 'createFSM']);
        $this->router->post('/api/fsm/{id}/execute', [$this->controller, 'execute']);
        $this->router->get('/api/fsm/{id}/state', [$this->controller, 'getState']);
        $this->router->post('/api/fsm/{id}/batch', [$this->controller, 'executeBatch']);
        
        // Health check
        $this->router->get('/health', function() {
            return ['status' => 'healthy', 'service' => 'fsm-rest-api'];
        });
    }
    
    private function configureServer(): void
    {
        $this->server->set([
            'worker_num' => 4,
            'task_worker_num' => 2,
            'enable_coroutine' => true,
            'log_level' => SWOOLE_LOG_WARNING,
        ]);
        
        $this->server->on('request', [$this, 'handleRequest']);
    }
    
    public function handleRequest(Request $request, Response $response): void
    {
        // Set CORS headers
        $response->header('Access-Control-Allow-Origin', '*');
        $response->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS');
        $response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
        $response->header('Content-Type', 'application/json');
        
        // Handle OPTIONS requests
        if ($request->server['request_method'] === 'OPTIONS') {
            $response->status(200);
            $response->end();
            return;
        }
        
        try {
            // Route the request
            $result = $this->router->dispatch(
                $request->server['request_method'],
                $request->server['request_uri'],
                $request->get ?? [],
                $this->parseRequestBody($request)
            );
            
            // Send response
            $response->status($result['status'] ?? 200);
            $response->end(json_encode($result['body']));
            
        } catch (\Exception $e) {
            $response->status(500);
            $response->end(json_encode([
                'status' => 'error',
                'error' => [
                    'message' => 'Internal server error',
                    'details' => $e->getMessage()
                ]
            ]));
        }
    }
    
    private function parseRequestBody(Request $request): array
    {
        if (empty($request->rawContent())) {
            return [];
        }
        
        $contentType = $request->header['content-type'] ?? '';
        
        if (str_contains($contentType, 'application/json')) {
            return json_decode($request->rawContent(), true) ?? [];
        }
        
        return $request->post ?? [];
    }
    
    public function start(): void
    {
        echo "FSM REST API Server starting on http://{$this->server->host}:{$this->server->port}\n";
        echo "Available endpoints:\n";
        echo "  POST   /api/fsm                 - Create new FSM\n";
        echo "  POST   /api/fsm/{id}/execute    - Execute FSM\n";
        echo "  GET    /api/fsm/{id}/state      - Get FSM state\n";
        echo "  POST   /api/fsm/{id}/batch      - Batch execute\n";
        echo "  GET    /health                  - Health check\n\n";
        
        $this->server->start();
    }
}

/**
 * Simple router for REST endpoints
 */
final class Router
{
    private array $routes = [];
    
    public function get(string $pattern, callable $handler): void
    {
        $this->addRoute('GET', $pattern, $handler);
    }
    
    public function post(string $pattern, callable $handler): void
    {
        $this->addRoute('POST', $pattern, $handler);
    }
    
    private function addRoute(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }
    
    public function dispatch(string $method, string $uri, array $query, array $body): array
    {
        // Remove query string from URI
        $uri = parse_url($uri, PHP_URL_PATH);
        
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }
            
            $params = $this->matchPattern($route['pattern'], $uri);
            if ($params !== null) {
                $handler = $route['handler'];
                
                if (is_array($handler)) {
                    // Controller method
                    [$controller, $action] = $handler;
                    
                    // Determine parameters based on action
                    if ($action === 'createFSM') {
                        $result = $controller->$action($body);
                    } elseif ($action === 'execute' || $action === 'executeBatch') {
                        $result = $controller->$action($params['id'], $body);
                    } elseif ($action === 'getState') {
                        $result = $controller->$action($params['id'], $query);
                    } else {
                        $result = $controller->$action($params, $body);
                    }
                } else {
                    // Closure
                    $result = $handler($params, $body, $query);
                }
                
                // Determine status code from result
                $status = 200;
                if (isset($result['error'])) {
                    $status = $result['error']['code'] ?? 500;
                }
                
                return [
                    'status' => $status,
                    'body' => $result
                ];
            }
        }
        
        return [
            'status' => 404,
            'body' => [
                'status' => 'error',
                'error' => [
                    'message' => 'Route not found',
                    'code' => 404
                ]
            ]
        ];
    }
    
    private function matchPattern(string $pattern, string $uri): ?array
    {
        // Convert pattern to regex
        $regex = preg_replace('/\{(\w+)\}/', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        if (preg_match($regex, $uri, $matches)) {
            // Extract named parameters
            $params = [];
            foreach ($matches as $key => $value) {
                if (!is_numeric($key)) {
                    $params[$key] = $value;
                }
            }
            return $params;
        }
        
        return null;
    }
}