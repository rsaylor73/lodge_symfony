<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class AjaxController extends Controller
{

    /**
     * @Route("/showcheckoutdate", name="showcheckoutdate")
     */
    public function showcheckoutdateAction(Request $request)
    {
    	$start_date = $request->query->get('start_date');
    	$nights = $request->query->get('nights');

    	$checkout = date("Y-m-d", strtotime($start_date . "+ $nights DAY"));

		return $this->render('ajax/showcheckoutdate.html.twig',[
			'checkout' => $checkout,
		]);

    }


}