<?php

namespace App\Repository;

use App\Entity\Order;
use Phillarmonic\SyncopateBundle\Mapper\EntityMapper;
use Phillarmonic\SyncopateBundle\Model\QueryFilter;
use Phillarmonic\SyncopateBundle\Repository\EntityRepository;
use DateTimeInterface;
use Phillarmonic\SyncopateBundle\Service\SyncopateService;

class OrderRepository extends EntityRepository
{
    public function __construct(SyncopateService $syncopateService, EntityMapper $entityMapper, string $entityClass)
    {
        parent::__construct($syncopateService, $entityMapper, $entityClass);
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status, int $limit = 50): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'status', value: $status)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find orders by date range
     */
    public function findByDateRange(DateTimeInterface $startDate, DateTimeInterface $endDate, int $limit = 100): array
    {
        return $this->createQueryBuilder()
            ->gte(field: 'createdAt', value: $startDate->format(DateTimeInterface::ATOM))
            ->lte(field: 'createdAt', value: $endDate->format(DateTimeInterface::ATOM))
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find orders by user
     */
    public function findByUser(string $userId, int $limit = 50): array
    {
        return $this->createQueryBuilder()
            ->eq(field: 'userId', value: $userId)
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Find orders with items by user (using join)
     */
    public function findOrdersWithItemsByUser(string $userId, int $limit = 20): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->eq(field: 'userId', value: $userId)
            ->innerJoin(
                entityType: 'order_item',
                localField: 'id',
                foreignField: 'orderId',
                as: 'items'
            )
            ->orderBy(field: 'createdAt', direction: 'DESC')
            ->limit($limit);

        return $joinQueryBuilder->getJoinResult();
    }

    /**
     * Find orders containing a specific product
     */
    public function findOrdersContainingProduct(string $productId, int $limit = 20): array
    {
        $joinQueryBuilder = $this->createJoinQueryBuilder()
            ->innerJoin(
                entityType: 'order_item',
                localField: 'id',
                foreignField: 'orderId',
                as: 'items'
            );

        $joinQueryBuilder->addJoinFilter(
            QueryFilter::eq('productId', $productId)
        );

        return $joinQueryBuilder->limit($limit)->getJoinResult();
    }

    /**
     * Find orders with total amount in range
     */
    public function findByTotalAmountRange(float $minAmount, float $maxAmount, int $limit = 50): array
    {
        return $this->createQueryBuilder()
            ->gte(field: 'totalAmount', value: $minAmount)
            ->lte(field: 'totalAmount', value: $maxAmount)
            ->orderBy(field: 'totalAmount', direction: 'DESC')
            ->limit($limit)
            ->getResult();
    }

    /**
     * Get order statistics
     */
    public function getOrderStatistics(): array
    {
        $totalCount = $this->count();
        $pendingCount = $this->count(['status' => Order::STATUS_PENDING]);
        $completedCount = $this->count(['status' => Order::STATUS_COMPLETED]);
        $cancelledCount = $this->count(['status' => Order::STATUS_CANCELLED]);

        $orders = $this->findAll();
        $totalRevenue = 0;
        $averageOrderValue = 0;

        if (!empty($orders)) {
            $totalRevenue = array_reduce($orders, function ($carry, $order) {
                return $carry + ($order->status !== Order::STATUS_CANCELLED ? $order->totalAmount : 0);
            }, 0);

            $completedOrders = array_filter($orders, fn($order) => $order->status !== Order::STATUS_CANCELLED);
            if (!empty($completedOrders)) {
                $averageOrderValue = $totalRevenue / count($completedOrders);
            }
        }

        return [
            'totalCount' => $totalCount,
            'pendingCount' => $pendingCount,
            'completedCount' => $completedCount,
            'cancelledCount' => $cancelledCount,
            'totalRevenue' => $totalRevenue,
            'averageOrderValue' => $averageOrderValue
        ];
    }
}