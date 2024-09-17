<?php
namespace App\Repository;

use App\Entity\Scan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @method Scan|null find($id, $lockMode = null, $lockVersion = null)
 * @method Scan|null findOneBy(array $criteria, array $orderBy = null)
 * @method Scan[]    findAll()
 * @method Scan[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class ScanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Scan::class);
    }
}