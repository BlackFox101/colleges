<?php
declare(strict_types=1);

namespace App\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\College;

/**
 * @extends ServiceEntityRepository<College>
 *
 * @method College|null find($id, $lockMode = null, $lockVersion = null)
 * @method College|null findOneBy(array $criteria, array $orderBy = null)
 * @method College[]    findAll()
 * @method College[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class CollegeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, College::class);
    }

    public function setAllCollegesIsDeprecated(): int
    {
        $qb = $this->createQueryBuilder('c')
            ->update('App:College', 'c')
            ->set('c.isDeprecated', true);

        return $qb->getQuery()->execute();
    }

    public function deleteDeprecatedColleges(): int
    {
        $qb = $this->createQueryBuilder('c')
            ->delete('App:College', 'c')
            ->where('c.isDeprecated = 1');

        return $qb->getQuery()->execute();
    }
}
