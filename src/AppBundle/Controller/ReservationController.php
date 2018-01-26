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
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

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
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

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

        $grand_pax = $pax + $children;

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

            HAVING COUNT(`i`.`roomID`) >= $nights

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
            'grand_pax' => $grand_pax,
        ]);

    }

    /**
     * @Route("/createreservation", name="createreservation")
     */
    public function createreservationAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

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
        (`userID`,`date_booked`,`status`,`pax`,`children`,`child1_age`,`child2_age`,`checkin_date`,`nights`,`locationID`)
        VALUES 
        ('$userID',NOW(),'Active','$pax','$children','$childage1','$childage2','$start_date','$nights','$lodge')";
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

        $this->addFlash('success','The reservation was booked. Please assign a reseller agent, primary contact and reservation guests.');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
        ]);        
    }

    /**
     * @Route("/locatereservation", name="locatereservation")
     */
    public function locatereservationAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        return $this->render('reservations/locatereservation.html.twig');
    }


    /**
     * @Route("/viewreservation", name="viewreservation")
     * @Route("/viewreservation/{reservationID}")
     */
    public function viewreservationAction(Request $request,$reservationID='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

        $details = $this->get('reservationdetails')->getresdetails($reservationID);

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
            `r`.`child2_age`,
            `r`.`resellerID`,
            `r`.`resellerAgentID`,
            `r`.`contactID`,
            `r`.`reservationType`,
            `r`.`group_contracts`,
            DATE_FORMAT(`r`.`group_contracts_timestamp`, '%M %d, %Y') AS 'group_contracts_timestamp',
            `cu`.`first_name` AS 'cu_first_name',
            `cu`.`last_name` AS 'cu_last_name',
            `cu`.`email` AS 'cu_email'            
        
        FROM
            `reservations` r

        LEFT JOIN `user` cu ON `r`.`group_contracts_user_received` = `cu`.`id`
        LEFT JOIN `user` u ON `r`.`userID` = `u`.`id`

        WHERE
            `r`.`reservationID` = '$reservationID'
        ";

        // init
        $nights = "";
        $checkin_date = "";
        $checkout_date = "";
        $first_name = "";
        $last_name = "";
        $email = "";
        $date_booked = "";
        $status = "";
        $pax = "";
        $children = "";
        $child1_age = "";
        $child2_age = "";
        $resellerID = "";
        $resellerAgentID = "";
        $contactID = "";
        $reservationType = "";
        $group_contracts = "";
        $group_contracts_timestamp = "";
        $cu_first_name = "";
        $cu_last_name = "";
        $cu_email = "";

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
            $resellerID = $row['resellerID'];
            $resellerAgentID = $row['resellerAgentID'];
            $contactID = $row['contactID'];
            $reservationType = $row['reservationType'];
            $group_contracts = $row['group_contracts'];
            $group_contracts_timestamp = $row['group_contracts_timestamp'];
            $cu_first_name = $row['cu_first_name'];
            $cu_last_name = $row['cu_last_name'];
            $cu_email = $row['cu_email'];
        }

        // Reseller data
        $reseller_data = "";
        if ($resellerID != "") {
            // lookup reseller
            $sql = "
            SELECT
                `r`.`company`,
                `r`.`status`,
                `r`.`commission`

            FROM
                `$AF_DB`.`resellers` r

            WHERE
                `r`.`resellerID` = '$resellerID'
            ";

            $result = $em->getConnection()->prepare($sql);
            $result->execute();
            $i = "0";
            while ($row = $result->fetch()) {
                $reseller_data[$i]['company'] = $row['company'];
                $reseller_data[$i]['status'] = $row['status'];
                $reseller_data[$i]['commission'] = $row['commission'];
            }
        }

        // Reseller agent
        $reselleragent_data = "";
        if ($resellerAgentID != "") {
            // Lookup agent
            $sql = "
            SELECT
                `a`.`first`,
                `a`.`last`,
                `a`.`status`,
                `a`.`email`,
                `a`.`waiver`,
                `a`.`resellerID`
            FROM
                `$AF_DB`.`reseller_agents` a

            WHERE
                `a`.`reseller_agentID` = '$resellerAgentID'
            ";
            $result = $em->getConnection()->prepare($sql);
            $result->execute();
            $i = "0";
            while ($row = $result->fetch()) {
                $reselleragent_data[$i]['name'] = $row['first'] . " " . $row['last'];
                $reselleragent_data[$i]['status'] = $row['status'];
                $reselleragent_data[$i]['email'] = $row['email'];
                $reselleragent_data[$i]['waiver'] = $row['waiver'];
                $reselleragent_data[$i]['resellerID'] = $row['resellerID'];
            }            
        }

        // Reservation Contact
        $contact_data = "";
        if ($contactID > 0) {
            $sql = "
            SELECT 
                `c`.`contactID`,
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
            $i = "0";
            while ($row = $result->fetch()) {
                $contact_data[$i]['first'] = $row['first'];
                $contact_data[$i]['middle'] = $row['middle'];
                $contact_data[$i]['last'] = $row['last'];
                $contact_data[$i]['email'] = $row['email'];
                $contact_data[$i]['contactID'] = $row['contactID'];
            }             
        }

        return $this->render('reservations/viewreservation.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '1',
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
            'reseller_data' => $reseller_data,
            'reselleragent_data' => $reselleragent_data,
            'contact_data' => $contact_data,
            'details' => $details,
            'reservationType' => $reservationType,
            'group_contracts' => $group_contracts,
            'group_contracts_timestamp' => $group_contracts_timestamp,
            'cu_first_name' => $cu_first_name,
            'cu_last_name' => $cu_last_name,
            'cu_email' => $cu_email,
        ]);
    }

    /**
     * @Route("/viewreservationguest", name="viewreservationguest")
     * @Route("/viewreservationguest/{reservationID}")
     */
    public function viewreservationguestAction(Request $request,$reservationID='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

        $details = $this->get('reservationdetails')->getresdetails($reservationID);
        $gisurl = $this->container->getParameter('gisurl');

        $sql = "
        SELECT
            `r`.`description`,
            `i`.`bed`,
            `i`.`type` AS 'class',
            `i`.`status`,
            `t`.`type`,
            `i`.`roomID`,
            `c`.`contactID`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            MIN(`i`.`inventoryID`) AS 'inventoryID',
            `g`.`gisPW`,
            `a`.`gis_guest_info`,
            `a`.`gis_waiver`,
            `a`.`gis_policy`,
            `a`.`gis_emergency_contact`,
            `a`.`gis_requests`,
            `a`.`gis_trip_insurance`,
            `a`.`gis_travel_info`,
            `a`.`gis_confirmation`

        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`
        LEFT JOIN `roomtype` t ON `i`.`typeID` = `t`.`id`
        LEFT JOIN `$AF_DB`.`contacts` c ON `i`.`contactID` = `c`.`contactID`
        LEFT JOIN `gis` g 
            ON 
                `i`.`inventoryID` = `g`.`inventoryID`
                AND `g`.`reservationID` = '$reservationID'
                AND `g`.`contactID` = `c`.`contactID`
        LEFT JOIN `gis_action` a
            ON
                `i`.`inventoryID` = `a`.`inventoryID`
                AND `a`.`reservationID` = '$reservationID'
                AND `a`.`contactID` = `c`.`contactID`

        WHERE
            `i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`description`, `i`.`bed`, `i`.`type`, `i`.`status`, `i`.`type`,`i`.`roomID`

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

        // GIS History
        $sql = "
        SELECT
            `u`.`first_name`,
            `u`.`last_name`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            DATE_FORMAT(`g`.`date`, '%m/%d/%Y') AS 'date',
            `g`.`time`

        FROM
            `gis_log` g

        LEFT JOIN `$AF_DB`.`contacts` c ON `g`.`contactID` = `c`.`contactID`
        LEFT JOIN `user` u ON `g`.`userID` = `u`.`id`

        WHERE
            `g`.`reservationID` = '$reservationID'

        ORDER BY `g`.`date` DESC, `g`.`time` DESC
        ";

        $i = "0";
        $gislog = "";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $gislog[$i][$key] = $value;
            }
            $i++;
        }

        return $this->render('reservations/viewreservationguest.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '2',
            'data' => $data,
            'details' => $details,
            'gisurl' => $gisurl,
            'gislog' => $gislog,
        ]);
    }

    /**
     * @Route("/lookupreservation", name="lookupreservation")
     */
    public function lookupreservationAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

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


    /**
     * @Route("/addtoreservation", name="addtoreservation")
     */
    public function addtoreservationAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');

        $sql = "
        SELECT
            `i`.`locationID`

        FROM
            `reservations` r,
            `inventory` i

        WHERE
            `r`.`reservationID` = '$reservationID'
            AND `r`.`reservationID` = `i`.`reservationID`

        LIMIT 1
        ";

        $lodge = "";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $lodge = $row['locationID'];
        }

        $sql = "
        SELECT
            DATE_FORMAT(`r`.`checkin_date`,'%Y%m%d') AS 'checkin_date',
            DATE_FORMAT(
                DATE_ADD(`r`.`checkin_date`, INTERVAL `r`.`nights` DAY),
                '%Y%m%d'
            ) AS 'checkout_date',
            `r`.`nights`

        FROM
            `reservations` r

        WHERE
            `r`.`reservationID` = '$reservationID'
            AND `r`.`status` = 'Active'

        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $found = "0";
        $pax = "0";
        $children = "0";
        $start = "";
        $end = "";
        while ($row = $result->fetch()) {
            $found = "1";
            $nights = $row['nights'];
            $pax = "2";
            $children = "0";
            $start = $row['checkin_date'];
            $end = $row['checkout_date'];
        } 

        if ($found == "0") {
            $this->addFlash('danger','The reservation was not found to be active.');
            return $this->redirectToRoute('viewreservationguest',[
                'reservationID' => $reservationID,
            ]);            
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

            GROUP BY `i`.`type`, `i`.`roomID`, `i`.`nightly_rate`, `i`.`bed`, `r`.`description`,
            `r`.`writeup`,  `t`.`type` 

            HAVING COUNT(`i`.`roomID`) >= $nights

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
            return $this->redirectToRoute('viewreservationguest');
        }

        $start_formatted = date("m/d/Y", strtotime($start));
        $end_formatted = date("m/d/Y", strtotime($end));

        return $this->render('reservations/addtoreservation.html.twig',[
            'reservationID' => $reservationID,
            'lodge' => $lodge,
            'pax' => $pax,
            'nights' => $nights,
            'start' => $start,
            'start_formatted' => $start_formatted,
            'end' => $end,
            'end_formatted' => $end_formatted,
            'inventory' => $inventory,
        ]);

    }

    /**
     * @Route("/saveaddtoreservation", name="saveaddtoreservation")
     */
    public function saveaddtoreservationAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');
        $start = $request->request->get('start_date');
        $nights = $request->request->get('nights');
        $nights2 = $nights - 1;
        $end = date("Ymd", strtotime($start . "+ $nights2 DAY"));

        $sql = "
        SELECT
            `i`.`locationID`,
            `r`.`pax`

        FROM
            `reservations` r,
            `inventory` i

        WHERE
            `r`.`reservationID` = '$reservationID'
            AND `r`.`reservationID` = `i`.`reservationID`

        LIMIT 1
        ";

        $lodge = "";
        $pax = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $lodge = $row['locationID'];
            $pax = $row['pax'];
        }

        $sql = "
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

        
        ORDER BY `r`.`description` ASC    
        ";  
        $test = "";
        $test2 = "";
        $inventory = "";
        $found = "0";
        $counter = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $test = "room";
            $test .= $row['roomID'];
            $test .= "_";
            $test .= $row['bed'];
            $test2 = $request->request->get($test);
            if ($test2 == "checked") {
                $sql2 = "UPDATE `inventory` SET `status` = 'tentative', `reservationID` = '$reservationID' WHERE `inventoryID` = '$row[inventoryID]'";
                $result2 = $em->getConnection()->prepare($sql2);
                $result2->execute();
                $found = "1";
                $counter++;
            }
        }
        if ($found == "0") {
            $this->addFlash('danger','There was an error adding the additional tent(s).');
            return $this->redirectToRoute('viewreservationguest',[
                'reservationID' => $reservationID,
            ]);             
        } else {
            // Increase the reservation PAX
            $sql = "
            SELECT

                `i`.`type` AS 'class'


            FROM
                `inventory` i

            LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`
            LEFT JOIN `roomtype` t ON `i`.`typeID` = `t`.`id`


            WHERE
                `i`.`reservationID` = '$reservationID'

            GROUP BY `r`.`description`, `i`.`bed`, `i`.`type`, `i`.`status`, `i`.`type`,`i`.`roomID`

            ORDER BY `r`.`description` ASC, `i`.`bed` ASC

            ";
            $adult = "0";
            $child = "0";
            $result = $em->getConnection()->prepare($sql);
            $result->execute();
            while ($row = $result->fetch()) {
                if ($row['class'] == "adult") {
                    $adult++;
                }
                if ($row['class'] == "child") {
                    $child++;
                }
            }

            $sql = "UPDATE `reservations` SET `pax` = '$adult', `children` = '$child' WHERE `reservationID` = '$reservationID'";
            $result = $em->getConnection()->prepare($sql);
            $result->execute();
            
            $this->addFlash('success','The additional tent(s) was added.');
            return $this->redirectToRoute('viewreservationguest',[
                'reservationID' => $reservationID,
            ]);             
        }
    }


    /**
     * @Route("/removetent", name="removetent") 
     */
    public function removetentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $reservationID = $request->query->get('reservationID');
        $roomID = $request->query->get('roomID');
        $bunk = $request->query->get('bunk');
        $inventoryID = $request->query->get('inventoryID');
        $contactID = $request->query->get('contactID');

        $em = $this->getDoctrine()->getManager();

        // get rate
        $sql = "SELECT `nightly_rate` FROM `rooms` WHERE `id` = '$roomID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $nightly_rate = "0";
        while ($row = $result->fetch()) {
            $nightly_rate = $row['nightly_rate'];
        }

        if($inventoryID != "") {
            // remove GIS actions
            $sql = "DELETE FROM `gis_action` WHERE `reservationID` = '$reservationID' AND `inventoryID` = '$inventoryID'";
            $result = $em->getConnection()->prepare($sql);
            $result->execute(); 

            // remove GIS travel details
            $sql = "DELETE FROM `gis_travel_info` WHERE `reservationID` = '$reservationID' AND `inventoryID` = '$inventoryID'";
            $result = $em->getConnection()->prepare($sql);
            $result->execute();                         
        }

        // remove GIS
        $sql = "DELETE FROM `gis` WHERE `reservationID` = '$reservationID' AND `contactID` = '$contactID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        

        // remove bunk
        $sql = "
        UPDATE 
        `inventory` 
        SET 
        `nightly_rate` = '$nightly_rate',`reservationID` = '0',`contactID` = '0',`status` = 'avail'
        WHERE 
        `reservationID` = '$reservationID' 
        AND `roomID` = '$roomID'
        AND `bed` = '$bunk'";        

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        // Increase the reservation PAX
        $sql = "
        SELECT

            `i`.`type` AS 'class'

        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`
        LEFT JOIN `roomtype` t ON `i`.`typeID` = `t`.`id`

        WHERE
            `i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`description`, `i`.`bed`, `i`.`type`, `i`.`status`, `i`.`type`,`i`.`roomID`

        ORDER BY `r`.`description` ASC, `i`.`bed` ASC

        ";
        $adult = "0";
        $child = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            if ($row['class'] == "adult") {
                $adult++;
            }
            if ($row['class'] == "child") {
                $child++;
            }
        }

        $sql = "UPDATE `reservations` SET `pax` = '$adult', `children` = '$child' WHERE `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $this->addFlash('success',"The tent was removed");
        return $this->redirectToRoute('viewreservationguest',[
            'reservationID' => $reservationID,
        ]); 
    }

    /**
     * @Route("/updatereservationtype", name="updatereservationtype")
     */
    public function updatereservationtypeAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager(); 
        $reservationID = $request->query->get('reservationID');   
        $reservationType = $request->query->get('reservationType');

        $sql = "UPDATE `reservations` SET `reservationType` = '$reservationType' WHERE `reservationID` = '$reservationID'";
        
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        

        //return $this->render('ajax/updatereservation.html.twig');
        $this->addFlash('success','The reservation type was updated.');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/updatereservationgroupcontract", name="updatereservationgroupcontract")
     */
    public function updatereservationgroupcontractAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager(); 
        $reservationID = $request->query->get('reservationID');
        $usr = $this->get('security.token_storage')->getToken()->getUser();   
        $userID = $usr->getId();
        $date = date("Ymd");

        $sql = "UPDATE `reservations` SET 
        `group_contracts` = 'Yes',
        `group_contracts_timestamp` = '$date',
        `group_contracts_user_received` = '$userID'
        WHERE `reservationID` = '$reservationID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        //return $this->render('ajax/updatereservation.html.twig');
        $this->addFlash('success','The reservation group contract was updated.');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
        ]);

    }

}
