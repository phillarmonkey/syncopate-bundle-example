<?php

namespace App\Repository;

use App\Entity\User;
use Phillarmonic\SyncopateBundle\Mapper\EntityMapper;
use Phillarmonic\SyncopateBundle\Model\QueryFilter;
use Phillarmonic\SyncopateBundle\Repository\EntityRepository;
use DateTimeInterface;
use Phillarmonic\SyncopateBundle\Service\SyncopateService;

class UserRepository extends EntityRepository
{
    public function __construct(SyncopateService $syncopateService, EntityMapper $entityMapper, string $entityClass)
    {
        parent::__construct($syncopateService, $entityMapper, $entityClass);
    }

    /**
     * Find users by email pattern
     */
    public function findByEmailPattern(string $pattern, int $limit = 20): array
    {
        return $this->createQueryBuilder()
            ->contains(field: 'email', value: $pattern)
            ->eq(field: 'isActive', value: true)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find recently registered users
     */
    public function findRecentlyRegistered(int $days = 30, int $limit = 50): array
    {
        $date = new \DateTime('now');
        $date->modify("-$days days");

        return $this->createQueryBuilder()
            ->gte(field: 'createdAt', value: $date->format(DateTimeInterface::ATOM))
            ->eq(field: 'isActive', value: true)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find users with orders (using join)
     */
    public function findUsersWithOrders(int $limit = 50): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'isActive', value: true)
            ->innerJoin(
                entityType: 'order',
                localField: 'id',
                foreignField: 'userId',
                as: 'orders'
            )
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit);

        return $joinQueryBuilder->getJoinResult();
    }

    /**
     * Find users with reviews
     */
    public function findUsersWithReviews(int $limit = 50): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'isActive', value: true)
            ->innerJoin(
                entityType: 'review',
                localField: 'id',
                foreignField: 'userId',
                as: 'reviews'
            )
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit);

        return $joinQueryBuilder->getJoinResult();
    }

    /**
     * Search users by name
     */
    public function searchByName(string $searchTerm, int $limit = 20): array
    {
        // Create combined search query for first and last name
        $queryBuilder = $this->createQueryBuilder();

        // Add fuzzy search on first name OR last name
        $firstName = QueryFilter::fuzzy('firstName', $searchTerm);
        $lastName = QueryFilter::fuzzy('lastName', $searchTerm);

        // We need to handle this as separate queries and merge the results
        $firstNameResults = $this->createQueryBuilder()
            ->fuzzy(field: 'firstName', value: $searchTerm)
            ->eq(field: 'isActive', value: true)
            ->setFuzzyOptions(0.7, 3)
            ->limit($limit)
            ->getResult();

        $lastNameResults = $this->createQueryBuilder()
            ->fuzzy(field: 'lastName', value: $searchTerm)
            ->eq(field: 'isActive', value: true)
            ->setFuzzyOptions(0.7, 3)
            ->limit($limit)
            ->getResult();

        // Merge results (removing duplicates by id)
        $results = [];
        $ids = [];

        foreach (array_merge($firstNameResults, $lastNameResults) as $user) {
            if (!in_array($user->id, $ids)) {
                $results[] = $user;
                $ids[] = $user->id;

                if (count($results) >= $limit) {
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * Find top customers (with most orders)
     */
    public function findTopCustomers(int $limit = 10): array
    {
        // This is a complex query that requires counting orders per user
        // and then sorting by that count. We'll need to fetch all results
        // and sort them in PHP for now.

        $users = $this->findUsersWithOrders(200);

        // Sort by order count
        usort($users, function ($a, $b) {
            return count($b->getOrders()) - count($a->getOrders());
        });

        // Return the top N users
        return array_slice($users, 0, $limit);
    }

    /**
     * Get user statistics
     */
    public function getUserStatistics(): array
    {
        $totalCount = $this->count();
        $activeCount = $this->count(['isActive' => true]);
        $withOrdersCount = $this->count(['orders' => ['$exists' => true]]);

        return [
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'withOrdersCount' => $withOrdersCount
        ];
    }
}