<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class ReservationController extends Controller
{
    /**
     * @Route("/newreservation", name="newreservation")
     */
    public function newreservationAction()
    {
    	$em = $this->getDoctrine()->getManager();

    	// get lodge list
    	$sql = "SELECT `id`,`name` FROM `locations` WHERE `active` = 'Yes' ORDER BY `name` ASC";
		$result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $lodge = "";
        while ($row = $result->fetch()) {
        	foreach($row as $key=>$value) {
        		$lodge[$i][$key] = $value;
        	}
        	$i++;
        }

        // get room types
        $sql = "SELECT `id`,`type` FROM `roomtype` ORDER BY `type` ASC";
		$result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $type = "";
        while ($row = $result->fetch()) {
        	foreach($row as $key=>$value) {
        		$type[$i][$key] = $value;
        	}
        	$i++;
        }

        return $this->render('reservations/newreservation.html.twig',[
        	'lodge' => $lodge,
        	'type' => $type,
        ]);

    }


    /**
     * @Route("/selecttens", name="selecttens")
     */
    public function selecttensAction(Request $request)
    {
    	$em = $this->getDoctrine()->getManager();

		$lodge = $request->request->get('lodge');
		$pax = $request->request->get('pax');
		$children = $request->request->get('children');
		$childage1 = $request->request->get('childage1');
		$childage2 = $request->request->get('childage2');
		$nights = $request->request->get('nights');
        $type = $request->request->get('type');
		$start_date = $request->request->get('start_date');
        $nights2 = $nights - 1; // offset as we won't book the day checking out.
        $start = date("Ymd", strtotime($start_date));
        $end = date("Ymd", strtotime($start_date . "+$nights2 day"));

        $start_formatted = date("m/d/Y", strtotime($start_date));
        $end_formatted = date("m/d/Y", strtotime($start_date . "+$nights day"));

        $type_sql = "";
        if ($type != "") {
            $type_sql = "AND `i`.`typeID` = '$type'";
        }

        $total_pax = $pax * $nights;
        $total_child = $children * $nights;

        // check availability
        $sql = "
        SELECT
            COUNT(CASE WHEN `i`.`status` = 'avail' AND `i`.`type` = 'adult' THEN `i`.`status` END) AS 'adult',
            COUNT(CASE WHEN `i`.`status` = 'avail' AND `i`.`type` = 'child' THEN `i`.`status` END) AS 'child'

        FROM 
            `inventory` i


        WHERE
            1 
            AND `i`.`locationID` = '$lodge' 
            AND `i`.`date_code` BETWEEN '$start' AND '$end'
            $type_sql

        HAVING 
            adult >= '$total_pax' AND child >= '$total_child'          
        ";  

        $found = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {

            $sql2 = "
            SELECT
                `i`.`type`,
                `i`.`roomID`,
                `i`.`typeID`,
                `i`.`nightly_rate`,
                `i`.`bed`,
                `r`.`description`,
                `r`.`writeup`,
                `t`.`type`

            FROM
                `inventory` i, `rooms` r, `roomtype` t

            WHERE
                1
                AND `i`.`date_code` BETWEEN '$start' AND '$end'
                AND `i`.`status` = 'avail'
                AND `i`.`locationID` = '$lodge'
                AND `i`.`roomID` = `r`.`id`
                AND `i`.`typeID` = `t`.`id`
                $type_sql  

            GROUP BY `i`.`type`, `i`.`roomID`, `i`.`nightly_rate`, `i`.`bed`, `r`.`description`,
            `r`.`writeup`,  `t`.`type` 

            ORDER BY `r`.`description` ASC    
            ";	
            $inventory = "";
            $i = "0";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();
            while ($row2 = $result2->fetch()) {
                foreach ($row2 as $key=>$value) {
                    $inventory[$i][$key] = $value;
                }
                $i++;
            }            
            $found = "1";	
        }

        if ($found == "0") {
            $this->addFlash('danger','The requested dates are not available.');
            return $this->redirectToRoute('newreservation');
        }

        return $this->render('reservations/selecttens.html.twig',[
            'lodge' => $lodge,
            'pax' => $pax,
            'children' => $children,
            'childage1' => $childage1,
            'childage2' => $childage2,
            'nights' => $nights,
            'type' => $type,
            'start_date' => $start_date,
            'start_formatted' => $start_formatted,
            'end_formatted' => $end_formatted,
            'inventory' => $inventory,

        ]);

    }

    /**
     * @Route("/createreservation", name="createreservation")
     */
    public function createreservationAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();
        $userID = "";
        $sql = "SELECT `id` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $userID = $row['id'];
        }        

        $lodge = $request->request->get('lodge');
        $pax = $request->request->get('pax');
        $children = $request->request->get('children');
        $childage1 = $request->request->get('childage1');
        $childage2 = $request->request->get('childage2');
        $nights = $request->request->get('nights');
        $type = $request->request->get('type');
        $start_date = $request->request->get('start_date');

        $sql = "INSERT INTO `reservations` 
        (`userID`,`date_booked`,`status`,`pax`,`children`,`child1_age`,`child2_age`,`checkin_date`,`nights`)
        VALUES 
        ('$userID',NOW(),'Active','$pax','$children','$childage1','$childage2','$start_date','$nights')";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $reservationID = $em->getConnection()->lastInsertId();

        $nights = $nights - 1; // offset as we won't book the day checking out.
        $start = date("Ymd", strtotime($start_date));
        $end = date("Ymd", strtotime($start_date . "+$nights day"));

        $start_formatted = date("m/d/Y", strtotime($start_date));
        $end_formatted = date("m/d/Y", strtotime($start_date . "+$nights day"));

        $type_sql = "";
        if ($type != "") {
            $type_sql = "AND `i`.`typeID` = '$type'";
        }

        $total_pax = $pax * $nights;
        $total_child = $children * $nights;


        // check availability
        $sql = "
        SELECT
            COUNT(CASE WHEN `i`.`status` = 'avail' AND `i`.`type` = 'adult' THEN `i`.`status` END) AS 'adult',
            COUNT(CASE WHEN `i`.`status` = 'avail' AND `i`.`type` = 'child' THEN `i`.`status` END) AS 'child'

        FROM 
            `inventory` i


        WHERE
            1 
            AND `i`.`locationID` = '$lodge' 
            AND `i`.`date_code` BETWEEN '$start' AND '$end'
            $type_sql

        HAVING 
            adult >= '$total_pax' AND child >= '$total_child'          
        ";  

        $found = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {

            $sql2 = "
            SELECT
                `i`.`inventoryID`,
                `i`.`type`,
                `i`.`roomID`,
                `i`.`typeID`,
                `i`.`nightly_rate`,
                `i`.`bed`,
                `r`.`description`,
                `r`.`writeup`,
                `t`.`type`

            FROM
                `inventory` i, `rooms` r, `roomtype` t

            WHERE
                1
                AND `i`.`date_code` BETWEEN '$start' AND '$end'
                AND `i`.`status` = 'avail'
                AND `i`.`locationID` = '$lodge'
                AND `i`.`roomID` = `r`.`id`
                AND `i`.`typeID` = `t`.`id`
                $type_sql  

            
            ORDER BY `r`.`description` ASC    
            ";  
            $test = "";
            $test2 = "";
            $inventory = "";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();
            while ($row2 = $result2->fetch()) {
                $test = "room";
                $test .= $row2['roomID'];
                $test .= "_";
                $test .= $row2['bed'];
                $test2 = $request->request->get($test);
                if ($test2 == "checked") {
                    $sql3 = "UPDATE `inventory` SET `status` = 'tentative', `reservationID` = '$reservationID' WHERE `inventoryID` = '$row2[inventoryID]'";
                    $result3 = $em->getConnection()->prepare($sql3);
                    $result3->execute();

                }

            }            
            $found = "1";   
        }

        if ($found == "0") {
            $this->addFlash('danger','The requested dates are no longer available.');
            return $this->redirectToRoute('newreservation');
        }

        return $this->render('reservations/createreservation.html.twig',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/locatereservation", name="locatereservation")
     */
    public function locatereservationAction(Request $request)
    {
        return $this->render('reservations/locatereservation.html.twig');
    }


    /**
     * @Route("/viewreservation", name="viewreservation")
     * @Route("/viewreservation/{reservationID}")
     */
    public function viewreservationAction(Request $request,$reservationID='')
    {
        $em = $this->getDoctrine()->getManager();

        $sql = "
        SELECT
            `r`.`nights`,
            DATE_FORMAT(`r`.`checkin_date`, '%m/%d/%Y') AS 'checkin_date',
            DATE_FORMAT(
                DATE_ADD(`r`.`checkin_date`, INTERVAL `r`.`nights` DAY),
                '%m/%d/%Y'
            ) AS 'checkout_date',
            `u`.`first_name`,
            `u`.`last_name`,
            `u`.`email`,
            DATE_FORMAT(`r`.`date_booked`,'%m/%d/%Y') AS 'date_booked',
            `r`.`status`,
            `r`.`pax`,
            `r`.`children`,
            `r`.`child1_age`,
            `r`.`child2_age`
        
        FROM
            `reservations` r, `user` u

        WHERE
            `r`.`reservationID` = '$reservationID'
            AND `r`.`userID` = `u`.`id`
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $nights = $row['nights'];
            $checkin_date = $row['checkin_date'];
            $checkout_date = $row['checkout_date'];
            $first_name = $row['first_name'];
            $last_name = $row['last_name'];
            $email = $row['email'];
            $date_booked = $row['date_booked'];
            $status = $row['status'];
            $pax = $row['pax'];
            $children = $row['children'];
            $child1_age = $row['child1_age'];
            $child2_age = $row['child2_age'];
        }

        return $this->render('reservations/viewreservation.html.twig',[
            'reservationID' => $reservationID,
            'nights' => $nights,
            'checkin_date' => $checkin_date,
            'checkout_date' => $checkout_date,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'date_booked' => $date_booked,
            'status' => $status,
            'pax' => $pax,
            'children' => $children,
            'child1_age' => $child1_age,
            'child2_age' => $child2_age,
        ]);
    }

    /**
     * @Route("/viewreservationguest", name="viewreservationguest")
     * @Route("/viewreservationguest/{reservationID}")
     */
    public function viewreservationguestAction(Request $request,$reservationID='')
    {
        $em = $this->getDoctrine()->getManager();

        $sql = "
        SELECT
            `r`.`description`,
            `i`.`bed`,
            `i`.`type` AS 'class',
            `i`.`status`,
            `t`.`type`

        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`
        LEFT JOIN `roomtype` t ON `i`.`typeID` = `t`.`id`

        WHERE
            `i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`description`, `i`.`bed`, `i`.`type`, `i`.`status`, `i`.`type`

        ORDER BY `r`.`description` ASC, `i`.`bed` ASC
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

        return $this->render('reservations/viewreservationguest.html.twig',[
            'reservationID' => $reservationID,
	    'data' => $data,
        ]);
    }

    /**
     * @Route("/viewreservationdollars", name="viewreservationdollars")
     * @Route("/viewreservationdollars/{reservationID}")
     */
    public function viewreservationdollarsAction(Request $request,$reservationID='')
    {
        $em = $this->getDoctrine()->getManager();


        return $this->render('reservations/viewreservationdollars.html.twig',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/viewreservationnotes", name="viewreservationnotes")
     * @Route("/viewreservationnotes/{reservationID}")
     */
    public function viewreservationnotesAction(Request $request,$reservationID='')
    {
        $em = $this->getDoctrine()->getManager();


        return $this->render('reservations/viewreservationnotes.html.twig',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/viewreservationcancel", name="viewreservationcancel")
     * @Route("/viewreservationcancel/{reservationID}")
     */
    public function viewreservationcancelAction(Request $request,$reservationID='')
    {
        $em = $this->getDoctrine()->getManager();


        return $this->render('reservations/viewreservationcancel.html.twig',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/lookupreservation", name="lookupreservation")
     */
    public function lookupreservationAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->query->get('reservationID');
        $sql = "SELECT `reservationID` FROM `reservations` WHERE `reservationID` = '$reservationID'";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $found = "0";
        while ($row = $result->fetch()) {
            $found = "1";
        }        
        return $this->render('reservations/lookupreservation.html.twig',[
            'found' => $found,
            'reservationID' => $reservationID,
        ]);
    }



}
