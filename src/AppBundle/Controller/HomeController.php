<?php

namespace AppBundle\Controller;

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

}
