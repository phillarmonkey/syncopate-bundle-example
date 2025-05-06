<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'product',
    idGenerator: EntityDefinition::ID_TYPE_AUTO_INCREMENT,
    description: 'Product entity for benchmarking'
)]
class Product
{
    use EntityTrait;

    public ?string $id = null;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $name;

    #[Field(type: 'string', nullable: true)]
    public ?string $description = null;

    #[Field(type: 'float', indexed: true, required: true)]
    public float $price;

    #[Field(type: 'integer', indexed: true, required: true)]
    public int $stock = 0;

    #[Field(type: 'string', indexed: true)]
    public string $sku;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Field(type: 'datetime', nullable: true)]
    public ?DateTimeInterface $updatedAt = null;

    #[Field(type: 'boolean', indexed: true, required: true)]
    public bool $isActive = true;

    #[Field(type: 'json', nullable: true)]
    public ?array $attributes = null;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $categoryId;

    #[Relationship(
        targetEntity: Category::class,
        type: Relationship::TYPE_MANY_TO_ONE,
        inversedBy: 'products'
    )]
    private ?Category $category = null;

    #[Relationship(
        targetEntity: Review::class,
        type: Relationship::TYPE_ONE_TO_MANY,
        mappedBy: 'product',
        cascade: Relationship::CASCADE_REMOVE
    )]
    private array $reviews = [];

    #[Relationship(
        targetEntity: Tag::class,
        type: Relationship::TYPE_MANY_TO_MANY,
        joinTable: 'product_tags'
    )]
    private array $tags = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and setters for the private properties

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): self
    {
        $this->category = $category;
        if ($category !== null) {
            $this->categoryId = $category->id;
        }
        return $this;
    }

    public function getReviews(): array
    {
        return $this->reviews;
    }

    public function addReview(Review $review): self
    {
        $this->reviews[] = $review;
        return $this;
    }

    public function getTags(): array
    {
        return $this->tags;
    }

    public function addTag(Tag $tag): self
    {
        if (!in_array($tag, $this->tags, true)) {
            $this->tags[] = $tag;
        }
        return $this;
    }

    public function removeTag(Tag $tag): self
    {
        $key = array_search($tag, $this->tags, true);
        if ($key !== false) {
            unset($this->tags[$key]);
            $this->tags = array_values($this->tags);
        }
        return $this;
    }

    public function updateTimestamp(): void
    {
        $this->updatedAt = new \DateTime();
    }
}