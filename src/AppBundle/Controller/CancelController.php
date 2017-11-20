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
        $AF_DB = $this->container->getParameter('AF_DB');
        $details = $this->get('reservationdetails')->getresdetails($reservationID);

        $cxl_inventory = "";
        if ($details['status'] == "Cancelled") {
            $sql = "
            SELECT
                `r`.`description`,
                `i`.`bed`,
                `i`.`cxl_reason`,
                DATE_FORMAT(`i`.`date_cancelled`, '%m/%d/%Y') AS 'date_cancelled',
                `c`.`first`,
                `c`.`middle`,
                `c`.`last`,
                `c`.`email`

            FROM
                `inventory_cxl` i

            LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`
            LEFT JOIN `$AF_DB`.`contacts` c ON `i`.`contactID` = `c`.`contactID`

            WHERE
                `i`.`reservationID` = '$reservationID'

            GROUP BY `r`.`description`,`i`.`bed`,`i`.`cxl_reason`,`i`.`date_cancelled`,
            `c`.`first`,`c`.`last`,`c`.`middle`,`c`.`email`

            ORDER BY `r`.`description` ASC, `i`.`bed` ASC
            ";
            $result = $em->getConnection()->prepare($sql);
            $result->execute(); 
            $i = "0";       
            while ($row = $result->fetch()) { 
                foreach ($row as $key=>$value) {
                    $cxl_inventory[$i][$key] = $value;
                }
                $i++;
            }           
        }

        return $this->render('reservations/viewreservationcancel.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '5',
            'details' => $details,
            'cxl_inventory' => $cxl_inventory,
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
        $cxl_reason = $request->request->get('cxl_reason');
        $date = date("Ymd");

        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();

        // check if active
        $sql = "SELECT `id` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 
        $userID = "";       
        while ($row = $result->fetch()) {
            $userID = $row['id'];
        }

        $sql = "
        SELECT 
            `i`.`locationID`,
            `i`.`contactID`,
            `i`.`type`,
            `i`.`roomID`,
            `i`.`typeID`,
            `i`.`date_code`,
            `i`.`nightly_rate`,
            `i`.`bed`

        FROM
            `inventory` i

        WHERE
            `i`.`reservationID` = '$reservationID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $sql2 = "INSERT INTO `inventory_cxl`
            (
                `reservationID`,
                `contactID`,
                `locationID`,
                `type`,
                `typeID`,
                `roomID`,
                `date_code`,
                `nightly_rate`,
                `bed`,
                `userID`,
                `date_cancelled`,
                `cxl_reason`
            ) VALUES (
                '$reservationID',
                '$row[contactID]',
                '$row[locationID]',
                '$row[type]',
                '$row[typeID]',
                '$row[roomID]',
                '$row[date_code]',
                '$row[nightly_rate]',
                '$row[bed]',
                '$userID',
                '$date',
                ?
            )
            ";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->bindValue(1, $cxl_reason);
            $result2->execute();             
        }

        $sql = "UPDATE `inventory` SET 
        `reservationID` = '0',
        `contactID` = '0',
        `status` = 'avail'
        WHERE `reservationID` = '$reservationID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 

        $sql = "UPDATE `reservations` SET `status` = 'Cancelled', `cxl_date` = '$date' WHERE `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();         

        $text = "The reservation was cancelled.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationcancel',[
            'reservationID' => $reservationID,
        ]);
    }


}