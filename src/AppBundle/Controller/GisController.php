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

    /**
     * @Route("/gishome", name="gishome")
     */
    public function gishomeAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
        }

        $sql = "
        SELECT
            `g`.`id`,
            `g`.`gis_guest_info` AS 'General',
            `g`.`gis_waiver` AS 'Waiver',
            `g`.`gis_policy` AS 'Policy',
            `g`.`gis_emergency_contact` AS 'Emergency Contact',
            `g`.`gis_requests` AS 'Requests',
            `g`.`gis_trip_insurance` AS 'Insurance',
            `g`.`gis_travel_info` AS 'Travel',
            `g`.`gis_confirmation` AS 'Confirmation'
        FROM
            `gis_action` g

        WHERE
            `g`.`contactID` = '$contactID'
            AND `g`.`reservationID` = '$reservationID'
            AND `g`.`inventoryID` = '$inventoryID'
        ";
        $i = "0";
        $data = "";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        }        

        return $this->render('gis/gishome.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,
            'data' => $data,
        ]);

    }

    /**
     * @Route("/gisGeneral", name="gisGeneral")
     */
    public function gisGeneralAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');
        $title = "";
        $occupation = "";
        $phone1_type = "";
        $phone1 = "";
        $phone2_type = "";
        $phone2 = "";
        $phone3_type = "";
        $phone3 = "";                
        $phone4_type = "";
        $phone4 = "";
        $preferred_name = "";
        $donottext = "";
        $gender = "";
        $dob = "";
        $address1 = "";
        $address2 = "";
        $city = "";
        $province = "";
        $state = "";
        $zip = "";
        $country = "";
        $nationality_countryID = "";
        $passport_number = "";
        $passport_exp = "";


        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`,
            `c`.`title`,
            `c`.`occupation`,
            `c`.`phone1_type`,
            `c`.`phone2_type`,
            `c`.`phone3_type`,
            `c`.`phone4_type`,
            `c`.`phone1`,
            `c`.`phone2`,
            `c`.`phone3`,
            `c`.`phone4`,
            `c`.`preferred_name`,
            `c`.`donottext`,
            `c`.`sex` AS 'gender',
            `c`.`date_of_birth` AS 'dob',
            `c`.`address1`,
            `c`.`address2`,
            `c`.`city`,
            `c`.`state`,
            `c`.`province`,
            `c`.`zip`,
            `cn`.`country`,
            `cn2`.`country` AS 'nationality_countryID',
            `c`.`passport_number`,
            DATE_FORMAT(`c`.`passport_exp`, '%m/%d/%Y') AS 'passport_exp'


        FROM
            `$AF_DB`.`contacts` c

        LEFT JOIN `$AF_DB`.`countries` cn ON `c`.`countryID` = `cn`.`countryID`
        LEFT JOIN `$AF_DB`.`countries` cn2 ON `c`.`nationality_countryID` = `cn2`.`countryID`

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
            $title = $row['title'];
            $occupation = $row['occupation'];
            $phone1_type = $row['phone1_type'];
            $phone2_type = $row['phone2_type'];
            $phone3_type = $row['phone3_type'];
            $phone4_type = $row['phone4_type'];
            $phone1 = $row['phone1'];
            $phone2 = $row['phone2'];
            $phone3 = $row['phone3'];
            $phone4 = $row['phone4'];
            $preferred_name = $row['preferred_name'];
            $donottext = $row['donottext'];
            $gender = $row['gender'];
            $dob = $row['dob'];
            $address1 = $row['address1'];
            $address2 = $row['address2'];
            $city = $row['city'];
            $state = $row['state'];
            $province = $row['province'];
            $zip = $row['zip'];
            $country = $row['country'];
            $nationality_countryID = $row['nationality_countryID'];
            $passport_number = $row['passport_number'];
            $passport_exp = $row['passport_exp'];
        }

        return $this->render('gis/gisgeneral.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,            
            'title' => $title,
            'occupation' => $occupation,
            'phone1_type' => $phone1_type,
            'phone1' => $phone1,
            'phone2_type' => $phone2_type,
            'phone2' => $phone2,
            'phone3_type' => $phone3_type,
            'phone3' => $phone3,
            'phone4_type' => $phone4_type,
            'phone4' => $phone4,
            'preferred_name' => $preferred_name,
            'donottext' => $donottext,
            'gender' => $gender,
            'dob' => $dob,
            'address1' => $address1,
            'address2' => $address2,
            'city' => $city,
            'province' => $province,
            'state' => $state,
            'zip' => $zip,
            'country' => $country,
            'nationality_countryID' => $nationality_countryID,
            'passport_number' => $passport_number,
            'passport_exp' => $passport_exp,
        ]);        

    }

    /**
     * @Route("/gisWaiver", name="gisWaiver")
     */
    public function gisWaiverAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $gisurl = $this->container->getParameter('gisurl');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
        }

        $gis_waiver = "";
        $sql = "
        SELECT `gis_waiver` FROM `gis_action` WHERE `contactID` = '$contactID' 
        AND `reservationID` = '$reservationID' AND `inventoryID` = '$inventoryID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $gis_waiver = $row['gis_waiver'];
        }

        return $this->render('gis/giswaiver.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,
            'gis_waiver' => $gis_waiver,
            'gisurl' => $gisurl,            
        ]);        

    }

    /**
     * @Route("/gisPolicy", name="gisPolicy")
     */
    public function gisPolicyAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');
        $data = "";

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
        }

        return $this->render('gis/gispolicy.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,            
            'data' => $data,
        ]);        

    }

    /**
     * @Route("/gisEmergencyContact", name="gisEmergencyContact")
     */
    public function gisEmergencyContactAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`,
            `c`.`emergency_first` AS 'firstA',
            `c`.`emergency_last` AS 'lastA',
            `c`.`emergency_address1` AS 'address1A',
            `c`.`emergency_address2` AS 'address2A',
            `c`.`emergency_relationship` AS 'relationshipA',
            `c`.`emergency_ph_home` AS 'phone_homeA',
            `c`.`emergency_ph_work` AS 'phone_workA',
            `c`.`emergency_ph_mobile` AS 'phone_mobileA',
            `c`.`emergency_city` AS 'cityA',            
            `c`.`emergency_state` AS 'stateA',
            `c`.`emergency_zip` AS 'zipA',
            `c`.`emergency_email` AS 'emailA',
            `cn`.`country` AS 'countryA',
            `c`.`emergency2_first` AS 'firstB',
            `c`.`emergency2_last` AS 'lastB',
            `c`.`emergency2_address1` AS 'address1B',
            `c`.`emergency2_address2` AS 'address2B',
            `c`.`emergency2_relationship` AS 'relationshipB',
            `c`.`emergency2_ph_home` AS 'phone_homeB',
            `c`.`emergency2_ph_work` AS 'phone_workB',
            `c`.`emergency2_ph_mobile` AS 'phone_mobileB',
            `c`.`emergency2_city` AS 'cityB',
            `c`.`emergency2_state` AS 'stateB',
            `c`.`emergency2_zip` AS 'zipB',
            `c`.`emergency2_email` AS 'emailB',
            `cn2`.`country` AS 'countryB'

        FROM
            `$AF_DB`.`contacts` c

        LEFT JOIN `$AF_DB`.`countries` cn ON `c`.`emergency_countryID` = `cn`.`countryID`
        LEFT JOIN `$AF_DB`.`countries` cn2 ON `c`.`emergency2_countryID` = `cn2`.`countryID`

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];

            $firstA = $row['firstA'];
            $lastA = $row['lastA'];
            $address1A = $row['address1A'];
            $address2A = $row['address2A'];
            $relationshipA = $row['relationshipA'];
            $phone_homeA = $row['phone_homeA'];
            $phone_workA = $row['phone_workA'];
            $phone_mobileA = $row['phone_mobileA'];
            $cityA = $row['cityA'];
            $stateA = $row['stateA'];
            $zipA = $row['zipA'];
            $emailA = $row['emailA'];
            $countryA = $row['countryA'];

            $firstB = $row['firstB'];
            $lastB = $row['lastB'];
            $address1B = $row['address1B'];
            $address2B = $row['address2B'];
            $relationshipB = $row['relationshipB'];
            $phone_homeB = $row['phone_homeB'];
            $phone_workB = $row['phone_workB'];
            $phone_mobileB = $row['phone_mobileB'];
            $cityB = $row['cityB'];
            $stateB = $row['stateB'];
            $zipB = $row['zipB'];
            $emailB = $row['emailB'];
            $countryB = $row['countryB'];

        }

        return $this->render('gis/gisemergencycontact.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,  

            'firstA' => $firstA,
            'lastA' => $lastA,
            'address1A' => $address1A,
            'address2A' => $address2A,
            'relationshipA' => $relationshipA,
            'phone_homeA' => $phone_homeA,
            'phone_workA' => $phone_workA,
            'phone_mobileA' => $phone_mobileA,
            'cityA' => $cityA,
            'stateA' => $stateA,
            'zipA' => $zipA,
            'emailA' => $emailA,
            'countryA' => $countryA,

            'firstB' => $firstB,
            'lastB' => $lastB,
            'address1B' => $address1B,
            'address2B' => $address2B,
            'relationshipB' => $relationshipB,
            'phone_homeB' => $phone_homeB,
            'phone_workB' => $phone_workB,
            'phone_mobileB' => $phone_mobileB,
            'cityB' => $cityB,
            'stateB' => $stateB,
            'zipB' => $zipB,
            'emailB' => $emailB,
            'countryB' => $countryB,
        ]);        

    }

    /**
     * @Route("/gisRequests", name="gisRequests")
     */
    public function gisRequestsAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`,
            `c`.`special_passenger_details`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
            $special_passenger_details = $row['special_passenger_details'];
        }

        return $this->render('gis/gisrequests.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,  
            'special_passenger_details' => $special_passenger_details,          
        ]);        

    }

    /**
     * @Route("/gisInsurance", name="gisInsurance")
     */
    public function gisInsuranceAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
        }

        $sql = "
        SELECT
            `g`.`insurance`,
            `g`.`trip_company`,
            `g`.`trip_policy`,
            `g`.`date_issued`
        FROM
            `gis` g

        WHERE
            `g`.`reservationID` = '$reservationID'
            AND `g`.`inventoryID` = '$inventoryID'
            AND `g`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $insurance = "";
        $trip_company = "";
        $trip_policy = "";
        $date_issued = "";

        while ($row = $result->fetch()) {
            $insurance = $row['insurance'];
            $trip_company = $row['trip_company'];
            $trip_policy = $row['trip_policy'];
            $date_issued = $row['date_issued'];
        }
        return $this->render('gis/gisinsurance.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email, 
            'insurance' => $insurance,
            'trip_company' => $trip_company,
            'trip_policy' => $trip_policy,
            'date_issued' => $date_issued,           
        ]);        

    }

    /**
     * @Route("/gisTravel", name="gisTravel")
     */
    public function gisTravelAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');
        $data = "";

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
        }

        $sql = "
        SELECT
            `g`.`arrival_airport1`,
            `g`.`arrival_airport2`,
            `g`.`arrival_airport3`,
            `g`.`arrival_airport4`,
            `g`.`arrival_airport5`,
            `g`.`arrival_flight1`,
            `g`.`arrival_flight2`,
            `g`.`arrival_flight3`,
            `g`.`arrival_flight4`,
            `g`.`arrival_flight5`,
            `g`.`arrival_date_time1`,
            `g`.`arrival_date_time2`,
            `g`.`arrival_date_time3`,
            `g`.`arrival_date_time4`,
            `g`.`arrival_date_time5`,
            `g`.`departure_airport1`,
            `g`.`departure_airport2`,
            `g`.`departure_airport3`,
            `g`.`departure_airport4`,
            `g`.`departure_airport5`,
            `g`.`departure_flight1`,
            `g`.`departure_flight2`,
            `g`.`departure_flight3`,
            `g`.`departure_flight4`,
            `g`.`departure_flight5`,
            `g`.`departure_date_time1`,
            `g`.`departure_date_time2`,
            `g`.`departure_date_time3`,
            `g`.`departure_date_time4`,
            `g`.`departure_date_time5`,
            `g`.`hotel_arrival`,
            `g`.`hotel_departure`

        FROM
            `gis_travel_info` g

        WHERE
            `g`.`contactID` = '$contactID'
            AND `g`.`reservationID` = '$reservationID'
            AND `g`.`inventoryID` = '$inventoryID'

        LIMIT 1
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $data[$key] = $value;
            }
        }
        return $this->render('gis/gistravel.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,            
            'data' => $data,
        ]);        

    }

    /**
     * @Route("/gisConfirmation", name="gisConfirmation")
     */
    public function gisConfirmationAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->query->get('reservationID');
        $bunk = $request->query->get('bunk');
        $roomID = $request->query->get('roomID');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');
        $data = "";

        $sql = "
        SELECT
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`

        FROM
            `$AF_DB`.`contacts` c

        WHERE
            `c`.`contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $first = ""; $middle = ""; $last = ""; $email = "";
        while ($row = $result->fetch()) {
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $email = $row['email'];
        }

        return $this->render('gis/gisconfirmation.html.twig',[
            'reservationID' => $reservationID,
            'inventoryID' => $inventoryID,
            'bunk' => $bunk,
            'roomID' => $roomID,
            'tab' => '2',
            'contactID' => $contactID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'email' => $email,            
            'data' => $data,
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