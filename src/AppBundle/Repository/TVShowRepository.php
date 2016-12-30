<?php

namespace AppBundle\Repository;

use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * TVShowRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class TVShowRepository extends \Doctrine\ORM\EntityRepository
{

    public function getTVShow($first_result, $max_results = 20)
    {
        $qb = $this->createQueryBuilder('t');
        $qb
            ->select('t')
            ->setFirstResult($first_result*$max_results)
            ->setMaxResults($max_results);

        $pag = new Paginator($qb);
        return $pag;
    }
}
