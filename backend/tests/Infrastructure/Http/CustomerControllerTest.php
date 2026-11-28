<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Http;

use App\Application\Customer\CustomerService;
use App\Application\Customer\CustomerMapper;
use App\Application\Common\Clock;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\SubscriptionStatus;
use App\Domain\Customer\SubscriptionStatusRepository;
use App\Domain\Customer\SubscriptionType;
use App\Domain\Customer\SubscriptionTypeRepository;
use App\Infrastructure\Http\CustomerController;
use PDOException;
use PHPUnit\Framework\TestCase;
use Slim\Psr7\Factory\ResponseFactory;
use Slim\Psr7\Factory\ServerRequestFactory;

final class CustomerControllerTest extends TestCase
{
    public function testCreateReturnsReadableDuplicateEmailMessage(): void
    {
        $customers = $this->createMock(CustomerRepository::class);
        $customers->method('save')->willThrowException(
            new PDOException(
                "SQLSTATE[23000]: Integrity constraint violation: 1062 Duplicate entry 'a@example.com' for key 'uk_customers_email'",
                23000,
            ),
        );
        $types = $this->createMock(SubscriptionTypeRepository::class);
        $types->method('findById')->willReturn(
            new SubscriptionType(1, 'Test', '3 MONTHS', 'Text'),
        );
        $status = new SubscriptionStatus(1, 'Bezahlt', null, null, null);
        $statuses = $this->createMock(SubscriptionStatusRepository::class);
        $statuses->method('findById')->willReturn($status);
        $statuses->method('all')->willReturn([$status]);
        $clock = $this->createMock(Clock::class);
        $clock->method('now')->willReturn(new \DateTimeImmutable('2026-01-01T00:00:00Z'));
        $service = new CustomerService(
            $customers,
            $types,
            $statuses,
            $clock,
            new CustomerMapper(),
        );
        $request = (new ServerRequestFactory())->createServerRequest('POST', '/api/customers')
            ->withParsedBody([
                'name' => 'Ada',
                'email' => 'a@example.com',
                'subscription_type_id' => 1,
                'start_date' => '2026-01-01',
            ]);

        $response = (new CustomerController($service))->create(
            $request,
            (new ResponseFactory())->createResponse(),
        );
        $body = json_decode((string) $response->getBody(), true, 512, JSON_THROW_ON_ERROR);

        self::assertSame(422, $response->getStatusCode());
        self::assertSame(
            'Bisher kann pro E-Mail nur ein Benutzer registriert werden.',
            $body['error'],
        );
    }
}
