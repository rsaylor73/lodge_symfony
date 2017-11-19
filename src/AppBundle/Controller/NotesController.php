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

        $details = $this->get('reservationdetails')->getresdetails($reservationID);

        $sql = "SELECT `noteID`,`note`,DATE_FORMAT(`date_added`,'%m/%d/%Y') AS 'date' FROM `reservation_notes` WHERE `reservationID` = '$reservationID' ORDER BY `date_added` DESC";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $notes = "";
        while ($row = $result->fetch()) {   
            foreach ($row as $key=>$value) {
                $notes[$i][$key] = $value;
            }
            $i++;
        }     

        return $this->render('reservations/viewreservationnotes.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '4',
            'notes' => $notes,
            'details' => $details,
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
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');
        $note = $request->request->get('note');
        $date = date("Ymd");

        $userID = "";
        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();
        $sql = "SELECT `id` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        while ($row = $result->fetch()) {
            $userID = $row['id'];
        }        

        $sql = "INSERT INTO `reservation_notes`
        (`reservationID`,`userID`,`date_added`,`date_updated`,`note`)
        VALUES
        ('$reservationID','$userID','$date','$date',?)
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $note);
        $result->execute(); 
        $noteID = $em->getConnection()->lastInsertId();
        if ($noteID == "") {
            $text = "The note failed to add.";
            $status = "danger";         
        } else {
            $text = "The note was added.";
            $status = "success";
        }

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationnotes',[
            'reservationID' => $reservationID,
        ]); 
    }       

    /**
     * @Route("/editreservationnote", name="editreservationnote")
     */
    public function editreservationnoteAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');
        $noteID = $request->request->get('noteID');

        $note = "";
        $sql = "SELECT `note` FROM `reservation_notes` WHERE `noteID` = '$noteID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        while ($row = $result->fetch()) {
            $note = $row['note'];
        }        

        return $this->render('notes/editnote.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '4',
            'note' => $note,
            'noteID' => $noteID,
        ]);

    }

    /**
     * @Route("/updatereservationnote", name="updatereservationnote")
     */
    public function updatereservationnoteAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');
        $note = $request->request->get('note');
        $noteID = $request->request->get('noteID');
        $date = date("Ymd");      

        $sql = "UPDATE `reservation_notes` SET
        `date_updated` = '$date',
        `note` = ?
        WHERE `noteID` = '$noteID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $note);
        $result->execute(); 
        $noteID = $em->getConnection()->lastInsertId();

        $text = "The note was updated.";
        $status = "success";

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationnotes',[
            'reservationID' => $reservationID,
        ]); 
    }       

}