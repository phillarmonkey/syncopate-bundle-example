<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'category',
    idGenerator: EntityDefinition::ID_TYPE_AUTO_INCREMENT,
    description: 'Product category entity for benchmarking'
)]
class Category
{
    use EntityTrait;

    public ?string $id = null;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $name;

    #[Field(type: 'string', nullable: true)]
    public ?string $description = null;

    #[Field(type: 'string', nullable: true)]
    public ?string $slug = null;

    #[Field(type: 'string', nullable: true)]
    public ?string $parentId = null;

    #[Field(type: 'integer', indexed: true, required: true)]
    public int $position = 0;

    #[Field(type: 'boolean', indexed: true, required: true)]
    public bool $isActive = true;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Relationship(
        targetEntity: Category::class,
        type: Relationship::TYPE_MANY_TO_ONE,
        inversedBy: 'children'
    )]
    private ?Category $parent = null;

    #[Relationship(
        targetEntity: Category::class,
        type: Relationship::TYPE_ONE_TO_MANY,
        mappedBy: 'parent',
        cascade: Relationship::CASCADE_REMOVE
    )]
    private array $children = [];

    #[Relationship(
        targetEntity: Product::class,
        type: Relationship::TYPE_ONE_TO_MANY,
        mappedBy: 'category',
        cascade: Relationship::CASCADE_REMOVE
    )]
    private array $products = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and setters for the private properties

    public function getParent(): ?Category
    {
        return $this->parent;
    }

    public function setParent(?Category $parent): self
    {
        $this->parent = $parent;
        if ($parent !== null) {
            $this->parentId = $parent->id;
        } else {
            $this->parentId = null;
        }
        return $this;
    }

    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(Category $child): self
    {
        if (!in_array($child, $this->children, true)) {
            $this->children[] = $child;
            $child->setParent($this);
        }
        return $this;
    }

    public function removeChild(Category $child): self
    {
        $key = array_search($child, $this->children, true);
        if ($key !== false) {
            unset($this->children[$key]);
            $this->children = array_values($this->children);
            $child->setParent(null);
        }
        return $this;
    }

    public function getProducts(): array
    {
        return $this->products;
    }

    public function addProduct(Product $product): self
    {
        if (!in_array($product, $this->products, true)) {
            $this->products[] = $product;
            $product->setCategory($this);
        }
        return $this;
    }

    public function removeProduct(Product $product): self
    {
        $key = array_search($product, $this->products, true);
        if ($key !== false) {
            unset($this->products[$key]);
            $this->products = array_values($this->products);
            $product->setCategory(null);
        }
        return $this;
    }
}