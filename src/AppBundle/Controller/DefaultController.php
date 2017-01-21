<?php

namespace AppBundle\Controller;

use AppBundle\Repository\EpisodeRepository;
use Doctrine\ORM\EntityManager;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Template;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     * @Template()
     */
    public function indexAction()
    {
        return [];
    }

    /**
     * @Route("/shows/{page}", name="shows", defaults={"page":"1"})
     * @Template()
     */
    public function showsAction($page)
    {
        /** @var EntityManager $em */
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:TVShow');

        $shows = $repo->getTVShow($page, $this->getParameter("tvShowPerPage"));

        return [
            'shows' => $shows,
            "page" => $page,
            "count" => intval(count($shows) / $this->getParameter("tvShowPerPage"))
        ];
    }

    /**
     * @Route("/show/{id}", name="show")
     * @Template()
     */
    public function showAction($id)
    {
        /** @var EntityManager $em */
        $em = $this->get('doctrine')->getManager();
        $repo = $em->getRepository('AppBundle:TVShow');

        return [
            'show' => $repo->find($id)
        ];
    }

    /**
     * @Route("/calendar", name="calendar")
     * @Template()
     */
    public function calendarAction()
    {

        $em = $this->getDoctrine();

        /** @var EpisodeRepository $repo */
        $repo = $em->getRepository("AppBundle:Episode");
        $episodes = $repo->findNextCalendar();

        return ["episodes" => $episodes];
    }

    /**
     * @Route("/login", name="login")
     * @Template()
     */
    public function loginAction()
    {
        return [];
    }

    /**
     * @Route("/{url}", name="remove_trailing_slash",
     *     requirements={"url" = ".*\/$"}, methods={"GET"})
     */
    public function removeTrailingSlashAction(Request $request)
    {
        $pathInfo = $request->getPathInfo();
        $requestUri = $request->getRequestUri();

        $url = str_replace($pathInfo, rtrim($pathInfo, ' /'), $requestUri);

        return $this->redirect($url, 301);
    }
}
