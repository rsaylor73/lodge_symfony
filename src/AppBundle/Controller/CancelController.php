<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class CancelController extends Controller
{

    /**
     * @Route("/viewreservationcancel", name="viewreservationcancel")
     * @Route("/viewreservationcancel/{reservationID}")
     */
    public function viewreservationcancelAction(Request $request,$reservationID='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

        $em = $this->getDoctrine()->getManager();
        $details = $this->get('reservationdetails')->getresdetails($reservationID);

        return $this->render('reservations/viewreservationcancel.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '5',
            'details' => $details,
        ]);
    }

    /**
     * @Route("/cancelreservation", name="cancelreservation")
     */
    public function cancelreservationAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');

        $text = "The reservation was cancelled.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationcancel',[
            'reservationID' => $reservationID,
        ]);
    }


}