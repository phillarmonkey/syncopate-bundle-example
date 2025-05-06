<?php

namespace App\Repository;

use App\Entity\Category;
use Phillarmonic\SyncopateBundle\Mapper\EntityMapper;
use Phillarmonic\SyncopateBundle\Model\QueryFilter;
use Phillarmonic\SyncopateBundle\Repository\EntityRepository;
use Phillarmonic\SyncopateBundle\Service\SyncopateService;

class CategoryRepository extends EntityRepository
{
    public function __construct(SyncopateService $syncopateService, EntityMapper $entityMapper, string $entityClass)
    {
        parent::__construct($syncopateService, $entityMapper, $entityClass);
    }

    /**
     * Find all root categories (without parent)
     */
    public function findRootCategories(): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'parentId', value: null)
            ->eq(field: 'isActive', value: true)
            ->orderBy(field: 'position')
            ->getResult();
    }

    /**
     * Find child categories by parent ID
     */
    public function findChildCategories(string $parentId): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'parentId', value: $parentId)
            ->eq(field: 'isActive', value: true)
            ->orderBy(field: 'position')
            ->getResult();
    }

    /**
     * Find categories with products
     */
    public function findCategoriesWithProducts(int $limit = 20): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'isActive', value: true)
            ->innerJoin(
                entityType: 'product',
                localField: 'id',
                foreignField: 'categoryId',
                as: 'products'
            )
            ->orderBy(field: 'position')
            ->limit($limit);

        return $joinQueryBuilder->getJoinResult();
    }

    /**
     * Find categories with at least N products
     */
    public function findCategoriesWithMinProducts(int $minProducts = 5): array
    {
        // This requires a complex query that counts products per category
        // For now, we'll fetch categories with products and filter in PHP
        $categoriesWithProducts = $this->findCategoriesWithProducts(100);

        return array_filter($categoriesWithProducts, function($category) use ($minProducts) {
            return count($category->getProducts()) >= $minProducts;
        });
    }

    /**
     * Build a complete category tree
     */
    public function buildCategoryTree(): array
    {
        // Get all categories
        $allCategories = $this->findAll();

        // Index categories by ID for fast lookup
        $categoriesById = [];
        foreach ($allCategories as $category) {
            $categoriesById[$category->id] = [
                'entity' => $category,
                'children' => []
            ];
        }

        // Build the tree
        $rootCategories = [];
        foreach ($categoriesById as $id => $data) {
            $category = $data['entity'];

            if ($category->parentId === null) {
                // This is a root category
                $rootCategories[] = $data;
            } else if (isset($categoriesById[$category->parentId])) {
                // Add to parent's children
                $categoriesById[$category->parentId]['children'][] = $data;
            }
        }

        return $rootCategories;
    }

    /**
     * Get the complete path from a category to root
     */
    public function getCategoryPathToRoot(string $categoryId): array
    {
        $path = [];
        $currentId = $categoryId;

        // To avoid infinite loops in case of circular references
        $maxDepth = 10;
        $depth = 0;

        while ($currentId !== null && $depth < $maxDepth) {
            $category = $this->find($currentId);

            if ($category === null) {
                break;
            }

            $path[] = $category;
            $currentId = $category->parentId;
            $depth++;
        }

        // Reverse to get root->leaf order
        return array_reverse($path);
    }

    /**
     * Search categories by name
     */
    public function searchByName(string $searchTerm, int $limit = 20): array
    {
        return $this->createQueryBuilder()
            ->fuzzy(field: 'name', value: $searchTerm)
            ->eq(field: 'isActive', value: true)
            ->setFuzzyOptions(0.7, 3)
            ->limit($limit)
            ->getResult();
    }

    /**
     * Get category statistics
     */
    public function getCategoryStatistics(): array
    {
        $totalCount = $this->count();
        $activeCount = $this->count(['isActive' => true]);
        $rootCount = $this->count(['parentId' => null]);

        // Count average depth
        $allCategories = $this->findAll();
        $totalDepth = 0;
        $categoriesWithDepth = 0;

        foreach ($allCategories as $category) {
            $path = $this->getCategoryPathToRoot($category->id);
            $depth = count($path) - 1; // Exclude the category itself

            if ($depth > 0) {
                $totalDepth += $depth;
                $categoriesWithDepth++;
            }
        }

        $avgDepth = $categoriesWithDepth > 0 ? $totalDepth / $categoriesWithDepth : 0;

        return [
            'totalCount' => $totalCount,
            'activeCount' => $activeCount,
            'rootCount' => $rootCount,
            'avgDepth' => $avgDepth
        ];
    }
}