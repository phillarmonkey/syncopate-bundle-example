# SyncopateBundle Performance Test Project

A Symfony project for comprehensive performance testing and benchmarking of the Phillarmonic SyncopateBundle,
a Symfony integration bundle for SyncopateDB.

## Overview

This project provides a complete testing environment for evaluating the performance, reliability, and scalability of the SyncopateBundle with varying data loads. It includes data generation commands, benchmarking controllers, and entity models that reflect real-world usage patterns.

## Features

- **Test Data Generation**: Command line tooling to generate configurable amounts of test data
- **Performance Benchmarking**: API endpoints for measuring various aspects of performance
- **Entity Relationships**: Complete entity model with relationships matching real-world scenarios
- **Memory Usage Testing**: Tools for analyzing memory consumption patterns
- **Stress Testing**: Endpoints for simulating heavy usage scenarios

## Project Structure

```
├── src/
│   ├── Command/
│   │   └── GenerateDataCommand.php    # Command to populate test data
│   ├── Controller/
│   │   └── SyncopateBenchmarkController.php  # Benchmark API endpoints
│   ├── Entity/
│   │   ├── Category.php
│   │   ├── Order.php
│   │   ├── OrderItem.php
│   │   ├── Product.php
│   │   ├── Review.php
│   │   ├── Tag.php
│   │   └── User.php
│   └── Repository/
│       ├── CategoryRepository.php
│       ├── OrderRepository.php
│       ├── ProductRepository.php
│       ├── ReviewRepository.php
│       └── UserRepository.php
└── vendor/
    └── phillarmonic/
        └── syncopate-bundle/  # The bundle being tested
```

## Installation

1. Clone this repository:
   ```bash
   git clone https://github.com/your-organization/syncopate-test-project.git
   cd syncopate-test-project
   ```

2. Install dependencies:
   ```bash
   composer install
   ```

3. Configure your `.env` file with SyncopateDB connection:
   ```
   SYNCOPATE_BASE_URL=http://localhost:8080
   SYNCOPATE_TIMEOUT=30
   ```

4. Update your `config/packages/phillarmonic_syncopate.yaml`:
   ```yaml
   phillarmonic_syncopate:
       base_url: '%env(SYNCOPATE_BASE_URL)%'
       timeout: '%env(int:SYNCOPATE_TIMEOUT)%'
       entity_paths:
           - '%kernel.project_dir%/src/Entity'
       auto_create_entity_types: true
       cache_entity_types: true
       cache_ttl: 3600
   ```

## Usage

### Generating Test Data

Generate test data using the provided command:

```bash
# Generate default amounts of test data
php bin/console app:generate-data

# Generate custom amounts of test data
php bin/console app:generate-data --categories=50 --products=1000 --users=200 --orders=500 --reviews=1000

# Clear existing data before generating new data
php bin/console app:generate-data --clear
```

Command options:
- `--categories`: Number of categories to create (default: 20)
- `--tags`: Number of tags to create (default: 30)
- `--users`: Number of users to create (default: 100)
- `--products`: Number of products to create (default: 500)
- `--orders`: Number of orders to create (default: 200)
- `--reviews`: Number of reviews to create (default: 300)
- `--batch`: Batch size for processing (default: 20)
- `--clear`: Clear existing data before generating new

### Running Benchmarks

The benchmark controller provides multiple API endpoints for performance testing:

- `GET /api/benchmark/dashboard` - Overall system stats and entity counts
- `GET /api/benchmark/crud` - Basic CRUD operations benchmark
- `GET /api/benchmark/bulk?count=100` - Bulk operations benchmark (configurable count)
- `GET /api/benchmark/query` - Query performance benchmark
- `GET /api/benchmark/join` - Join query performance benchmark
- `GET /api/benchmark/custom-repository` - Custom repository methods benchmark
- `GET /api/benchmark/memory?batch_size=50` - Memory usage benchmark (configurable batch size)
- `GET /api/benchmark/stress?iterations=10` - Stress test with multiple operations
- `GET /api/benchmark/raw-query` - Raw query options benchmark

Each endpoint returns detailed metrics including:
- Execution time
- Memory consumption
- Result counts

## Debugging

The SyncopateBundle includes a `DebugHelper` utility class that can be used to troubleshoot memory issues or data type errors. Example:

```php
use Phillarmonic\SyncopateBundle\Util\DebugHelper;

// Get memory usage
$memoryUsage = DebugHelper::getMemoryUsage();

// Check array for problematic data types
$issues = DebugHelper::checkArrayForProblematicTypes($data);

// Enable debug mode
DebugHelper::enableDebug();

// Set custom logging
DebugHelper::setLogCallback(function($message, $context) {
    // Your custom logging here
});
```

## Performance Optimization Strategies

This project demonstrates several strategies for optimizing performance with SyncopateDB:

1. **Batch Processing**: Load large datasets in smaller batches
2. **Memory Management**: Explicitly release memory with `unset()` and `gc_collect_cycles()`
3. **Optimized Queries**: Use specific query filters to reduce result sets
4. **Join Optimization**: Structure joins for optimal performance

## Entity Relationships

The test project includes the following entities with relationships:

- **Category**: Hierarchical structure with parent/child relationships
- **Product**: Belongs to category, has many reviews
- **User**: Has many orders and reviews
- **Order**: Belongs to user, has many order items
- **OrderItem**: Belongs to order, references a product
- **Review**: Belongs to user and product
- **Tag**: Many-to-many with products

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

## License

This project is open-sourced software licensed under the MIT license.