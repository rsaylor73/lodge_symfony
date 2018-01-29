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


class GuestsController extends Controller
{

    /**
     * @Route("/moveguest", name="moveguest")
     */
    public function moveguestAction(Request $request)
    {

        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');
		$lodge = "6"; // change this soon

		$reservationID = $request->request->get('reservationID');
		$details = $this->get('reservationdetails')->getresdetails($reservationID);

		// Get current reservation details
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
            MIN(`i`.`inventoryID`) AS 'inventoryID'


        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`
        LEFT JOIN `roomtype` t ON `i`.`typeID` = `t`.`id`
        LEFT JOIN `$AF_DB`.`contacts` c ON `i`.`contactID` = `c`.`contactID`

        WHERE
            `i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`description`, `i`.`bed`, `i`.`type`, `i`.`status`, `i`.`type`,`i`.`roomID`

        ORDER BY `r`.`id` ASC, `i`.`bed` ASC
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

        // Get available details
        // check availability
        $start = date("Ymd", strtotime($details['checkin_date']));
        $end = date("Ymd", strtotime($details['checkout_date']));

        $total_pax = $details['pax'] * $details['nights'];
        $total_child = $details['children'] * $details['nights'];


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
        $inventory = "";
        $i = "0";
        $nights = $details['nights'];
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

            ORDER BY `r`.`id` ASC    
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

        return $this->render('guests/moveguest.html.twig',[
        	'reservationID' => $reservationID,
        	'tab' => '2',
        	'details' => $details,
        	'data' => $data,
        	'found' => $found,
        	'inventory' => $inventory,

        ]);
    }


    /**
     * @Route("/processmoveguest", name="processmoveguest")
     */
    public function processmoveguestAction(Request $request)
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
        $target = $request->request->get('target');
        

        $details = $this->get('reservationdetails')->getresdetails($reservationID);
        $nights = $details['nights'];
        $start = date("Ymd", strtotime($details['checkin_date']));
        $end = date("Ymd", strtotime($details['checkout_date']));
        $lodge = "6"; // change this to automatic


        // get contact (source)
        $contactID = "";
        $bed = "";
        $nightly_rate = "";
        $source_roomID = "";
        $sql = "SELECT `contactID`,`bed`,`nightly_rate`,`roomID` FROM `inventory` WHERE `inventoryID` = '$inventoryID' AND `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $contactID = $row['contactID'];
            $nightly_rate = $row['nightly_rate'];
            $bed = $row['bed'];
            $source_roomID = $row['roomID'];
        }

        // get target
        $sql = "
        SELECT
            `i`.`type`,
            `i`.`roomID`,
            `i`.`typeID`,
            `i`.`nightly_rate`,
            `i`.`bed`,
            `r`.`description`,
            `r`.`writeup`,
            `t`.`type`,
            MIN(`i`.`inventoryID`) AS 'inventoryID'

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

        ORDER BY `r`.`id` ASC    
        ";          
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $targetID = "";
        $roomID = "";
        $target_roomID = "";
        $bedTarget = "";
        $target_bed = "";
        $test = "";
        $check = "";
        $target_nightly_rate = "";
        while ($row = $result->fetch()) {
            // room{{i.roomID}}_{{i.bed}}
            $roomID = $row['roomID'];
            $bedTarget = $row['bed'];
            $test = "room" . $roomID . "_" . $bedTarget;
            if ($target == $test) {
                $targetID = $row['inventoryID'];
                $target_nightly_rate = $row['nightly_rate'];
                $target_roomID = $row['roomID'];
                $target_bed = $row['bed'];
            }
        }

        // Book new room
        $sql = "UPDATE `inventory` SET
        `reservationID` = '$reservationID',
        `contactID` = '$contactID',
        `status` = 'booked',
        `nightly_rate` = '$nightly_rate'
        WHERE 
            `roomID` = '$target_roomID'
            AND `bed` = '$target_bed'
            AND `date_code` BETWEEN '$start' AND '$end'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        // Remove old room
        $sql = "UPDATE `inventory` SET
            `reservationID` = '0',
            `contactID` = '0',
            `status` = 'avail',
            `nightly_rate` = '$target_nightly_rate'

        WHERE
            `roomID` = '$source_roomID'
            AND `bed` = '$bed'
            AND `date_code` BETWEEN '$start' AND '$end'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        // Update gis
        $sql = "UPDATE `gis` SET `inventoryID` = '$targetID' WHERE `inventoryID` = '$inventoryID' AND `reservationID` = '$reservationID'";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        // Update gis_action
        $sql = "UPDATE `gis_action` SET `inventoryID` = '$targetID' WHERE `inventoryID` = '$inventoryID' AND `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        // Update gis_travel_info
        $sql = "UPDATE `gis_travel_info` SET `inventoryID` = '$targetID' WHERE `inventoryID` = '$inventoryID' AND `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();                        


        $this->addFlash('success','The guest has been moved.');
        return $this->redirectToRoute('viewreservationguest',[
            'reservationID' => $reservationID,
        ]);

    }

}