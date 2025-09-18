<?php

/**
 * Example usage of the Kode Attributes package
 */

// Include the autoloader
require_once 'vendor/autoload.php';

// Import the classes
use Kode\Attributes\Attr;
use Kode\Attributes\Reader;
use Kode\Attributes\ArrayCache;

// Define a sample attribute
#[Attribute(Attribute::IS_REPEATABLE | Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Route
{
    public function __construct(
        public readonly string $path,
        public readonly string $method = 'GET'
    ) {
    }
}

// Define a sample controller with attributes
#[Route('/api/users', 'GET')]
#[Route('/api/users', 'POST')]
class UserController
{
    #[Route('/api/users/{id}', 'GET')]
    public function getUser(int $id): void
    {
        // Method implementation
    }
    
    #[Route('/api/users/{id}', 'PUT')]
    public function updateUser(int $id): void
    {
        // Method implementation
    }
}

// Example 1: Using the Attr facade (simple usage)
echo "=== Using Attr facade ===\n";

$routes = Attr::of(UserController::class);
echo "UserController has " . count($routes) . " route attributes\n";

foreach ($routes as $meta) {
    $route = $meta->getInstance();
    echo "Route: {$route->method} {$route->path}\n";
}

// Example 2: Using the Reader directly (advanced usage)
echo "\n=== Using Reader directly ===\n";

$reader = new Reader(new ArrayCache());

// Get class attributes
$classRoutes = $reader->getClassAttrs(UserController::class);
echo "Class routes: " . count($classRoutes) . "\n";

// Get method attributes
$methodRoutes = $reader->getMethodAttrs(UserController::class, 'getUser');
echo "Method routes for getUser: " . count($methodRoutes) . "\n";

// Example 3: Filtering and mapping
echo "\n=== Filtering and mapping ===\n";

// Filter routes by method
$postRoutes = $routes->filter(fn($meta) => $meta->getInstance()->method === 'POST');
echo "POST routes: " . count($postRoutes) . "\n";

// Map routes to path strings
$paths = $routes->map(fn($meta) => $meta->getInstance()->path);
echo "All paths: " . implode(', ', $paths) . "\n";

// Example 4: Checking for specific attributes
echo "\n=== Checking for specific attributes ===\n";

if (Attr::has(UserController::class, Route::class)) {
    echo "UserController has Route attributes\n";
    
    $route = Attr::get(UserController::class, Route::class);
    if ($route) {
        $instance = $route->getInstance();
        echo "First route: {$instance->method} {$instance->path}\n";
    }
}