<?php

namespace AppBundle\Controller;

# you have to lead the entity for the DB you will be working from
use AppBundle\Entity\User;

use AppBundle\Form\Type\UserType;
use AppBundle\Form\Type\ForgotPasswordType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;


class GisController extends Controller
{

    /**
     * @Route("/newgis", name="newgis")
     */
    public function newgisAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');

		$reservationID = $request->request->get('reservationID');
		$inventoryID = $request->request->get('inventoryID');
		$guest_contactID = $request->request->get('guest_contactID');

		// init
		$guest_first = ""; $guest_middle = ""; $guest_last = ""; $guest_email = "";

		if ($guest_contactID != "") {
			$sql = "SELECT `first`,`middle`,`last`,`email` FROM `$AF_DB`.`contacts` WHERE `contactID` = '$guest_contactID'";
	        $result = $em->getConnection()->prepare($sql);
	        $result->execute();
	        while ($row = $result->fetch()) {
	        	$guest_first = $row['first'];
	        	$guest_middle = $row['middle'];
	        	$guest_last = $row['last'];
	        	$guest_email = $row['email'];
	        }			
		}

		$details = $this->get('reservationdetails')->getresdetails($reservationID);
		$contactID = $details['contactID'];
		$first = $details['first'];
		$middle = $details['middle'];
		$last = $details['last'];
		$email = $details['email'];

		if (($guest_contactID == "") or ($contactID == "")) {
	        $this->addFlash('danger','You do not have a guest assigned and or a primary contact assigned.');
	        return $this->redirectToRoute('viewreservationguest',[
	            'reservationID' => $reservationID,
	        ]); 			
		}

        return $this->render('gis/newgis.html.twig',[
        	'reservationID' => $reservationID,
        	'inventoryID' => $inventoryID,
        	'tab' => '2',
        	'contactID' => $contactID,
        	'first' => $first,
        	'middle' => $middle,
        	'last' => $last,
        	'email' => $email,
        	'guest_contactID' => $guest_contactID,
        	'guest_first' => $guest_first,
        	'guest_middle' => $guest_middle,
        	'guest_last' => $guest_last,
        	'guest_email' => $guest_email,
        ]);

    }

    /**
     * @Route("/sendgis", name="sendgis")
     */
    public function sendgisAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');
		$site_email = $this->container->getParameter('site_email');
		$gisurl = $this->container->getParameter('gisurl');

		$gisPW = $this->randomPassword();
		$reservationID = $request->request->get('reservationID');
		$inventoryID = $request->request->get('inventoryID');
		$contactID = $request->request->get('contactID');
		$email = $request->request->get('email');

		// user info
        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();
        $userID = "";
        $sql = "SELECT `id` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $userID = $row['id'];
        } 		

		// init
		$first = ""; $last = "";

		//get email
		$sql = "SELECT `first`,`last` FROM `$AF_DB`.`contacts` WHERE `contactID` = '$contactID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {	
        	$first = $row['first'];
        	$last = $row['last'];
        }

        // update or insert GIS
        $gisID = "";
        $timestamp = date("U");
        $sql = "SELECT `gisID` FROM `gis` WHERE `reservationID` = '$reservationID' AND `inventoryID` = '$inventoryID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {	
        	$gisID = $row['gisID'];
        }        
        if ($gisID == "") {
        	// new
        	$sql = "INSERT INTO `gis` 
        	(
        		`reservationID`,`inventoryID`,`contactID`,`timestamp`,`gisPW`,`userID`
        	) VALUES (
        		'$reservationID','$inventoryID','$contactID','$timestamp','$gisPW','$userID'
        	)
        	";

        } else {
        	// update
        	$sql = "UPDATE `gis` SET `gisPW` = '$gisPW', `contactID` = '$contactID', `userID` = '$userID' 
        	WHERE `gisID` = '$gisID'";
        }
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        // GIS Log
        $date = date("Ymd");
        $time = date("H:i:s");
        $sql = "INSERT INTO `gis_log` 
        (
            `userID`,`date`,`reservationID`,`contactID`,`time`
        ) VALUES (
            '$userID','$date','$reservationID','$contactID','$time'
        )
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        
        // GIS URL
        $url = $gisurl . "/" . $reservationID . "/" . $contactID . "/" . $inventoryID . "/" . $gisPW;

        // send email
        $title = "Your Aggressor Safari Online Guest Information System :: Conf #$reservationID";
        $message = (new \Swift_Message($title))
            ->setFrom($site_email)
            ->setTo($email)
            ->setSubject($title)
            ->setBody(
                $this->renderView(
                    'Emails/gis.html.twig',
                    array(
                        'first' => $first,
                        'last' => $last,
                        'url' => $url
                    )
                ),
                'text/html'
            )
        ;
        $this->get('mailer')->send($message);
        $this->addFlash('success','The GIS link was sent to ' . $email);
        return $this->redirectToRoute('viewreservationguest',[
            'reservationID' => $reservationID,
        ]); 
	}

    private function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    } 	
}