<?php

namespace AppBundle\Services;

use aharen\OMDbAPI;
use Doctrine\ORM\EntityManager;
use AppBundle\Entity\Episode;
use AppBundle\Entity\Season;
use AppBundle\Entity\TVShow;

class OMDBService
{

    private $omdb;
    private $em;
    private $status;
    private $kernelRootDir;

    public function __construct($em, $kernelRootdir)
    {
        $this->kernelRootDir = $this->kernelRootDir;
        $this->omdb = new OMDbAPI();
        $this->em = $em->getEntityManager();
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
            $status["code"] = 1;
            $status["content"] = "Erreur lors de la requête sur OMDB";
            return $status;
        }

        if ($details->data->Response == "False") {
            $status["code"] = 2;
            $status["content"] = $details->data->Error;
            return $status;
        }

        $details = $details->data;

        $show = new TVShow();
        $show->setSynopsis($details->Plot);
        $show->setName($details->Title);

        if ($details->Poster != null && $details->Poster != "N/A") {
            $image = file_get_contents($details->Poster);

            $extension = explode(".", $details->Poster);
            $extensionType = $extension[count($extension) - 1];

            $filename = $details->Title . "-" . rand(1, 200);
            $filename = str_replace(' ', '', $filename) . "." . $extensionType;

            $webRoot = ".".$this->kernelRootDir . '/../web';
            $uploadDir = $webRoot . '/uploads/';
            $show->setImage($filename);
            file_put_contents($uploadDir . $filename, $image);
        } else {
            $show->setImage(null);
        }

        $this->em->persist($show);
        $this->em->flush();

        $this->createSeasons($show, $omdbID);

        $status["code"] = 0;
        $status["content"] = $show;

        return $status;
    }

    public function createSeasons(TVShow $show, $omdbID)
    {
        $details = $this->omdb->fetch('i', $omdbID, ["season" => 1]);

        if ($details->code !== 200) {

            $status["code"] = 1;
            $status["content"] = "Erreur lors de la requête sur OMDB";
            return $status;
        }

        if ($details->data->Response == "False") {
            $status["code"] = 2;
            $status["content"] = $details->data->Error;
            return $status;
        }

        $details = $details->data;
        $seasonCount = $details->totalSeasons;

        for ($i = 1; $i <= $seasonCount; $i++) {
            $season = new Season();
            $season->setNumber($i);
            $season->setShow($show);

            $retour = $this->createEpisodes($season, $i, $omdbID);
            if ($retour["code"] != 0) {
                $status["code"] = 3;
                $status["content"] = "Erreur lors de la création des épisodes.";
                return $status;
            }

            $this->em->persist($season);
            $this->em->persist($show);

        }

        $this->em->flush();

        $status["code"] = 0;
        $status["content"] = "Ok";

        return $status;
    }

    public function createEpisodes(Season $season, $seasonNumber, $omdbID)
    {
        $details = $this->omdb->fetch('i', $omdbID, ["season" => $seasonNumber]);

        if ($details->code !== 200) {

            $status["code"] = 1;
            $status["content"] = "Erreur lors de la requête sur OMDB";
            return $status;
        }

        if ($details->data->Response == "False") {
            $status["code"] = 2;
            $status["content"] = $details->data->Error;
            return $status;
        }

        $details = $details->data;

        foreach ($details->Episodes as $episodeInfo) {
            $episode = new Episode();
            $episode->setSeason($season);
            $episode->setNumber($episodeInfo->Episode);
            $episode->setName($episodeInfo->Title);

            if ($episodeInfo->Released != "N/A") {
                $date = new \DateTime($episodeInfo->Released);
                $episode->setDate($date);
            }
            $this->em->persist($episode);
        }

        $this->em->persist($season);
        $this->em->flush();

        return ["code" => 0, "content" => 'Ok'];
    }


}