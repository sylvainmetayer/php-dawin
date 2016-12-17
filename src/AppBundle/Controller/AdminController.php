<?php

namespace AppBundle\Controller;

use aharen\OMDbAPI;
use AppBundle\Entity\Episode;
use AppBundle\Entity\Season;
use AppBundle\Entity\TVShow;
use AppBundle\Forms\EpisodeType;
use AppBundle\Forms\ShowType;
use AppBundle\Service\OMDBService;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * @Route("/admin")
 */
class AdminController extends Controller
{
    /**
     * @Route("/addShow", name="admin_add_show")
     * @Template()
     * @param Request $request
     * @return array
     */
    public function addShowAction(Request $request)
    {
        $show = new TVShow;
        $form = $this->createForm(ShowType::class, $show);
        $success = false;

        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $file = $show->getImage();
            if ($file) {
                // Handling file upload
                $filename = md5(uniqid()) . '.' . $file->guessExtension();
                $webRoot = $this->get('kernel')->getRootDir() . '/../web';

                $file->move($webRoot . '/uploads', $filename);
                $show->setImage($filename);
            }

            $em = $this->get('doctrine')->getManager();
            $em->persist($show);
            $em->flush();
            $success = true;
        }

        return [
            'form' => $form->createView(),
            'success' => $success
        ];
    }

    /**
     * @Route("/addSeason/{id}", name="admin_add_season")
     * @param $id
     * @return RedirectResponse
     */
    public function addSeasonAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:TVShow');

        if ($show = $repo->find($id)) {
            $season = new Season;
            $season
                ->setShow($show)
                ->setNumber(count($show->getSeasons()) + 1);
            $em->persist($season);
            $em->flush();
        }

        return $this->redirect($this->generateUrl('show', ['id' => $id]));
    }

    /**
     * @Route("/deleteEpisode/{id}", name="admin_delete_episode")
     */
    public function deleteEpisodeAction($id)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Episode');
        if ($episode = $repo->find($id)) {
            $id = $episode->getSeason()->getShow()->getId();
            $em->remove($episode);
            $em->flush();
            return $this->redirect($this->generateUrl('show', ['id' => $id]));
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }
    }

    /**
     * @Route("/deleteSeason/{id}", name="admin_delete_season")
     */
    public function deleteSeasonAction($id)
    {
        /** @var EntityManager $em */
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Season');

        /** @var Season $season */
        if ($season = $repo->find($id)) {
            /** @var Episode $episode */
            foreach ($season->getEpisodes() as $episode) {
                $em->remove($episode);
            }

            $em->remove($season);
            $em->flush();
            return $this->redirect($this->generateUrl('show', ['id' => $season->getShow()->getId()]));
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }
    }

    /**
     * @Route("/addEpisode/{id}", name="admin_add_episode")
     * @Template()
     */
    public function addEpisodeAction($id, Request $request)
    {
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:Season');

        if ($season = $repo->find($id)) {
            $episode = new Episode;
            $episode
                ->setSeason($season)
                ->setNumber(count($season->getEpisodes()) + 1);

            $form = $this->createForm(EpisodeType::class, $episode);

            $form->handleRequest($request);
            if ($form->isSubmitted() && $form->isValid()) {
                $em->persist($episode);
                $em->flush();
                return $this->redirect($this->generateUrl('show', [
                    'id' => $episode->getSeason()->getShow()->getId()
                ]));
            }
        } else {
            return $this->redirect($this->generateUrl('homepage'));
        }

        return [
            'form' => $form->createView()
        ];
    }

    /**
     * @Route("/omdb", name="admin_omdb")
     * @Template()
     * @param Request $request
     * @return array|RedirectResponse
     */
    public function omdbAction(Request $request)
    {
        $form = $this->createFormBuilder()
            ->add('keyword')
            ->getForm();

        $result = [];
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $omdb = new OMDbAPI();

            // TODO Externaliser dans un service
            $result = $omdb->search($data['keyword']);

            if ($result->code != 200) {
                $request->getSession()->getFlashBag()->add('danger', 'Erreur lors de la requête sur OMDB.');
                return $this->redirectToRoute("admin_omdb");
            }

            if ($result->data->Response == "False") {
                $request->getSession()->getFlashBag()->add('danger', $result->data->Error);
                return $this->redirectToRoute("admin_omdb");
            }

            $result = $result->data->Search;
        }

        return [
            'form' => $form->createView(),
            'result' => $result
        ];
    }

    /**
     * @Route("/omdb/{id}", name="admin_omdb_create")
     * @param Request $request
     * @param $id
     * @return RedirectResponse
     */
    public function omdbCreateShowAction(Request $request, $id)
    {
        /** @var OMDBService $omdb */
        //$omdb = $this->get("app.omdb");

        $result = $this->createTVShow($id);

        if ($result["code"] != 0) {
            $request->getSession()->getFlashBag()->add('danger', $result["content"]);
            return $this->redirectToRoute("admin_omdb");
        }

        /** @var TVShow $show */
        $show = $result["content"];
        return $this->redirectToRoute("show", array("id" => $show->getId()));
    }


    /**
     * @param $omdbID
     * @return array
     */
    public function createTVShow($omdbID)
    {
        $omdb = new OMDbAPI();
        $details = $omdb->fetch('i', $omdbID);

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

        dump($details);

        $show = new TVShow();
        $show->setSynopsis($details->Plot);
        $show->setName($details->Title);
        $show->setImage($details->Poster); //FIXME Il faut télécharger l'image

        $this->getDoctrine()->getEntityManager()->persist($show);
        $this->getDoctrine()->getEntityManager()->flush();

        $this->createSeasons($show, $omdbID);

        $status["code"] = 0;
        $status["content"] = $show;

        return $status;
    }

    public function createSeasons(TVShow $show, $omdbID)
    {
        $omdb = new OMDbAPI();
        $details = $omdb->fetch('i', $omdbID, ["season" => 1]);

        dump($details);

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

            $retour = $this->createEpisodes($season, $i, $omdbID);
            if ($retour["code"] != 0) {
                $status["code"] = 3;
                $status["content"] = "Erreur lors de la création des épisodes.";
                return $status;
            }

            $show->addSeason($season);
            $this->getDoctrine()->getEntityManager()->persist($season);
            $this->getDoctrine()->getEntityManager()->persist($show);

        }

        $this->getDoctrine()->getEntityManager()->flush();

        $status["code"] = 0;
        $status["content"] = "Ok";

        return $status;
    }

    public function createEpisodes(Season $season, $seasonNumber, $omdbID)
    {
        $omdb = new OMDbAPI();
        $details = $omdb->fetch('i', $omdbID, ["season" => $seasonNumber]);

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
            $date = new \DateTime($episodeInfo->Released);
            $episode->setDate($date);
            $season->addEpisode($episode);
            $this->getDoctrine()->getEntityManager()->persist($episode);
        }

        $this->getDoctrine()->getEntityManager()->persist($season);
        $this->getDoctrine()->getEntityManager()->flush();

        return ["code" => 0, "content" => 'Ok'];
    }

}
