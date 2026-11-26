<?php

declare(strict_types=1);

namespace App\Application\Customer;

use App\Domain\Customer\Customer;
use App\Domain\Customer\CustomerRepository;
use App\Domain\Customer\Subscription;
use App\Domain\Customer\SubscriptionType;
use App\Domain\Customer\SubscriptionTypeRepository;
use DateTimeImmutable;
use InvalidArgumentException;

final class CustomerService
{
    public function __construct(
        private readonly CustomerRepository $customers,
        private readonly SubscriptionTypeRepository $subscriptionTypes,
    ) {
    }

    /** @return list<array{id:int,name:string,duration:string,duration_months:int,reminder:string}> */
    public function subscriptionTypes(): array
    {
        return array_map(
            fn (SubscriptionType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'duration' => $type->duration,
                'duration_months' => $type->durationMonths(),
                'reminder' => $type->reminder,
            ],
            $this->subscriptionTypes->all(),
        );
    }

    /** @return list<array<string, mixed>> */
    public function all(): array
    {
        return array_map(fn (Customer $customer): array => $this->toArray($customer), $this->customers->all());
    }

    /** @return array{customers:list<array<string,mixed>>,pagination:array<string,int|string>} */
    public function paginate(
        int $page = 1,
        int $perPage = 25,
        string $sort = 'expires_at',
        string $direction = 'asc',
    ): array {
        $page = max(1, $page);
        $perPage = $perPage === 25 ? 25 : 25;
        $supported = ['name', 'email', 'subscription_type_id', 'start_date', 'expires_at', 'reminder_sent'];
        if (!in_array($sort, $supported, true)) {
            $sort = 'expires_at';
        }
        $direction = strtolower($direction) === 'desc' ? 'desc' : 'asc';

        $customers = array_map(fn (Customer $customer): array => $this->toArray($customer), $this->customers->all());
        usort($customers, static function (array $left, array $right) use ($sort, $direction): int {
            $leftValue = $left[$sort] ?? '';
            $rightValue = $right[$sort] ?? '';
            $comparison = $leftValue === null ? 1 : ($rightValue === null ? -1 : strcmp((string) $leftValue, (string) $rightValue));
            if ($comparison === 0) {
                $comparison = $left['id'] <=> $right['id'];
            }
            return $direction === 'desc' ? -$comparison : $comparison;
        });

        $total = count($customers);
        $offset = ($page - 1) * $perPage;
        $customers = array_slice($customers, $offset, $perPage);
        return [
            'customers' => $customers,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => max(1, (int) ceil($total / $perPage)),
                'sort' => $sort,
                'direction' => $direction,
            ],
        ];
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
        $type = $this->subscriptionType($input);
        $subscription = Subscription::create(
            $type->id,
            $type->durationMonths(),
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
        $type = $this->subscriptionType($input);
        $customer->changeSubscription(Subscription::reconstitute(
            (int) $existing->id(),
            $type->id,
            $type->durationMonths(),
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
            throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $key)) . ' is required');
        }
        return (int) $input[$key];
    }

    /** @param array<string, mixed> $input */
    private function subscriptionType(array $input): SubscriptionType
    {
        $type = $this->subscriptionTypes->findById($this->integer($input, 'subscription_type_id'));
        if ($type === null) {
            throw new InvalidArgumentException('Subscription type was not found');
        }
        return $type;
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
            'subscription_type_id' => $subscription->subscriptionTypeId(),
            'duration_months' => $subscription->durationMonths(),
            'start_date' => $subscription->startsAt()->format('Y-m-d'),
            'expires_at' => $subscription->expiresAt()->format('Y-m-d'),
            'reminder_sent' => $subscription->reminderSent()?->format('Y-m-d\TH:i:s.uP'),
        ];
    }
}
