<?php

namespace App\Repository;

use App\Entity\FileAsset;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Survos\CoreBundle\Traits\QueryBuilderHelperInterface;
use Survos\CoreBundle\Traits\QueryBuilderHelperTrait;

/**
 * @extends ServiceEntityRepository<FileAsset>
 */
class FileAssetRepository extends ServiceEntityRepository
    implements QueryBuilderHelperInterface
{
    use QueryBuilderHelperTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FileAsset::class);
    }

    //    /**
    //     * @return FileAsset[] Returns an array of FileAsset objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('f.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?FileAsset
    //    {
    //        return $this->createQueryBuilder('f')
    //            ->andWhere('f.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
