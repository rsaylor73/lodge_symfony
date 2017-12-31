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

    /**
     * @Route("/gisstatus", name="gisstatus")
     */
    public function gisstatusAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->query->get('reservationID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');
        $general = $request->query->get('General');
        $waiver = $request->query->get('Waiver');
        $policy = $request->query->get('Policy');
        $emergency = $request->query->get('EmergencyContact');
        $requests = $request->query->get('Requests');
        $insurance = $request->query->get('Insurance');
        $travel = $request->query->get('Travel');
        $confirmation = $request->query->get('Confirmation');

        $sql = "UPDATE `gis_action` SET
        `gis_guest_info` = '$general',
        `gis_waiver` = '$waiver',
        `gis_policy` = '$policy',
        `gis_emergency_contact` = '$emergency',
        `gis_requests` = '$requests',
        `gis_trip_insurance` = '$insurance',
        `gis_travel_info` = '$travel',
        `gis_confirmation` = '$confirmation'
        WHERE `contactID` = '$contactID'
        AND `reservationID` = '$reservationID'
        AND `inventoryID` = '$inventoryID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        return $this->render('ajax/gisstatus.html.twig');

    }

}