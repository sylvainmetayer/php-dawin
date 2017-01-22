<?php

namespace AppBundle\Repository;

use DateTime;

/**
 * EpisodeRepository
 *
 * This class was generated by the Doctrine ORM. Add your own custom
 * repository methods below.
 */
class EpisodeRepository extends \Doctrine\ORM\EntityRepository
{

    /**
     * Cette fonction retourne les épisodes à venir, par rapport à la date courante.
     * @return array
     */
    public function findNextCalendar()
    {
        $date = new DateTime();
        $em = $this->getEntityManager();
        $queryBuilder = $em->createQueryBuilder();

        $query = $queryBuilder->select('e')
            ->from('AppBundle:Episode', 'e')
            ->where("e.date > :date")
            ->setParameter("date", $date->format("Y-m-d"))
            ->orderBy("e.date", "ASC")
            ->getQuery();

        return $query->getResult();
    }

    public function search($data)
    {
        $em = $this->getEntityManager();
        $queryBuilder = $em->createQueryBuilder();

        $query = $queryBuilder->select("e")
            ->from("AppBundle:Episode", 'e');

        $query
            ->where("e.name like :name")
            ->setParameter("name", "%" . $data . "%");


        return $query->getQuery()->getResult();
    }

}
