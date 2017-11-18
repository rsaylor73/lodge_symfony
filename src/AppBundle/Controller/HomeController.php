<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class HomeController extends Controller
{
    /**
     * @Route("/", name="index")
     */
    public function indexAction()
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('dashboard');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        return $this->render('default/index.html.twig');
    }

    /**
     * @Route("/accessdenied", name="accessdenied")
     */
    public function accessdeniedAction(Request $request)
    {
    	$section=$request->query->get('section');
        return $this->render('default/accessdenied.html.twig',[
        	'section' => $section,
        ]);
    }

    /**
     * @Route("/inactive", name="inactive")
     */
    public function inactiveAction(Request $request)
    {
        $section=$request->query->get('section');
        return $this->render('default/inactive.html.twig');
    }    

}
