<?php

namespace App\Repository;

use App\Entity\Service;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Service>
 */
class ServiceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Service::class);
    }

    public function findActiveServices(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search services by name, description, or category
     */
    public function searchServices(string $search, ?int $categoryId = null): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->where('s.isActive = :active')
            ->setParameter('active', true);

        if (!empty($search)) {
            $qb->andWhere('s.name LIKE :search OR s.description LIKE :search OR c.name LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($categoryId !== null && $categoryId > 0) {
            $qb->andWhere('s.category = :categoryId')
               ->setParameter('categoryId', $categoryId);
        }

        return $qb->orderBy('c.name', 'ASC')
            ->addOrderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find services by category
     */
    public function findByCategory(int $categoryId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.category = :categoryId')
            ->andWhere('s.isActive = :active')
            ->setParameter('categoryId', $categoryId)
            ->setParameter('active', true)
            ->orderBy('s.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
