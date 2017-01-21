<?php

namespace AppBundle\Controller;

use aharen\OMDbAPI;
use AppBundle\Entity\Episode;
use AppBundle\Entity\Season;
use AppBundle\Entity\TVShow;
use AppBundle\Forms\EpisodeType;
use AppBundle\Forms\ShowType;
use AppBundle\Services\OMDBService;
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
        $omdbService = $this->get("app.omdb");

        $result = $omdbService->createTVShow($id);

        if ($result["code"] != 0) {
            $request->getSession()->getFlashBag()->add('danger', $result["content"]);
            return $this->redirectToRoute("admin_omdb");
        }

        /** @var TVShow $show */
        $show = $result["content"];
        return $this->redirectToRoute("show", array("id" => $show->getId()));
    }



}
