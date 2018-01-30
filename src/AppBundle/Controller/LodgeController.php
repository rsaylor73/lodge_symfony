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
        $singlesupplement = $this->container->getParameter('singlesupplement');

        $date = $request->request->get('date');
        $date2 = $request->query->get('date2');
        $format = $request->query->get('format');
        if ($date2 != "") {
            $date = $date2;
        }
        if ($date != "") {
            $nice_date = date("d M Y", strtotime($date));
            $today = date("Y-m-d", strtotime($date));
        } else {
            $date = date("Y-m-d");
    	    $nice_date = date("d M Y");
            $today = date("Y-m-d");
        }

        // edit the service to change the query as this is
        // used in more then one location.
        $sql = $this->get('commonservices')->checkin_report_query($date);

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $data = "";
        while ($row = $result->fetch()) {   
            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        } 

        $render = 'lodge/checkinreport.html.twig';
        if ($format == 'print') {
            $render = 'lodge/checkinreport_email.html.twig';
        }

        return $this->render($render,[
        	'date' => $nice_date,
            'data' => $data,
            'date2' => $date,
            'format' => 'web',
            'singlesupplement' => $singlesupplement,
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

        $sql = $this->get('commonservices')->checkout_report_query();

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $data = "";
        while ($row = $result->fetch()) {   
            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        } 

        return $this->render('lodge/checkoutreport.html.twig',[
        	'date' => $nice_date,
            'data' => $data,
            'format' => 'web',
        ]); 
    }

}