<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'tag',
    idGenerator: EntityDefinition::ID_TYPE_UUID,
    description: 'Tag entity for benchmarking'
)]
class Tag
{
    use EntityTrait;

    public ?string $id = null;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $name;

    #[Field(type: 'string', indexed: true, nullable: true)]
    public ?string $slug = null;

    #[Field(type: 'string', nullable: true)]
    public ?string $color = null;

    #[Field(type: 'integer', indexed: true, required: true)]
    public int $counter = 0;

    #[Field(type: 'boolean', indexed: true, required: true)]
    public bool $isActive = true;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Relationship(
        targetEntity: Product::class,
        type: Relationship::TYPE_MANY_TO_MANY,
        joinTable: 'product_tags'
    )]
    private array $products = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and setters for the private properties

    public function getProducts(): array
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!in_array($product, $this->products, true)) {
            $this->products[] = $product;
            $this->counter++;
            $product->addTag($this);
        }
        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $key = array_search($product, $this->products, true);
        if ($key !== false) {
            unset($this->products[$key]);
            $this->products = array_values($this->products);
            $this->counter--;
            $product->removeTag($this);
        }
        return $this;
    }

    public function incrementCounter(): self
    {
        $this->counter++;
        return $this;
    }

    public function decrementCounter(): self
    {
        if ($this->counter > 0) {
            $this->counter--;
        }
        return $this;
    }
}