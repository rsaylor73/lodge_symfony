<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class LodgeController extends Controller
{

    /**
     * @Route("/checkinreport", name="checkinreport") 
     */
    public function checkinreportAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('lodgereports');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

    	$em = $this->getDoctrine()->getManager();
    	$AF_DB = $this->container->getParameter('AF_DB');

    	$nice_date = date("d M Y");

        return $this->render('lodge/checkinreport.html.twig',[
        	'date' => $nice_date,
        ]); 
    }

    /**
     * @Route("/checkoutreport", name="checkoutreport") 
     */
    public function checkoutreportAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('lodgereports');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

    	$em = $this->getDoctrine()->getManager();
    	$AF_DB = $this->container->getParameter('AF_DB');

    	$nice_date = date("d M Y");

        return $this->render('lodge/checkoutreport.html.twig',[
        	'date' => $nice_date,
        ]); 
    }

}