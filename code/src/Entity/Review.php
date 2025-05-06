<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'review',
    idGenerator: EntityDefinition::ID_TYPE_UUID,
    description: 'Product review entity for benchmarking'
)]
class Review
{
    use EntityTrait;

    public ?string $id = null;

    #[Field(type: 'string', required: true, indexed: true)]
    public string $productId;

    #[Field(type: 'string', required: true, indexed: true)]
    public string $userId;

    #[Field(type: 'integer', required: true, indexed: true)]
    public int $rating;

    #[Field(type: 'string', nullable: true)]
    public ?string $title = null;

    #[Field(type: 'string', required: true)]
    public string $content;

    #[Field(type: 'boolean', indexed: true, required: true)]
    public bool $isVerified = false;

    #[Field(type: 'boolean', indexed: true, required: true)]
    public bool $isApproved = true;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Relationship(
        targetEntity: Product::class,
        type: Relationship::TYPE_MANY_TO_ONE,
        inversedBy: 'reviews'
    )]
    private ?Product $product = null;

    #[Relationship(
        targetEntity: User::class,
        type: Relationship::TYPE_MANY_TO_ONE,
        inversedBy: 'reviews'
    )]
    private ?User $user = null;

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
        }
        return $this;
    }

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

    public function verify(): self
    {
        $this->isVerified = true;
        return $this;
    }

    public function approve(): self
    {
        $this->isApproved = true;
        return $this;
    }

    public function reject(): self
    {
        $this->isApproved = false;
        return $this;
    }
}