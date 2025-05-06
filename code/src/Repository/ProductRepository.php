<?php

namespace App\Repository;

use App\Entity\Product;
use Phillarmonic\SyncopateBundle\Mapper\EntityMapper;
use Phillarmonic\SyncopateBundle\Model\QueryFilter;
use Phillarmonic\SyncopateBundle\Repository\EntityRepository;
use Phillarmonic\SyncopateBundle\Service\SyncopateService;

class ProductRepository extends EntityRepository
{
    public function __construct(SyncopateService $syncopateService, EntityMapper $entityMapper, string $entityClass)
    {
        parent::__construct($syncopateService, $entityMapper, $entityClass);
    }

    /**
     * Find products by price range
     */
    public function findByPriceRange(float $minPrice, float $maxPrice, int $limit = 100): array
    {
        return $this->createQueryBuilder()
            ->gte(field: 'price', value: $minPrice)
            ->lte(field: 'price', value: $maxPrice)
            ->orderBy(field: 'price')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find products with stock below threshold
     */
    public function findLowStockProducts(int $threshold = 10): array
    {
        return $this->createQueryBuilder()
            ->lte(field: 'stock', value: $threshold)
            ->gt(field: 'stock', value: 0)
            ->eq(field: 'isActive', value: true)
            ->orderBy(field: 'stock')
            ->getResult();
    }

    /**
     * Find products by category with tag filtering
     */
    public function findByCategoryWithTags(string $categoryId, array $tagIds = [], int $limit = 50): array
    {
        $queryBuilder = $this->createQueryBuilder()
            ->eq(field: 'categoryId', value: $categoryId)
            ->eq(field: 'isActive', value: true);

        // Add tag filtering if provided
        if (!empty($tagIds)) {
            // We'll need to use a join query for many-to-many relationship
            $joinQueryBuilder = $this->createJoinQueryBuilder()
                ->eq(field: 'categoryId', value: $categoryId)
                ->eq(field: 'isActive', value: true)
                ->innerJoin(
                    entityType: 'tag',
                    localField: 'id',
                    foreignField: 'productId',
                    as: 'tags'
                );

            $joinQueryBuilder->addJoinFilter(
                QueryFilter::in('id', $tagIds)
            );

            return $joinQueryBuilder->limit($limit)->getJoinResult();
        }

        return $queryBuilder->limit($limit)->getResult();
    }

    /**
     * Find popular products based on order count
     */
    public function findPopularProducts(int $limit = 10): array
    {
        // This would use a join query to count orders
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'isActive', value: true)
            ->innerJoin(
                entityType: 'order_item',
                localField: 'id',
                foreignField: 'productId',
                as: 'orderItems'
            )
            ->limit($limit);

        return $joinQueryBuilder->getJoinResult();
    }

    /**
     * Search products by name or description
     */
    public function searchProducts(string $searchTerm, int $limit = 25): array
    {
        return $this->createQueryBuilder()
            ->fuzzy(field: 'name', value: $searchTerm)
            ->eq(field: 'isActive', value: true)
            ->limit($limit)
            ->setFuzzyOptions(0.7, 3)
            ->getResult();
    }

    /**
     * Find products with reviews
     */
    public function findProductsWithReviews(int $minRating = 4, int $limit = 20): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'isActive', value: true)
            ->innerJoin(
                entityType: 'review',
                localField: 'id',
                foreignField: 'productId',
                as: 'reviews'
            );

        $joinQueryBuilder->addJoinFilter(
            QueryFilter::gte('rating', $minRating)
        );

        return $joinQueryBuilder->limit($limit)->getJoinResult();
    }

    /**
     * Get product statistics
     */
    public function getProductStatistics(): array
    {
        $totalCount = $this->count();
        $activeCount = $this->count(['isActive' => true]);
        $outOfStockCount = $this->count(['stock' => 0]);

        $avgPrice = 0;
        $products = $this->findAll();

        if (!empty($products)) {
            $totalPrice = array_reduce($products, function ($carry, $product) {
                return $carry + $product->price;
            }, 0);
            $avgPrice = $totalPrice / count($products);
        }

        return [
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'outOfStockCount' => $outOfStockCount,
            'avgPrice' => $avgPrice
        ];
    }
}