<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'order_item',
    idGenerator: EntityDefinition::ID_TYPE_UUID,
    description: 'Order item entity for benchmarking'
)]
class OrderItem
{
    use EntityTrait;

    public ?string $id = null;

    #[Field(type: 'string', required: true, indexed: true)]
    public string $productId;

    #[Field(type: 'string', required: true, indexed: true)]
    public string $orderId;

    #[Field(type: 'string', required: true)]
    public string $productName;

    #[Field(type: 'string', nullable: true)]
    public ?string $productSku = null;

    #[Field(type: 'float', required: true)]
    public float $price;

    #[Field(type: 'integer', required: true)]
    public int $quantity;

    #[Field(type: 'float', required: true)]
    public float $subtotal;

    #[Field(type: 'json', nullable: true)]
    public ?array $options = null;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Relationship(
        targetEntity: Product::class,
        type: Relationship::TYPE_MANY_TO_ONE
    )]
    private ?Product $product = null;

    #[Relationship(
        targetEntity: Order::class,
        type: Relationship::TYPE_MANY_TO_ONE,
        inversedBy: 'items'
    )]
    private ?Order $order = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and setters for the private properties

    public function getProduct(): ?Product
    {
        return $this->product;
    }

    public function setProduct(?Product $product): self
    {
        $this->product = $product;
        if ($product !== null) {
            $this->productId = $product->id;
            $this->productName = $product->name;
            $this->productSku = $product->sku;
            $this->price = $product->price;
            $this->updateSubtotal();
        }
        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;
        if ($order !== null) {
            $this->orderId = $order->id;
        }
        return $this;
    }

    public function updateSubtotal(): void
    {
        $this->subtotal = $this->price * $this->quantity;
    }

    public function setQuantity(int $quantity): self
    {
        $this->quantity = $quantity;
        $this->updateSubtotal();
        return $this;
    }
}