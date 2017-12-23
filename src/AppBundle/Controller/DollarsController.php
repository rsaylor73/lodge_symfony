<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class DollarsController extends Controller
{

    /**
     * @Route("/viewreservationdollars", name="viewreservationdollars")
     * @Route("/viewreservationdollars/{reservationID}")
     */
    public function viewreservationdollarsAction(Request $request,$reservationID='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $AF_DB = $this->container->getParameter('AF_DB');
        $em = $this->getDoctrine()->getManager();

        // init vars
        $transfer_amount = "";
        $pax = "";
        $transfer_total = "";
        $payment_history = "";
        $manual_commission_override = "";

        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

        $details = $this->get('reservationdetails')->getresdetails($reservationID);

        $sql = "
        SELECT
        	SUM(`i`.`nightly_rate`) AS 'total',
        	MIN(`i`.`nightly_rate`) AS 'nightly_rate',
        	`r`.`pax`,
        	`r`.`children`,
        	`r`.`nights`,
            `r`.`manual_commission_override`

        FROM
        	`inventory` i

        LEFT JOIN `reservations` r ON `i`.`reservationID` = `r`.`reservationID`

        WHERE
        	`i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`pax`, `r`.`children`, `r`.`nights`
        ";
		$result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $dollars = "";
        $total = "";
        while ($row = $result->fetch()) {
        	foreach($row as $key=>$value) {
        		$dollars[$i][$key] = $value;
        	}
            $total = $row['total'];
            $manual_commission_override = $row['manual_commission_override'];
        	$i++;
            
            $transfer_amount = $this
            ->get('reservationdetails')
            ->transfer_amount($details['nights']);            

            $pax = $details['pax'] + $details['children'];
            $transfer_total = $transfer_amount * $pax;
        }

        // payment history
        $payment_history = $this
        ->get('reservationdetails')
        ->payment_history($reservationID);

        $payment_total = "0";
        if(is_array($payment_history)) {
            foreach($payment_history as $key=>$value) {
                foreach($value as $key2=>$value2) {
                    if ($key2 == "amount") {
                        $payment_total = $payment_total + $value2;
                    }
                }
            }
        }

        // comp space
        $sql = "
        SELECT
            `r`.`description`,
            `i`.`roomID`,
            `i`.`bed`

        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`

        WHERE
            `i`.`reservationID` = '$reservationID'
            AND `i`.`nightly_rate` = '0'

        GROUP BY `i`.`roomID`,`i`.`bed`

        ORDER BY `r`.`description` ASC, `i`.`bed` ASC
        ";        

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $comp = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $comp[$i][$key] = $value;
            }
            $i++;
        }

        // discount history
        $discount_history = $this
        ->get('reservationdetails')
        ->discount_history($reservationID);

        $discount_total = "0";
        if(is_array($discount_history)) {
            foreach($discount_history as $key=>$value) {
                foreach($value as $key2=>$value2) {
                    if ($key2 == "amount") {
                        $discount_total = $discount_total + $value2;
                    }
                }
            }
        }        

        // commission
        $sql = "
        SELECT
            `rs`.`commission`
        FROM
            `reservations` r

        LEFT JOIN `$AF_DB`.`resellers` rs ON `r`.`resellerID` = `rs`.`resellerID`

        WHERE
            `r`.`reservationID` = '$reservationID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $commission = "0";
        while ($row = $result->fetch()) {
            $commission = $row['commission'];
        }

        if ($manual_commission_override > 0) {
            $commission = $manual_commission_override;
        }

        if ($commission == "") {
            $commission = "0";
        }

        $total_commissionable = $total - $discount_total;
        $comm_amount = floor($total_commissionable * ($commission / 100));

        // balance
        $balance = ($total + $transfer_total)  - $discount_total - $comm_amount - $payment_total;

        return $this->render('reservations/viewreservationdollars.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '3',
            'dollars' => $dollars,
            'transfer_amount' => $transfer_amount,
            'transfer_total' => $transfer_total,
            'payment_history' => $payment_history,
            'payment_total' => $payment_total,
            'discount_history' => $discount_history,
            'discount_total' => $discount_total,
            'commission' => $commission,
            'comm_amount' => $comm_amount,
            'balance' => $balance,
            'details' => $details,
            'comp' => $comp,         
        ]);
    }

    /**
     * @Route("/compspace", name="compspace")
     */
    public function compspaceAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $AF_DB = $this->container->getParameter('AF_DB');
        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');

        $sql = "
        SELECT
            `r`.`description`,
            `i`.`roomID`,
            `i`.`bed`

        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`

        WHERE
            `i`.`reservationID` = '$reservationID'
            AND `i`.`nightly_rate` != '0'

        GROUP BY `i`.`roomID`,`i`.`bed`

        ORDER BY `r`.`description` ASC, `i`.`bed` ASC
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $data = "";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        }

        return $this->render('discounts/compspace.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '3',
            'data' => $data,
        ]);
    }

    /**
     * @Route("/setcompspace", name="setcompspace")
     */
    public function setcompspaceAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $AF_DB = $this->container->getParameter('AF_DB');
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->query->get('reservationID');
        $bed = $request->query->get('bed');
        $roomID = $request->query->get('roomID');

        $sql = "
        UPDATE `inventory` 
        SET `nightly_rate` = '0' 
        WHERE 
        `reservationID` = '$reservationID'
        AND `roomID` = '$roomID'
        AND `bed` = '$bed'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $this->addFlash('success','The comp space was added.');
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);
    }


    /**
     * @Route("/unsetcompspace", name="unsetcompspace")
     */
    public function unsetcompspaceAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $AF_DB = $this->container->getParameter('AF_DB');
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->query->get('reservationID');
        $bed = $request->query->get('bed');
        $roomID = $request->query->get('roomID');

        // get rate
        $sql = "SELECT `nightly_rate` FROM `rooms` WHERE `id` = '$roomID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $nightly_rate = "0";
        while ($row = $result->fetch()) {
            $nightly_rate = $row['nightly_rate'];
        }

        $sql = "UPDATE `inventory` SET `nightly_rate` = '$nightly_rate'
        WHERE `reservationID` = '$reservationID' AND `roomID` = '$roomID'
        AND `bed` = '$bed'";        

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $this->addFlash('success','The comp space was removed.');
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);
    }
}