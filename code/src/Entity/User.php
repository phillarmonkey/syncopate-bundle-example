<?php

namespace App\Entity;

use Phillarmonic\SyncopateBundle\Attribute\Entity;
use Phillarmonic\SyncopateBundle\Attribute\Field;
use Phillarmonic\SyncopateBundle\Attribute\Relationship;
use Phillarmonic\SyncopateBundle\Model\EntityDefinition;
use Phillarmonic\SyncopateBundle\Trait\EntityTrait;
use DateTimeInterface;

#[Entity(
    name: 'user',
    idGenerator: EntityDefinition::ID_TYPE_AUTO_INCREMENT,
    description: 'User entity for benchmarking'
)]
class User
{
    use EntityTrait;

    public ?string $id = null;

    #[Field(type: 'string', indexed: true, required: true)]
    public string $email;

    #[Field(type: 'string', required: true)]
    public string $firstName;

    #[Field(type: 'string', required: true)]
    public string $lastName;

    #[Field(type: 'string', nullable: true)]
    public ?string $phoneNumber = null;

    #[Field(type: 'datetime', indexed: true, required: true)]
    public DateTimeInterface $createdAt;

    #[Field(type: 'boolean', indexed: true, required: true)]
    public bool $isActive = true;

    #[Field(type: 'json', nullable: true)]
    public ?array $preferences = null;

    #[Relationship(
        targetEntity: Order::class,
        type: Relationship::TYPE_ONE_TO_MANY,
        mappedBy: 'user',
        cascade: Relationship::CASCADE_REMOVE
    )]
    private array $orders = [];

    #[Relationship(
        targetEntity: Review::class,
        type: Relationship::TYPE_ONE_TO_MANY,
        mappedBy: 'user',
        cascade: Relationship::CASCADE_REMOVE
    )]
    private array $reviews = [];

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    // Getters and setters for the private properties

    public function getOrders(): array
    {
        return $this->orders;
    }

    public function addOrder(Order $order): self
    {
        $this->orders[] = $order;
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

    public function getFullName(): string
    {
        return $this->firstName . ' ' . $this->lastName;
    }
}