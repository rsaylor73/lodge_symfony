<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class NotesController extends Controller
{

    /**
     * @Route("/viewreservationnotes", name="viewreservationnotes")
     * @Route("/viewreservationnotes/{reservationID}")
     */
    public function viewreservationnotesAction(Request $request,$reservationID='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();

        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

        return $this->render('reservations/viewreservationnotes.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '4',
        ]);
    }

    /**
     * @Route("/newreservationnote", name="newreservationnote")
     */
    public function newreservationnoteAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');

        return $this->render('notes/newnote.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '4',
        ]);
    }

    /**
     * @Route("/savereservationnote", name="savereservationnote")
     */
    public function savereservationnoteAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $reservationID = $request->request->get('reservationID');

        $text = "Test.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationnotes',[
            'reservationID' => $reservationID,
        ]); 
    }       

}