<?php

namespace AppBundle\Service;

use aharen\OMDbAPI;
use AppBundle\Entity\Season;
use AppBundle\Entity\TVShow;
use Doctrine\ORM\EntityManager;

class OMDBService
{

    private $omdb;
    private $em;
    private $status;

    public function __construct(EntityManager $em)
    {
        $this->omdb = new OMDbAPI();
        $this->em = $em;
        $this->status = array(
            "code" => 0,
            "content" => null
        );
    }

    /**
     * @param $omdbID
     * @return array
     */
    public function createTVShow($omdbID)
    {
        $details = $this->omdb->fetch('i', $omdbID);

        if ($details->code != 200) {
            $this->status["code"] = 1;
            $this->status["content"] = "Erreur lors de la requête sur OMDB";
            return $this->status;
        }

        if ($details->data->Response == "False") {
            $this->status["code"] = 2;
            $this->status["content"] = $details->data->Error;
            return $this->status;
        }

        $details = $details->data;

        dump($details);

        $show = new TVShow();
        $show->setSynopsis($details->Plot);
        $show->setName($details->Title);
        $show->setImage($details->Poster); //FIXME Il faut télécharger l'image

        $this->em->persist($show);
        $this->em->flush();

        $this->createSeasons($show, $omdbID);

        $this->status["code"] = 0;
        $this->status["content"] = $show;

        return $this->status;
    }

    public function createSeasons(TVShow $show, $omdbID)
    {
        $details = $this->omdb->fetch('i', $omdbID, ["Season" => 1]);

        if ($details->code !== 200) {
            $this->status["code"] = 1;
            $this->status["content"] = "Erreur lors de la requête sur OMDB";
            return $this->status;
        }

        if ($details->data->Response == "False") {
            $this->status["code"] = 2;
            $this->status["content"] = $details->data->Error;
            return $this->status;
        }

        $details = $details->data;
        $seasonCount = $details->totalSeasons;

        for ($i = 1; $i < $seasonCount; $i++) {
            $season = new Season();
            $season->setNumber($i);
            $this->em->persist($season);

            $retour = $this->createEpisodes($show, $i, $omdbID);
            if ($retour["code"] != 0) {
                $this->status["code"] = 3;
                $this->status["content"] = "Erreur lors de la création des épisodes.";
                return $this->status;
            }

            $show->addSeason($season);
            //$this->em->flush();
        }

        $this->status["code"] = 0;
        $this->status["content"] = "Ok";
    }

    public function createEpisodes(TVShow $show, $seasonNumber, $omdbID)
    {
        return $this->status;
    }
}