<?php

namespace App\Command;

use App\Entity\Category;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\Review;
use App\Entity\Tag;
use App\Entity\User;
use Faker\Factory;
use Faker\Generator;
use Phillarmonic\SyncopateBundle\Service\SyncopateService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:generate-data',
    description: 'Generate test data for SyncopateDB',
)]
class GenerateDataCommand extends Command
{
    private SyncopateService $syncopateService;
    private Generator $faker;
    private array $categoryIds = [];
    private array $tagIds = [];
    private array $userIds = [];
    private array $productIds = [];
    private array $orderIds = [];

    // Default values - can be overridden by options
    private int $categoryCount = 20;
    private int $tagCount = 30;
    private int $userCount = 100;
    private int $productCount = 500;
    private int $orderCount = 200;
    private int $reviewCount = 300;
    private int $batchSize = 20;

    public function __construct(SyncopateService $syncopateService)
    {
        parent::__construct();
        $this->syncopateService = $syncopateService;
        $this->faker = Factory::create();
    }

    protected function configure(): void
    {
        $this
            ->addOption('categories', null, InputOption::VALUE_REQUIRED, 'Number of categories to create', $this->categoryCount)
            ->addOption('tags', null, InputOption::VALUE_REQUIRED, 'Number of tags to create', $this->tagCount)
            ->addOption('users', null, InputOption::VALUE_REQUIRED, 'Number of users to create', $this->userCount)
            ->addOption('products', null, InputOption::VALUE_REQUIRED, 'Number of products to create', $this->productCount)
            ->addOption('orders', null, InputOption::VALUE_REQUIRED, 'Number of orders to create', $this->orderCount)
            ->addOption('reviews', null, InputOption::VALUE_REQUIRED, 'Number of reviews to create', $this->reviewCount)
            ->addOption('batch', null, InputOption::VALUE_REQUIRED, 'Batch size for processing', $this->batchSize)
            ->addOption('clear', null, InputOption::VALUE_NONE, 'Clear existing data before generating new')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('SyncopateDB Data Generator');

        // Get options
        $this->categoryCount = (int) $input->getOption('categories');
        $this->tagCount = (int) $input->getOption('tags');
        $this->userCount = (int) $input->getOption('users');
        $this->productCount = (int) $input->getOption('products');
        $this->orderCount = (int) $input->getOption('orders');
        $this->reviewCount = (int) $input->getOption('reviews');
        $this->batchSize = (int) $input->getOption('batch');
        $clearData = $input->getOption('clear');

        $io->section('Configuration');
        $io->table(
            ['Entity', 'Count'],
            [
                ['Categories', $this->categoryCount],
                ['Tags', $this->tagCount],
                ['Users', $this->userCount],
                ['Products', $this->productCount],
                ['Orders', $this->orderCount],
                ['Reviews', $this->reviewCount]
            ]
        );

        // Clear existing data if requested
        if ($clearData) {
            $io->section('Clearing Existing Data');
            $this->clearData($io);
        }

        // Generate data in the correct order of dependencies
        $io->section('Generating Data');

        $this->generateCategories($io);
        $this->generateTags($io);
        $this->generateUsers($io);
        $this->generateProducts($io);
        $this->generateOrders($io);
        $this->generateReviews($io);

        $io->success('Data generation completed!');

        return Command::SUCCESS;
    }

    /**
     * Clear existing data
     */
    private function clearData(SymfonyStyle $io): void
    {
        $entityTypes = ['review', 'order_item', 'order', 'product', 'user', 'tag', 'category'];

        foreach ($entityTypes as $entityType) {
            $io->write("Clearing $entityType data... ");

            // Unfortunately, there's no bulk delete in SyncopateDB yet,
            // so we need to fetch all entities and delete them one by one

            try {
                // For each entity type, map to the correct entity class
                $entityClass = match ($entityType) {
                    'review' => Review::class,
                    'order_item' => OrderItem::class,
                    'order' => Order::class,
                    'product' => Product::class,
                    'user' => User::class,
                    'tag' => Tag::class,
                    'category' => Category::class,
                    default => null
                };

                if ($entityClass) {
                    $entities = $this->syncopateService->findAll($entityClass);
                    $count = count($entities);

                    if ($count > 0) {
                        $progressBar = new ProgressBar($io, $count);
                        $progressBar->start();

                        foreach ($entities as $entity) {
                            $this->syncopateService->delete($entity);
                            $progressBar->advance();
                        }

                        $progressBar->finish();
                        $io->writeln(" <info>$count entities deleted</info>");
                    } else {
                        $io->writeln(" <info>No entities found</info>");
                    }
                }
            } catch (\Throwable $e) {
                $io->writeln(" <error>Failed to clear data: {$e->getMessage()}</error>");
            }
        }
    }

    /**
     * Generate categories with a hierarchical structure
     */
    private function generateCategories(SymfonyStyle $io): void
    {
        $io->writeln('Generating categories...');
        $progressBar = new ProgressBar($io, $this->categoryCount);
        $progressBar->start();

        // First create root categories (about 30%)
        $rootCategoryCount = max(1, intval($this->categoryCount * 0.3));
        $rootCategories = [];

        for ($i = 0; $i < $rootCategoryCount; $i++) {
            $category = new Category();
            $category->name = $this->faker->unique()->word . ' ' . $this->faker->word;
            $category->description = $this->faker->sentence;
            $category->slug = $this->createSlug($category->name);
            $category->position = $i;

            try {
                $category = $this->syncopateService->create($category);
                $this->categoryIds[] = $category->id;
                $rootCategories[] = $category;
            } catch (\Throwable $e) {
                $io->warning("Failed to create category: {$e->getMessage()}");
            }

            $progressBar->advance();

            // Garbage collection to avoid memory issues
            if ($i % $this->batchSize === 0) {
                gc_collect_cycles();
            }
        }

        // Create subcategories
        $remainingCount = $this->categoryCount - $rootCategoryCount;
        $currentParents = $rootCategories;

        while ($remainingCount > 0 && !empty($currentParents)) {
            $newParents = [];

            foreach ($currentParents as $parent) {
                // Create 2-4 subcategories per parent
                $subcategoryCount = min($remainingCount, $this->faker->numberBetween(2, 4));
                $remainingCount -= $subcategoryCount;

                for ($i = 0; $i < $subcategoryCount; $i++) {
                    $category = new Category();
                    $category->name = $this->faker->unique()->word . ' ' . $this->faker->word;
                    $category->description = $this->faker->sentence;
                    $category->slug = $this->createSlug($category->name);
                    $category->position = $i;
                    $category->parentId = $parent->id;

                    try {
                        $category = $this->syncopateService->create($category);
                        $this->categoryIds[] = $category->id;
                        $newParents[] = $category;
                    } catch (\Throwable $e) {
                        $io->warning("Failed to create subcategory: {$e->getMessage()}");
                    }

                    $progressBar->advance();

                    // Garbage collection to avoid memory issues
                    if ($i % $this->batchSize === 0) {
                        gc_collect_cycles();
                    }

                    if ($remainingCount <= 0) {
                        break 2;
                    }
                }
            }

            $currentParents = $newParents;
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Categories generated: ' . count($this->categoryIds));
    }

    /**
     * Generate tags
     */
    private function generateTags(SymfonyStyle $io): void
    {
        $io->writeln('Generating tags...');
        $progressBar = new ProgressBar($io, $this->tagCount);
        $progressBar->start();

        for ($i = 0; $i < $this->tagCount; $i++) {
            $tag = new Tag();
            $tag->name = $this->faker->unique()->word;
            $tag->slug = $this->createSlug($tag->name);
            $tag->color = $this->faker->hexColor;

            try {
                $tag = $this->syncopateService->create($tag);
                $this->tagIds[] = $tag->id;
            } catch (\Throwable $e) {
                $io->warning("Failed to create tag: {$e->getMessage()}");
            }

            $progressBar->advance();

            // Garbage collection to avoid memory issues
            if ($i % $this->batchSize === 0) {
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Tags generated: ' . count($this->tagIds));
    }

    /**
     * Generate users
     */
    private function generateUsers(SymfonyStyle $io): void
    {
        $io->writeln('Generating users...');
        $progressBar = new ProgressBar($io, $this->userCount);
        $progressBar->start();

        for ($i = 0; $i < $this->userCount; $i++) {
            $user = new User();
            $user->email = $this->faker->unique()->safeEmail();
            $user->firstName = $this->faker->firstName();
            $user->lastName = $this->faker->lastName();
            $user->phoneNumber = $this->faker->phoneNumber();
            $user->isActive = $this->faker->boolean(90); // 90% active

            // Random preferences
            if ($this->faker->boolean(70)) {
                $user->preferences = [
                    'newsletter' => $this->faker->boolean(60),
                    'theme' => $this->faker->randomElement(['light', 'dark', 'auto']),
                    'language' => $this->faker->randomElement(['en', 'fr', 'de', 'es']),
                ];
            }

            try {
                $user = $this->syncopateService->create($user);
                $this->userIds[] = $user->id;
            } catch (\Throwable $e) {
                $io->warning("Failed to create user: {$e->getMessage()}");
            }

            $progressBar->advance();

            // Garbage collection to avoid memory issues
            if ($i % $this->batchSize === 0) {
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Users generated: ' . count($this->userIds));
    }

    /**
     * Generate products
     */
    private function generateProducts(SymfonyStyle $io): void
    {
        if (empty($this->categoryIds)) {
            $io->warning('No categories available. Skipping product generation.');
            return;
        }

        $io->writeln('Generating products...');
        $progressBar = new ProgressBar($io, $this->productCount);
        $progressBar->start();

        for ($i = 0; $i < $this->productCount; $i++) {
            $product = new Product();
            $product->name = $this->faker->words(3, true);
            $product->description = $this->faker->paragraph();
            $product->price = $this->faker->randomFloat(2, 5, 1000);
            $product->stock = $this->faker->numberBetween(0, 100);
            $product->sku = 'SKU-' . $this->faker->unique()->numberBetween(10000, 99999);
            $product->isActive = $this->faker->boolean(90); // 90% active

            // Random attributes
            $product->attributes = [
                'color' => $this->faker->safeColorName(),
                'size' => $this->faker->randomElement(['S', 'M', 'L', 'XL']),
                'weight' => $this->faker->randomFloat(2, 0.1, 10) . ' kg',
                'material' => $this->faker->word(),
            ];

            // Assign to a random category
            $product->categoryId = $this->faker->randomElement($this->categoryIds);

            try {
                $product = $this->syncopateService->create($product);
                $this->productIds[] = $product->id;

                // Assign random tags (0-5)
                if (!empty($this->tagIds)) {
                    $tagCount = $this->faker->numberBetween(0, min(5, count($this->tagIds)));
                    $randomTagIds = $this->faker->randomElements($this->tagIds, $tagCount);

                    // Since SyncopateDB doesn't have a direct many-to-many relationship API,
                    // we would need a separate junction table or mechanism for this
                    // For simplicity, we're skipping this step in this example
                }
            } catch (\Throwable $e) {
                $io->warning("Failed to create product: {$e->getMessage()}");
            }

            $progressBar->advance();

            // Garbage collection to avoid memory issues
            if ($i % $this->batchSize === 0) {
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Products generated: ' . count($this->productIds));
    }

    /**
     * Generate orders with order items
     */
    private function generateOrders(SymfonyStyle $io): void
    {
        if (empty($this->userIds) || empty($this->productIds)) {
            $io->warning('No users or products available. Skipping order generation.');
            return;
        }

        $io->writeln('Generating orders and order items...');
        $progressBar = new ProgressBar($io, $this->orderCount);
        $progressBar->start();

        $statuses = [
            Order::STATUS_PENDING => 20,
            Order::STATUS_PROCESSING => 30,
            Order::STATUS_COMPLETED => 40,
            Order::STATUS_CANCELLED => 10,
        ];

        for ($i = 0; $i < $this->orderCount; $i++) {
            $order = new Order();
            $order->orderNumber = 'ORD-' . $this->faker->unique()->numberBetween(100000, 999999);

            // Weighted status selection
            $order->status = $this->getWeightedRandomStatus($statuses);

            // Random user
            $order->userId = $this->faker->randomElement($this->userIds);

            // Order dates
            $order->createdAt = $this->faker->dateTimeBetween('-1 year', 'now');
            if ($order->status === Order::STATUS_COMPLETED) {
                $completedDate = clone $order->createdAt;
                $completedDate->modify('+' . $this->faker->numberBetween(1, 14) . ' days');
                $order->completedAt = $completedDate;
            }

            // Random addresses
            $order->shippingAddress = $this->generateRandomAddress();

            // 70% chance to have same billing address
            if ($this->faker->boolean(70)) {
                $order->billingAddress = $order->shippingAddress;
            } else {
                $order->billingAddress = $this->generateRandomAddress();
            }

            try {
                // Save order to get ID
                $order = $this->syncopateService->create($order);
                $this->orderIds[] = $order->id;

                // Create order items (1-5 per order)
                $itemCount = $this->faker->numberBetween(1, 5);
                $totalAmount = 0;
                $usedProductIds = []; // To avoid duplicates in the same order

                for ($j = 0; $j < $itemCount; $j++) {
                    // Select a random product not already in this order
                    $availableProductIds = array_diff($this->productIds, $usedProductIds);
                    if (empty($availableProductIds)) {
                        break; // No more unique products available
                    }

                    $productId = $this->faker->randomElement($availableProductIds);
                    $usedProductIds[] = $productId;

                    try {
                        $product = $this->syncopateService->getById(Product::class, $productId);

                        $item = new OrderItem();
                        $item->productId = $product->id;
                        $item->orderId = $order->id;
                        $item->productName = $product->name;
                        $item->productSku = $product->sku;
                        $item->price = $product->price;
                        $item->quantity = $this->faker->numberBetween(1, 3);
                        $item->subtotal = $item->price * $item->quantity;

                        // Save the item
                        $this->syncopateService->create($item);

                        $totalAmount += $item->subtotal;
                    } catch (\Throwable $e) {
                        $io->warning("Failed to create order item: {$e->getMessage()}");
                    }
                }

                // Update order total
                $order->totalAmount = $totalAmount;
                $this->syncopateService->update($order);
            } catch (\Throwable $e) {
                $io->warning("Failed to create order: {$e->getMessage()}");
            }

            $progressBar->advance();

            // Garbage collection to avoid memory issues
            if ($i % $this->batchSize === 0) {
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Orders generated: ' . count($this->orderIds));
    }

    /**
     * Generate reviews
     */
    private function generateReviews(SymfonyStyle $io): void
    {
        if (empty($this->userIds) || empty($this->productIds)) {
            $io->warning('No users or products available. Skipping review generation.');
            return;
        }

        $io->writeln('Generating reviews...');
        $progressBar = new ProgressBar($io, $this->reviewCount);
        $progressBar->start();

        for ($i = 0; $i < $this->reviewCount; $i++) {
            $review = new Review();

            // Random product
            $review->productId = $this->faker->randomElement($this->productIds);

            // Random user
            $review->userId = $this->faker->randomElement($this->userIds);

            // Rating (weighted towards better ratings)
            $ratings = [5, 5, 5, 4, 4, 4, 4, 3, 3, 2, 1];
            $review->rating = $this->faker->randomElement($ratings);

            // Content
            $review->title = $this->faker->boolean(80) ? $this->faker->sentence(4) : null;
            $review->content = $this->faker->paragraph();

            // Status
            $review->isVerified = $this->faker->boolean(60);
            $review->isApproved = $this->faker->boolean(90);

            // Date (recent)
            $review->createdAt = $this->faker->dateTimeBetween('-6 months', 'now');

            try {
                $this->syncopateService->create($review);
            } catch (\Throwable $e) {
                $io->warning("Failed to create review: {$e->getMessage()}");
            }

            $progressBar->advance();

            // Garbage collection to avoid memory issues
            if ($i % $this->batchSize === 0) {
                gc_collect_cycles();
            }
        }

        $progressBar->finish();
        $io->newLine(2);
        $io->success('Reviews generated: ' . $this->reviewCount);
    }

    /**
     * Generate a random address
     */
    private function generateRandomAddress(): array
    {
        return [
            'firstName' => $this->faker->firstName(),
            'lastName' => $this->faker->lastName(),
            'street' => $this->faker->streetAddress(),
            'city' => $this->faker->city(),
            'state' => $this->faker->state(),
            'postalCode' => $this->faker->postcode(),
            'country' => $this->faker->country(),
            'phone' => $this->faker->phoneNumber(),
        ];
    }

    /**
     * Create a URL-friendly slug from a string
     */
    private function createSlug(string $string): string
    {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9-]+/', '-', $string), '-'));
    }

    /**
     * Get a random status based on weights
     */
    private function getWeightedRandomStatus(array $statuses): string
    {
        $totalWeight = array_sum($statuses);
        $randomValue = $this->faker->numberBetween(1, $totalWeight);

        $currentWeight = 0;
        foreach ($statuses as $status => $weight) {
            $currentWeight += $weight;
            if ($randomValue <= $currentWeight) {
                return $status;
            }
        }

        // Fallback (should never reach here)
        return array_key_first($statuses);
    }
}