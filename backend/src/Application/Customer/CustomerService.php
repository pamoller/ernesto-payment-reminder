<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use DateTimeImmutable;
use InvalidArgumentException;

final class CustomerService
{
    public function __construct(private readonly CustomerRepository $customers)
    {
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map(fn (Customer $customer): array => $this->toArray($customer), $this->customers->all());
    }

    /** @return array<string, mixed>|null */
    public function find(int $id): ?array
    {
        $customer = $this->customers->findById($id);
        return $customer === null ? null : $this->toArray($customer);
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>
     */
    public function create(array $input): array
    {
        $subscription = Subscription::create(
            $this->integer($input, 'duration_months'),
            $this->startDate($input),
        );
        $customer = Customer::create(
            $this->string($input, 'name'),
            $this->string($input, 'email'),
            $subscription,
        );
        return $this->toArray($this->customers->save($customer));
    }

    /** @param array<string, mixed> $input
     *  @return array<string, mixed>|null
     */
    public function update(int $id, array $input): ?array
    {
        $customer = $this->customers->findById($id);
        if ($customer === null) {
            return null;
        }

        $customer->rename($this->string($input, 'name'));
        $customer->changeEmail($this->string($input, 'email'));
        $existing = $customer->subscription();
        $customer->changeSubscription(Subscription::reconstitute(
            (int) $existing->id(),
            $this->integer($input, 'duration_months'),
            $this->startDate($input),
            $existing->reminderSent(),
        ));

        return $this->toArray($this->customers->save($customer));
    }

    public function delete(int $id): bool
    {
        return $this->customers->delete($id);
    }

    /** @param array<string, mixed> $input */
    private function string(array $input, string $key): string
    {
        if (!isset($input[$key]) || !is_string($input[$key])) {
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' is required');
        }
        return $input[$key];
    }

    /** @param array<string, mixed> $input */
    private function integer(array $input, string $key): int
    {
        if (!isset($input[$key]) || filter_var($input[$key], FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Duration months is required');
        }
        return (int) $input[$key];
    }

    /** @param array<string, mixed> $input */
    private function startDate(array $input): DateTimeImmutable
    {
        if (!isset($input['start_date']) || !is_string($input['start_date'])) {
            throw new InvalidArgumentException('Start date is required');
        }
        $date = DateTimeImmutable::createFromFormat('Y-m-d', $input['start_date'], new \DateTimeZone('UTC'));
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            throw new InvalidArgumentException('Start date must use YYYY-MM-DD');
        }
        return $date;
    }

    /** @return array<string, mixed> */
    private function toArray(Customer $customer): array
    {
        $subscription = $customer->subscription();
        return [
            'id' => $customer->id(),
            'name' => $customer->name(),
            'email' => $customer->email(),
            'duration_months' => $subscription->durationMonths(),
            'start_date' => $subscription->startsAt()->format('Y-m-d'),
            'expires_at' => $subscription->expiresAt()->format('Y-m-d'),
            'reminder_sent' => $subscription->reminderSent()?->format(DATE_ATOM),
        ];
    }
}
