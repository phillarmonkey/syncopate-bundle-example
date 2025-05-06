<?php

namespace App\Controller;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\CategoryRepository;
use App\Repository\OrderRepository;
use App\Repository\ProductRepository;
use App\Repository\ReviewRepository;
use App\Repository\UserRepository;
use Phillarmonic\SyncopateBundle\Model\QueryFilter;
use Phillarmonic\SyncopateBundle\Model\QueryOptions;
use Phillarmonic\SyncopateBundle\Model\JoinQueryOptions;
use Phillarmonic\SyncopateBundle\Service\SyncopateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/benchmark')]
class SyncopatePerformanceController extends AbstractController
{
    private SyncopateService $syncopateService;
    private CategoryRepository $categoryRepository;
    private ProductRepository $productRepository;
    private OrderRepository $orderRepository;
    private ReviewRepository $reviewRepository;
    private UserRepository $userRepository;

    public function __construct(
        SyncopateService $syncopateService,
        CategoryRepository $categoryRepository,
        ProductRepository $productRepository,
        OrderRepository $orderRepository,
        ReviewRepository $reviewRepository,
        UserRepository $userRepository
    ) {
        $this->syncopateService = $syncopateService;
        $this->categoryRepository = $categoryRepository;
        $this->productRepository = $productRepository;
        $this->orderRepository = $orderRepository;
        $this->reviewRepository = $reviewRepository;
        $this->userRepository = $userRepository;
    }

    /**
     * Dashboard with entity counts and system info
     */
    #[Route('/dashboard', name: 'app_benchmark_dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        $startTime = microtime(true);

        // Get entity counts
        $categoriesCount = $this->categoryRepository->count();
        $productsCount = $this->productRepository->count();
        $ordersCount = $this->orderRepository->count();
        $reviewsCount = $this->reviewRepository->count();
        $usersCount = $this->userRepository->count();

        // Get system info
        $serverInfo = $this->syncopateService->getServerInfo();
        $health = $this->syncopateService->checkHealth();

        $timeTaken = microtime(true) - $startTime;

        return new JsonResponse([
            'entities' => [
                'categories' => $categoriesCount,
                'products' => $productsCount,
                'orders' => $ordersCount,
                'reviews' => $reviewsCount,
                'users' => $usersCount,
            ],
            'system' => [
                'server_info' => $serverInfo,
                'health' => $health,
                'memory_usage' => $this->formatBytes(memory_get_usage()),
                'peak_memory' => $this->formatBytes(memory_get_peak_usage()),
            ],
            'performance' => [
                'time_taken' => $timeTaken,
            ],
        ]);
    }

    /**
     * Basic CRUD operations benchmark
     */
    #[Route('/crud', name: 'app_benchmark_crud', methods: ['GET'])]
    public function crudBenchmark(): JsonResponse
    {
        $results = [];

        // Create
        $createStart = microtime(true);
        $category = new Category();
        $category->name = 'Benchmark Category ' . uniqid('', true);
        $category->description = 'Created for benchmarking';
        $category->slug = 'benchmark-' . uniqid('', true);
        $category->position = 1000;
        $category = $this->syncopateService->create($category);
        $results['create'] = [
            'time' => microtime(true) - $createStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
        ];

        // Read
        $readStart = microtime(true);
        $category = $this->syncopateService->getById(Category::class, $category->id);
        $results['read'] = [
            'time' => microtime(true) - $readStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
        ];

        // Update
        $updateStart = microtime(true);
        $category->description = 'Updated for benchmarking';
        $category = $this->syncopateService->update($category);
        $results['update'] = [
            'time' => microtime(true) - $updateStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
        ];

        // Delete
        $deleteStart = microtime(true);
        $deleted = $this->syncopateService->delete($category);
        $results['delete'] = [
            'time' => microtime(true) - $deleteStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'success' => $deleted,
        ];

        return new JsonResponse($results);
    }

    /**
     * Bulk operations benchmark
     */
    #[Route('/bulk', name: 'app_benchmark_bulk', methods: ['GET'])]
    public function bulkOperationsBenchmark(Request $request): JsonResponse
    {
        $count = $request->query->getInt('count', 100);
        $results = [];

        // Bulk create
        $createStart = microtime(true);
        $categoryIds = [];

        for ($i = 0; $i < $count; $i++) {
            $category = new Category();
            $category->name = 'Bulk Cat ' . $i . ' ' . uniqid('', true);
            $category->description = 'Created in bulk operation';
            $category->slug = 'bulk-' . $i . '-' . uniqid('', true);
            $category->position = 2000 + $i;

            $category = $this->syncopateService->create($category);
            $categoryIds[] = $category->id;

            // Garbage collection for large operations
            if ($i % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $results['bulk_create'] = [
            'time' => microtime(true) - $createStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($categoryIds),
        ];

        // Bulk read
        $readStart = microtime(true);
        $categories = [];

        foreach ($categoryIds as $id) {
            $categories[] = $this->syncopateService->getById(Category::class, $id);

            // Garbage collection for large operations
            if (count($categories) % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $results['bulk_read'] = [
            'time' => microtime(true) - $readStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($categories),
        ];

        // Bulk update
        $updateStart = microtime(true);
        $updateCount = 0;

        foreach ($categories as $category) {
            $category->description = 'Updated in bulk operation ' . uniqid('', true);
            $this->syncopateService->update($category);
            $updateCount++;

            // Garbage collection for large operations
            if ($updateCount % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $results['bulk_update'] = [
            'time' => microtime(true) - $updateStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => $updateCount,
        ];

        // Bulk delete
        $deleteStart = microtime(true);
        $deleteCount = 0;

        foreach ($categories as $category) {
            $this->syncopateService->delete($category);
            $deleteCount++;

            // Garbage collection for large operations
            if ($deleteCount % 20 === 0) {
                gc_collect_cycles();
            }
        }

        $results['bulk_delete'] = [
            'time' => microtime(true) - $deleteStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => $deleteCount,
        ];

        return new JsonResponse($results);
    }

    /**
     * Query benchmark
     */
    #[Route('/query', name: 'app_benchmark_query', methods: ['GET'])]
    public function queryBenchmark(): JsonResponse
    {
        $results = [];

        // Simple find all
        $findAllStart = microtime(true);
        $products = $this->productRepository->findAll();
        $results['find_all'] = [
            'time' => microtime(true) - $findAllStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($products),
        ];

        // Query with criteria
        $queryWithCriteriaStart = microtime(true);
        $activeProducts = $this->productRepository->findBy(['isActive' => true], ['price' => 'ASC'], 100);
        $results['query_with_criteria'] = [
            'time' => microtime(true) - $queryWithCriteriaStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($activeProducts),
        ];

        // Complex query with query builder
        $complexQueryStart = microtime(true);
        $expensiveProducts = $this->productRepository->createQueryBuilder()
            ->eq('isActive', true)
            ->gt('price', 500)
            ->gt('stock', 10)
            ->orderBy('price', 'DESC')
            ->limit(50)
            ->getResult();
        $results['complex_query'] = [
            'time' => microtime(true) - $complexQueryStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($expensiveProducts),
        ];

        // Fuzzy search
        $fuzzySearchStart = microtime(true);
        $searchProducts = $this->productRepository->createQueryBuilder()
            ->fuzzy('name', 'chair')
            ->setFuzzyOptions(0.7, 3)
            ->limit(20)
            ->getResult();
        $results['fuzzy_search'] = [
            'time' => microtime(true) - $fuzzySearchStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($searchProducts),
        ];

        return new JsonResponse($results);
    }

    /**
     * Join query benchmark
     */
    #[Route('/join', name: 'app_benchmark_join', methods: ['GET'])]
    public function joinQueryBenchmark(): JsonResponse
    {
        $results = [];

        // Simple join
        $simpleJoinStart = microtime(true);
        $productsWithCategory = $this->productRepository->createJoinQueryBuilder()
            ->eq('isActive', true)
            ->innerJoin(
                'category',
                'categoryId',
                'id',
                'category'
            )
            ->limit(50)
            ->getJoinResult();
        $results['simple_join'] = [
            'time' => microtime(true) - $simpleJoinStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($productsWithCategory),
        ];

        // Complex join
        $complexJoinStart = microtime(true);
        $ordersWithItems = $this->orderRepository->createJoinQueryBuilder()
            ->eq('status', Order::STATUS_COMPLETED)
            ->innerJoin(
                'order_item',
                'id',
                'orderId',
                'items'
            )
            ->innerJoin(
                'user',
                'userId',
                'id',
                'user'
            )
            ->limit(20)
            ->getJoinResult();
        $results['complex_join'] = [
            'time' => microtime(true) - $complexJoinStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($ordersWithItems),
        ];

        // Join with filtering on joined entity
        $joinWithFilteringStart = microtime(true);
        $joinBuilder = $this->productRepository->createJoinQueryBuilder()
            ->eq('isActive', true)
            ->innerJoin(
                'review',
                'id',
                'productId',
                'reviews'
            );

        $joinBuilder->addJoinFilter(
            QueryFilter::gte('rating', 4)
        );

        $productsWithGoodReviews = $joinBuilder->limit(20)->getJoinResult();

        $results['join_with_filtering'] = [
            'time' => microtime(true) - $joinWithFilteringStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($productsWithGoodReviews),
        ];

        return new JsonResponse($results);
    }

    /**
     * Custom repository methods benchmark
     */
    #[Route('/custom-repository', name: 'app_benchmark_custom_repository', methods: ['GET'])]
    public function customRepositoryMethodsBenchmark(): JsonResponse
    {
        $results = [];

        // Category repository methods
        $rootCategoriesStart = microtime(true);
        $rootCategories = $this->categoryRepository->findRootCategories();
        $results['root_categories'] = [
            'time' => microtime(true) - $rootCategoriesStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($rootCategories),
        ];

        $categoryTreeStart = microtime(true);
        $categoryTree = $this->categoryRepository->buildCategoryTree();
        $results['category_tree'] = [
            'time' => microtime(true) - $categoryTreeStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($categoryTree),
        ];

        // Product repository methods
        $lowStockStart = microtime(true);
        $lowStockProducts = $this->productRepository->findLowStockProducts(10);
        $results['low_stock_products'] = [
            'time' => microtime(true) - $lowStockStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($lowStockProducts),
        ];

        $popularProductsStart = microtime(true);
        $popularProducts = $this->productRepository->findPopularProducts(10);
        $results['popular_products'] = [
            'time' => microtime(true) - $popularProductsStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($popularProducts),
        ];

        // Order repository methods
        $ordersByStatusStart = microtime(true);
        $completedOrders = $this->orderRepository->findByStatus(Order::STATUS_COMPLETED, 20);
        $results['orders_by_status'] = [
            'time' => microtime(true) - $ordersByStatusStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($completedOrders),
        ];

        // User repository methods
        $recentUsersStart = microtime(true);
        $recentUsers = $this->userRepository->findRecentlyRegistered(30, 20);
        $results['recent_users'] = [
            'time' => microtime(true) - $recentUsersStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($recentUsers),
        ];

        return new JsonResponse($results);
    }

    /**
     * Memory usage monitoring test
     */
    #[Route('/memory', name: 'app_benchmark_memory', methods: ['GET'])]
    public function memoryUsageBenchmark(Request $request): JsonResponse
    {
        $batchSize = $request->query->getInt('batch_size', 50);
        $results = [];

        // Initial memory
        $initialMemory = memory_get_usage();
        $results['initial_memory'] = $this->formatBytes($initialMemory);

        // Load all products at once
        $bulkLoadStart = microtime(true);
        $memoryBefore = memory_get_usage();

        $products = $this->productRepository->findAll();

        $memoryAfter = memory_get_usage();
        $peakMemory = memory_get_peak_usage();

        $results['bulk_load'] = [
            'time' => microtime(true) - $bulkLoadStart,
            'memory_before' => $this->formatBytes($memoryBefore),
            'memory_after' => $this->formatBytes($memoryAfter),
            'memory_increase' => $this->formatBytes($memoryAfter - $memoryBefore),
            'peak_memory' => $this->formatBytes($peakMemory),
            'count' => count($products),
        ];

        // Clear memory
        unset($products);
        gc_collect_cycles();

        $results['after_gc'] = $this->formatBytes(memory_get_usage());

        // Load products in batches
        $batchedLoadStart = microtime(true);
        $memoryBefore = memory_get_usage();

        $offset = 0;
        $totalCount = 0;
        $hasMore = true;

        while ($hasMore) {
            $batch = $this->productRepository->findBy([], [], $batchSize, $offset);
            $batchCount = count($batch);
            $totalCount += $batchCount;

            if ($batchCount < $batchSize) {
                $hasMore = false;
            } else {
                $offset += $batchSize;
            }

            // Free memory
            unset($batch);
            gc_collect_cycles();
        }

        $memoryAfter = memory_get_usage();
        $peakMemory = memory_get_peak_usage();

        $results['batched_load'] = [
            'time' => microtime(true) - $batchedLoadStart,
            'memory_before' => $this->formatBytes($memoryBefore),
            'memory_after' => $this->formatBytes($memoryAfter),
            'memory_increase' => $this->formatBytes($memoryAfter - $memoryBefore),
            'peak_memory' => $this->formatBytes($peakMemory),
            'count' => $totalCount,
            'batch_size' => $batchSize,
        ];

        return new JsonResponse($results);
    }

    /**
     * Stress test
     */
    #[Route('/stress', name: 'app_benchmark_stress', methods: ['GET'])]
    public function stressTest(Request $request): JsonResponse
    {
        $iterations = $request->query->getInt('iterations', 10);
        $results = [];

        $overallStart = microtime(true);

        for ($i = 0; $i < $iterations; $i++) {
            // Create entities
            $category = new Category();
            $category->name = 'Stress Test Category ' . $i;
            $category->description = 'Created during stress test';
            $category->slug = 'stress-test-' . $i;
            $category->position = 3000 + $i;
            $category = $this->syncopateService->create($category);

            // Create a product
            $product = new Product();
            $product->name = 'Stress Test Product ' . $i;
            $product->description = 'Created during stress test';
            $product->price = random_int(100, 1000) / 10;
            $product->stock = random_int(1, 100);
            $product->sku = 'STRESS-' . uniqid('', true);
            $product->categoryId = $category->id;
            $product = $this->syncopateService->create($product);

            // Run queries
            $this->productRepository->findByPriceRange(10, 1000, 20);
            $this->categoryRepository->findRootCategories();

            // Update entities
            $product->description = 'Updated during stress test';
            $this->syncopateService->update($product);

            // Delete entities to clean up
            $this->syncopateService->delete($product);
            $this->syncopateService->delete($category);

            // Free memory
            unset($category, $product);
            gc_collect_cycles();
        }

        $totalTime = microtime(true) - $overallStart;

        $results['stress_test'] = [
            'iterations' => $iterations,
            'total_time' => $totalTime,
            'avg_time_per_iteration' => $totalTime / $iterations,
            'peak_memory' => $this->formatBytes(memory_get_peak_usage()),
        ];

        return new JsonResponse($results);
    }

    /**
     * Raw query options benchmark
     */
    #[Route('/raw-query', name: 'app_benchmark_raw_query', methods: ['GET'])]
    public function rawQueryBenchmark(): JsonResponse
    {
        $results = [];

        // Get entity type
        $entityType = $this->categoryRepository->getEntityType();

        // Create query options manually
        $queryStart = microtime(true);

        $queryOptions = new QueryOptions($entityType);
        $queryOptions->addFilter(QueryFilter::eq('isActive', true));
        $queryOptions->setLimit(50);
        $queryOptions->setOrderBy('position');

        $categories = $this->syncopateService->query(Category::class, $queryOptions);

        $results['raw_query'] = [
            'time' => microtime(true) - $queryStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($categories),
        ];

        // Create join query options manually
        $joinQueryStart = microtime(true);

        $joinQueryOptions = new JoinQueryOptions($entityType);
        $joinQueryOptions->addFilter(QueryFilter::eq('isActive', true));

        $joinDefinition = new \Phillarmonic\SyncopateBundle\Model\JoinDefinition(
            'product',
            'id',
            'categoryId',
            'products',
            \Phillarmonic\SyncopateBundle\Model\JoinDefinition::JOIN_TYPE_INNER,
            \Phillarmonic\SyncopateBundle\Model\JoinDefinition::SELECT_STRATEGY_ALL
        );

        $joinQueryOptions->addJoin($joinDefinition);
        $joinQueryOptions->setLimit(20);

        $categoriesWithProducts = $this->syncopateService->joinQuery(Category::class, $joinQueryOptions);

        $results['raw_join_query'] = [
            'time' => microtime(true) - $joinQueryStart,
            'memory' => $this->formatBytes(memory_get_peak_usage()),
            'count' => count($categoriesWithProducts),
        ];

        return new JsonResponse($results);
    }

    /**
     * Format bytes to human-readable format
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);

        $bytes /= 1024 ** $pow;

        return round($bytes, $precision) . ' ' . $units[$pow];
    }
}