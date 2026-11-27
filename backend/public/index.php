<?php

declare(strict_types=1);

use App\Infrastructure\Http\HealthController;
use App\Application\Customer\CustomerService;
use App\Application\Customer\CustomerMapper;
use App\Infrastructure\Clock\SystemClock;
use App\Infrastructure\Database\PdoCustomerRepository;
use App\Infrastructure\Database\PdoFactory;
use App\Infrastructure\Database\PdoSubscriptionTypeRepository;
use App\Infrastructure\Database\PdoSubscriptionStatusRepository;
use App\Infrastructure\Http\CustomerController;
use App\Infrastructure\Http\AuthController;
use App\Infrastructure\Http\SubscriptionLifecycleController;
use App\Infrastructure\Auth\SessionAuth;
use App\Infrastructure\Auth\Csrf;
use App\Application\Reminder\ErrorLogReminderLogger;
use App\Application\Customer\SubscriptionLifecycleService;
use App\Infrastructure\Reminder\PhpmailerReminderMailer;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
$secureCookie = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
ini_set('session.cookie_secure', $secureCookie ? '1' : '0');
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $secureCookie,
    'httponly' => true,
    'samesite' => 'Lax',
]);
session_start();

$app = AppFactory::create();
$app->addBodyParsingMiddleware();
$app->add(new Csrf());

$app->get('/api/health', HealthController::class);
$authController = new AuthController();
$app->get('/api/auth/session', [$authController, 'session']);
$app->post('/api/auth/login', [$authController, 'login']);
$app->post('/api/auth/logout', [$authController, 'logout']);

$pdo = PdoFactory::create();
$customerRepository = new PdoCustomerRepository($pdo);
$clock = new SystemClock();
$customerService = new CustomerService(
    $customerRepository,
    new PdoSubscriptionTypeRepository($pdo),
    new PdoSubscriptionStatusRepository($pdo),
    $clock,
    new CustomerMapper(),
);
$customerController = new CustomerController(
    $customerService,
);
$mailer = new PhpmailerReminderMailer(
    getenv('SMTP_HOST') ?: '',
    (int) (getenv('SMTP_PORT') ?: 587),
    getenv('SMTP_USER') ?: '',
    getenv('SMTP_PASSWORD') ?: '',
    getenv('SMTP_FROM') ?: '',
    getenv('SMTP_FROM_NAME') ?: 'Ernesto',
    getenv('ADMIN_EMAIL') ?: '',
    getenv('SMTP_SECURE') ?: 'tls',
);
$logger = new ErrorLogReminderLogger();
$lifecycleController = new SubscriptionLifecycleController(
    new SubscriptionLifecycleService($customerRepository, $mailer, $logger),
    $customerService,
    $clock,
);

$app->get('/api/customers', [$customerController, 'list'])->add(new SessionAuth());
$app->get('/api/subscription-types', [$customerController, 'listSubscriptionTypes'])->add(new SessionAuth());
$app->get('/api/subscription-statuses', [$customerController, 'listSubscriptionStatuses'])->add(new SessionAuth());
$app->post('/api/customers', [$customerController, 'create'])->add(new SessionAuth());
$app->get('/api/customers/{id}', [$customerController, 'get'])->add(new SessionAuth());
$app->put('/api/customers/{id}', [$customerController, 'update'])->add(new SessionAuth());
$app->post('/api/customers/{id}/renew', [$customerController, 'renew'])->add(new SessionAuth());
$app->delete('/api/customers/{id}', [$customerController, 'delete'])->add(new SessionAuth());
$app->post('/api/customers/{id}/status-email', [$lifecycleController, 'resend'])->add(new SessionAuth());
$app->run();
