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
use Phillarmonic\SyncopateBundle\Service\SyncopateService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/performance')]
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
     * Basic CRUD operations benchmark
     */
    #[Route('/crud', name: 'app_performance_crud', methods: ['GET'])]
    public function crudBenchmark(): JsonResponse
    {
        $start = microtime(true);
        $results = [];

        // Create a new category
        $createStart = microtime(true);
        $category = new Category();
        $category->name = 'Performance Test Category ' . uniqid('', true);
        $category->description = 'Created for performance testing';
        $category->slug = 'performance-test-' . uniqid('', true);
        $category->position = 999;
        $category = $this->syncopateService->create($category);
        $results['create'] = microtime(true) - $createStart;

        // Read the category
        $readStart = microtime(true);
        $category = $this->syncopateService->getById(Category::class, $category->id);
        $results['read'] = microtime(true) - $readStart;

        // Update the category
        $updateStart = microtime(true);
        $category->description = 'Updated for performance testing';
        $category = $this->syncopateService->update($category);
        $results['update'] = microtime(true) - $updateStart;

        // Delete the category
        $deleteStart = microtime(true);
        $this->syncopateService->delete($category);
        $results['delete'] = microtime(true) - $deleteStart;

        $results['total'] = microtime(true) - $start;

        return new JsonResponse($results);
    }

    /**
     * Basic repository operations benchmark
     */
    #[Route('/repository', name: 'app_performance_repository', methods: ['GET'])]
    public function repositoryBenchmark(): JsonResponse
    {
        $start = microtime(true);
        $results = [];

        // Find all products
        $findAllStart = microtime(true);
        $products = $this->productRepository->findAll();
        $results['findAll'] = microtime(true) - $findAllStart;
        $results['findAll_count'] = count($products);

        // Find by criteria
        $findByStart = microtime(true);
        $activeProducts = $this->productRepository->findBy(['isActive' => true], ['price' => 'ASC'], 50);
        $results['findBy'] = microtime(true) - $findByStart;
        $results['findBy_count'] = count($activeProducts);

        // Find one by criteria
        $findOneByStart = microtime(true);
        $product = $this->productRepository->findOneBy(['isActive' => true]);
        $results['findOneBy'] = microtime(true) - $findOneByStart;
        $results['findOneBy_found'] = $product !== null;

        // Count
        $countStart = microtime(true);
        $count = $this->productRepository->count(['isActive' => true]);
        $results['count'] = microtime(true) - $countStart;
        $results['count_value'] = $count;

        $results['total'] = microtime(true) - $start;

        return new JsonResponse($results);
    }

    /**
     * Query builder operations benchmark
     */
    #[Route('/query-builder', name: 'app_performance_query_builder', methods: ['GET'])]
    public function queryBuilderBenchmark(): JsonResponse
    {
        $start = microtime(true);
        $results = [];

        // Simple query
        $simpleQueryStart = microtime(true);
        $products = $this->productRepository->createQueryBuilder()
            ->eq('isActive', true)
            ->orderBy('price', 'ASC')
            ->limit(50)
            ->getResult();
        $results['simple_query'] = microtime(true) - $simpleQueryStart;
        $results['simple_query_count'] = count($products);

        // Complex query
        $complexQueryStart = microtime(true);
        $products = $this->productRepository->createQueryBuilder()
            ->eq('isActive', true)
            ->gte('price', 50)
            ->lte('price', 500)
            ->gt('stock', 0)
            ->orderBy('price', 'DESC')
            ->limit(20)
            ->getResult();
        $results['complex_query'] = microtime(true) - $complexQueryStart;
        $results['complex_query_count'] = count($products);

        // Fuzzy search
        $fuzzySearchStart = microtime(true);
        $products = $this->productRepository->createQueryBuilder()
            ->fuzzy('name', 'chair')
            ->setFuzzyOptions(0.7, 3)
            ->limit(10)
            ->getResult();
        $results['fuzzy_search'] = microtime(true) - $fuzzySearchStart;
        $results['fuzzy_search_count'] = count($products);

        // Count query
        $countQueryStart = microtime(true);
        $count = $this->productRepository->createQueryBuilder()
            ->eq('isActive', true)
            ->gte('price', 100)
            ->count();
        $results['count_query'] = microtime(true) - $countQueryStart;
        $results['count_query_value'] = $count;

        $results['total'] = microtime(true) - $start;

        return new JsonResponse($results);
    }

    /**
     * Join query operations benchmark
     */
    #[Route('/join-query', name: 'app_performance_join_query', methods: ['GET'])]
    public function joinQueryBenchmark(): JsonResponse
    {
        $start = microtime(true);
        $results = [];

        // Simple join
        $simpleJoinStart = microtime(true);
        $products = $this->productRepository->createJoinQueryBuilder()
            ->eq('isActive', true)
            ->innerJoin(
                'category',
                'categoryId',
                'id',
                'category'
            )
            ->limit(20)
            ->getJoinResult();
        $results['simple_join'] = microtime(true) - $simpleJoinStart;
        $results['simple_join_count'] = count($products);

        // Complex join with filters
        $complexJoinStart = microtime(true);
        $orders = $this->orderRepository->createJoinQueryBuilder()
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
            ->limit(10)
            ->getJoinResult();
        $results['complex_join'] = microtime(true) - $complexJoinStart;
        $results['complex_join_count'] = count($orders);

        // Join with filter on joined entity
        $joinWithFilterStart = microtime(true);
        $products = $this->productRepository->createJoinQueryBuilder()
            ->eq('isActive', true)
            ->innerJoin(
                'review',
                'id',
                'productId',
                'reviews'
            );

        $products->addJoinFilter(
            QueryFilter::gte('rating', 4)
        );

        $productsWithGoodReviews = $products->limit(10)->getJoinResult();
        $results['join_with_filter'] = microtime(true) - $joinWithFilterStart;
        $results['join_with_filter_count'] = count($productsWithGoodReviews);

        $results['total'] = microtime(true) - $start;

        return new JsonResponse($results);
    }

    /**
     * Custom repository methods benchmark
     */
    #[Route('/custom-repository', name: 'app_performance_custom_repository', methods: ['GET'])]
    public function customRepositoryBenchmark(): JsonResponse
    {
        $start = microtime(true);
        $results = [];

        // Category Repository
        $rootCategoriesStart = microtime(true);
        $rootCategories = $this->categoryRepository->findRootCategories();
        $results['root_categories'] = microtime(true) - $rootCategoriesStart;
        $results['root_categories_count'] = count($rootCategories);

        // Product Repository
        $lowStockStart = microtime(true);
        $lowStockProducts = $this->productRepository->findLowStockProducts(10);
        $results['low_stock_products'] = microtime(true) - $lowStockStart;
        $results['low_stock_products_count'] = count($lowStockProducts);

        // Order Repository
        $ordersByStatusStart = microtime(true);
        $completedOrders = $this->orderRepository->findByStatus(Order::STATUS_COMPLETED, 20);
        $results['orders_by_status'] = microtime(true) - $ordersByStatusStart;
        $results['orders_by_status_count'] = count($completedOrders);

        // User Repository
        $recentUsersStart = microtime(true);
        $recentUsers = $this->userRepository->findRecentlyRegistered(30, 20);
        $results['recent_users'] = microtime(true) - $recentUsersStart;
        $results['recent_users_count'] = count($recentUsers);

        // Review Repository
        $topRatedStart = microtime(true);
        $topRatedReviews = $this->reviewRepository->findTopRatedReviews(4, 20);
        $results['top_rated_reviews'] = microtime(true) - $topRatedStart;
        $results['top_rated_reviews_count'] = count($topRatedReviews);

        $results['total'] = microtime(true) - $start;

        return new JsonResponse($results);
    }

    /**
     * Memory usage benchmark
     */
    #[Route('/memory-usage', name: 'app_performance_memory_usage', methods: ['GET'])]
    public function memoryUsageBenchmark(): JsonResponse
    {
        $initMemory = memory_get_usage();
        $results = [];

        // Load large result set
        $results['initial_memory'] = $this->formatBytes($initMemory);

        $loadLargeStart = microtime(true);
        $memoryBefore = memory_get_usage();

        // Load all products
        $products = $this->productRepository->findAll();

        $memoryAfter = memory_get_usage();
        $peakMemory = memory_get_peak_usage();

        $results['large_result_time'] = microtime(true) - $loadLargeStart;
        $results['large_result_count'] = count($products);
        $results['memory_before'] = $this->formatBytes($memoryBefore);
        $results['memory_after'] = $this->formatBytes($memoryAfter);
        $results['memory_increase'] = $this->formatBytes($memoryAfter - $memoryBefore);
        $results['peak_memory'] = $this->formatBytes($peakMemory);

        // Force garbage collection
        unset($products);
        gc_collect_cycles();

        $results['memory_after_gc'] = $this->formatBytes(memory_get_usage());

        // Load batched result set
        $batchSize = 50;
        $batchedStart = microtime(true);
        $memoryBefore = memory_get_usage();

        $totalCount = 0;
        $offset = 0;
        $hasMore = true;

        while ($hasMore) {
            $batchProducts = $this->productRepository->findBy([], [], $batchSize, $offset);
            $batchCount = count($batchProducts);
            $totalCount += $batchCount;

            if ($batchCount < $batchSize) {
                $hasMore = false;
            } else {
                $offset += $batchSize;
            }

            // Free memory
            unset($batchProducts);
            gc_collect_cycles();
        }

        $memoryAfter = memory_get_usage();
        $peakMemory = memory_get_peak_usage();

        $results['batched_result_time'] = microtime(true) - $batchedStart;
        $results['batched_result_count'] = $totalCount;
        $results['batched_memory_before'] = $this->formatBytes($memoryBefore);
        $results['batched_memory_after'] = $this->formatBytes($memoryAfter);
        $results['batched_memory_increase'] = $this->formatBytes($memoryAfter - $memoryBefore);
        $results['batched_peak_memory'] = $this->formatBytes($peakMemory);

        return new JsonResponse($results);
    }

    /**
     * Stress test - multiple concurrent operations
     */
    #[Route('/stress-test', name: 'app_performance_stress_test', methods: ['GET'])]
    public function stressTest(Request $request): JsonResponse
    {
        $iterations = $request->query->getInt('iterations', 10);
        $start = microtime(true);
        $results = [];

        for ($i = 0; $i < $iterations; $i++) {
            // Create new entities
            $category = new Category();
            $category->name = 'Stress Test Category ' . $i;
            $category->description = 'Created during stress test';
            $category->slug = 'stress-test-' . $i;
            $category->position = 1000 + $i;
            $category = $this->syncopateService->create($category);

            $product = new Product();
            $product->name = 'Stress Test Product ' . $i;
            $product->description = 'Created during stress test';
            $product->price = mt_rand(10, 1000) / 10;
            $product->stock = mt_rand(1, 100);
            $product->sku = 'STRESS-' . $i;
            $product->categoryId = $category->id;
            $product = $this->syncopateService->create($product);

            // Perform queries
            $this->productRepository->findLowStockProducts(5);
            $this->categoryRepository->findRootCategories();

            // Update entities
            $category->description = 'Updated during stress test ' . $i;
            $this->syncopateService->update($category);

            $product->stock = mt_rand(1, 100);
            $this->syncopateService->update($product);

            // Delete entities
            $this->syncopateService->delete($product);
            $this->syncopateService->delete($category);

            // Free memory
            unset($category, $product);
            gc_collect_cycles();
        }

        $results['iterations'] = $iterations;
        $results['total_time'] = microtime(true) - $start;
        $results['avg_time_per_iteration'] = $results['total_time'] / $iterations;
        $results['peak_memory'] = $this->formatBytes(memory_get_peak_usage());

        return new JsonResponse($results);
    }

    /**
     * Performance dashboard with all metrics
     */
    #[Route('/dashboard', name: 'app_performance_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $stats = [
            'database' => $this->getDatabaseStats(),
            'entity_counts' => $this->getEntityCounts(),
            'performance_metrics' => $this->getPerformanceMetrics(),
        ];

        // In a real application, you might render a Twig template
        // For simplicity, we'll return JSON
        return new JsonResponse($stats);
    }

    /**
     * Get database statistics
     */
    private function getDatabaseStats(): array
    {
        try {
            $info = $this->syncopateService->getServerInfo();
            $settings = $this->syncopateService->getServerSettings();
            $health = $this->syncopateService->checkHealth();

            return [
                'info' => $info,
                'settings' => $settings,
                'health' => $health,
            ];
        } catch (\Throwable $e) {
            return [
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get entity counts
     */
    private function getEntityCounts(): array
    {
        return [
            'categories' => $this->categoryRepository->count(),
            'products' => $this->productRepository->count(),
            'users' => $this->userRepository->count(),
            'orders' => $this->orderRepository->count(),
            'reviews' => $this->reviewRepository->count(),
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics(): array
    {
        $start = microtime(true);

        // Simple performance measures
        $simpleReadStart = microtime(true);
        $this->categoryRepository->findAll();
        $simpleReadTime = microtime(true) - $simpleReadStart;

        $simpleQueryStart = microtime(true);
        $this->productRepository->findByPriceRange(10, 100, 20);
        $simpleQueryTime = microtime(true) - $simpleQueryStart;

        $complexQueryStart = microtime(true);
        $this->productRepository->findProductsWithReviews(4, 10);
        $complexQueryTime = microtime(true) - $complexQueryStart;

        return [
            'simple_read_time' => $simpleReadTime,
            'simple_query_time' => $simpleQueryTime,
            'complex_query_time' => $complexQueryTime,
            'total_benchmark_time' => microtime(true) - $start,
            'peak_memory' => $this->formatBytes(memory_get_peak_usage()),
            'current_memory' => $this->formatBytes(memory_get_usage()),
        ];
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