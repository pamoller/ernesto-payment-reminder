<?php

declare(strict_types=1);

use App\Infrastructure\Http\HealthController;
use App\Application\Customer\CustomerService;
use App\Infrastructure\Database\PdoCustomerRepository;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Http\CustomerController;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->get('/api/health', HealthController::class);
$pdo = PdoFactory::create();
$customerController = new CustomerController(
    new CustomerService(new PdoCustomerRepository($pdo)),
);

$app->get('/api/customers', [$customerController, 'list']);
$app->post('/api/customers', [$customerController, 'create']);
$app->get('/api/customers/{id}', [$customerController, 'get']);
$app->put('/api/customers/{id}', [$customerController, 'update']);
$app->delete('/api/customers/{id}', [$customerController, 'delete']);
$app->run();
