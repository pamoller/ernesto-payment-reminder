<?php

declare(strict_types=1);

use App\Infrastructure\Http\HealthController;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$app = AppFactory::create();
$app->addBodyParsingMiddleware();

$app->get('/api/health', HealthController::class);
$app->run();
