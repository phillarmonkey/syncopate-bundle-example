<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'order',
    idGenerator: EntityDefinition::ID_TYPE_UUID,
    description: 'Order entity for benchmarking'
)]
class Order
{
    use EntityTrait;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public ?string $id = null;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $orderNumber;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $status = self::STATUS_PENDING;

    #[Field(type: 'float', indexed: true, required: true)]
    public float $totalAmount = 0.0;

    #[Field(type: 'string', required: true, indexed: true)]
    public string $userId;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Field(type: 'datetime', nullable: true)]
    public ?DateTimeInterface $completedAt = null;

    #[Field(type: 'json', nullable: true)]
    public ?array $shippingAddress = null;

    #[Field(type: 'json', nullable: true)]
    public ?array $billingAddress = null;

    #[Relationship(
        targetEntity: User::class,
        type: Relationship::TYPE_MANY_TO_ONE,
        inversedBy: 'orders'
    )]
    private ?User $user = null;

    #[Relationship(
        targetEntity: OrderItem::class,
        type: Relationship::TYPE_ONE_TO_MANY,
        mappedBy: 'order',
        cascade: Relationship::CASCADE_REMOVE
    )]
    private array $items = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->orderNumber = 'ORD-' . uniqid();
    }

    // Getters and setters for the private properties

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;
        if ($user !== null) {
            $this->userId = $user->id;
        }
        return $this;
    }

    public function getItems(): array
    {
        return $this->items;
    }

    public function addItem(OrderItem $item): self
    {
        if (!in_array($item, $this->items, true)) {
            $this->items[] = $item;
            $item->setOrder($this);
            $this->recalculateTotal();
        }
        return $this;
    }

    public function removeItem(OrderItem $item): self
    {
        $key = array_search($item, $this->items, true);
        if ($key !== false) {
            unset($this->items[$key]);
            $this->items = array_values($this->items);
            $item->setOrder(null);
            $this->recalculateTotal();
        }
        return $this;
    }

    public function complete(): self
    {
        $this->status = self::STATUS_COMPLETED;
        $this->completedAt = new \DateTime();
        return $this;
    }

    public function cancel(): self
    {
        $this->status = self::STATUS_CANCELLED;
        return $this;
    }

    public function recalculateTotal(): void
    {
        $this->totalAmount = 0.0;
        foreach ($this->items as $item) {
            $this->totalAmount += ($item->price * $item->quantity);
        }
    }
}