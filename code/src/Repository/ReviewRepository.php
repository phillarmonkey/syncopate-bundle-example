<?php

namespace App\Repository;

use App\Entity\Review;
use Phillarmonic\SyncopateBundle\Model\QueryFilter;
use Phillarmonic\SyncopateBundle\Repository\EntityRepository;
use DateTimeInterface;

class ReviewRepository extends EntityRepository
{
    /**
     * Find reviews by product
     */
    public function findByProduct(string $productId, int $limit = 20): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'productId', value: $productId)
            ->eq(field: 'isApproved', value: true)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find reviews by user
     */
    public function findByUser(string $userId, int $limit = 20): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'userId', value: $userId)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find top-rated reviews
     */
    public function findTopRatedReviews(int $minRating = 4, int $limit = 20): array
    {
        return $this->createQueryBuilder()
            ->gte(field: 'rating', value: $minRating)
            ->eq(field: 'isApproved', value: true)
            ->orderBy(field: 'rating', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find reviews pending approval
     */
    public function findPendingReviews(int $limit = 50): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'isApproved', value: false)
            ->orderBy(field: 'createdAt', direction: 'ASC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find recent reviews
     */
    public function findRecentReviews(int $days = 30, int $limit = 20): array
    {
        $date = new \DateTime('now');
        $date->modify("-$days days");

        return $this->createQueryBuilder()
            ->gte(field: 'createdAt', value: $date->format(DateTimeInterface::ATOM))
            ->eq(field: 'isApproved', value: true)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find reviews with full product and user data (using joins)
     */
    public function findReviewsWithProductAndUser(int $limit = 20): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'isApproved', value: true)
            ->innerJoin(
                entityType: 'product',
                localField: 'productId',
                foreignField: 'id',
                as: 'product'
            )
            ->innerJoin(
                entityType: 'user',
                localField: 'userId',
                foreignField: 'id',
                as: 'user'
            )
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit);

        return $joinQueryBuilder->getJoinResult();
    }

    /**
     * Calculate average rating for a product
     */
    public function calculateAverageRating(string $productId): float
    {
        $reviews = $this->findByProduct($productId, 1000);

        if (empty($reviews)) {
            return 0.0;
        }

        $sum = array_reduce($reviews, function ($carry, $review) {
            return $carry + $review->rating;
        }, 0);

        return $sum / count($reviews);
    }

    /**
     * Count reviews by rating
     */
    public function countReviewsByRating(string $productId): array
    {
        $reviews = $this->findByProduct($productId, 1000);
        $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($reviews as $review) {
            if (isset($counts[$review->rating])) {
                $counts[$review->rating]++;
            }
        }

        return $counts;
    }

    /**
     * Get review statistics
     */
    public function getReviewStatistics(): array
    {
        $totalCount = $this->count();
        $approvedCount = $this->count(['isApproved' => true]);
        $pendingCount = $this->count(['isApproved' => false]);
        $verifiedCount = $this->count(['isVerified' => true]);

        // Calculate average rating
        $reviews = $this->findAll();
        $totalRating = 0;
        $ratingCounts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];

        foreach ($reviews as $review) {
            $totalRating += $review->rating;
            if (isset($ratingCounts[$review->rating])) {
                $ratingCounts[$review->rating]++;
            }
        }

        $avgRating = $totalCount > 0 ? $totalRating / $totalCount : 0;

        return [
            'totalCount' => $totalCount,
            'approvedCount' => $approvedCount,
            'pendingCount' => $pendingCount,
            'verifiedCount' => $verifiedCount,
            'averageRating' => $avgRating,
            'ratingDistribution' => $ratingCounts
        ];
    }
}