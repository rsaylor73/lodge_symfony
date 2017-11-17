<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class CommissionController extends Controller
{

    /**
     * @Route("/overridecommission", name="overridecommission")
     */
    public function overridecommissionAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('overridecommission');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

    	$em = $this->getDoctrine()->getManager();
    	$reservationID = $request->request->get('reservationID');

    	$sql = "SELECT `manual_commission_override` FROM `reservations` WHERE `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $commission = "0";
        while ($row = $result->fetch()) {  
        	$commission = $row['manual_commission_override'];
        }

        return $this->render('commission/manualcommissionoverride.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '3',
            'commission' => $commission,
        ]);           	
    }

    /**
     * @Route("/updateoverridecommission", name="updateoverridecommission")
     */
    public function updateoverridecommissionAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('overridecommission');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
    	$em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');  
        $manual_commission_override = $request->request->get('manual_commission_override');  

        $sql = "
        UPDATE `reservations` SET 
        `manual_commission_override` = '$manual_commission_override'
        WHERE `reservationID` = '$reservationID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $text = "The commission override was updated.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);

    }

}