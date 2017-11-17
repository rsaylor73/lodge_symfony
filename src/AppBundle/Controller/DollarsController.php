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
            $transfer_amount = $this->transfer_amount($row['nights']);
            $pax = $row['pax'] + $row['children'];
            $transfer_total = $transfer_amount * $pax;
        }

        // payment history
        $payment_history = $this->payment_history($em,$reservationID);
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

        // discount history
        $discount_history = $this->discount_history($em,$reservationID);
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
        ]);
    }

    private function transfer_amount($nights) {
        $amount = "";
        switch ($nights) {
            case "3":
            $amount = "150";
            break;
            case "4":
            $amount = "150";
            break;
            case "5":
            $amount = "180";
            break;
            case "6":
            $amount = "210";
            break;
            default:
            $amount = "150";
            break;
        }
        return($amount);
    }

    private function payment_history($em,$reservationID) {
        $sql = "
        SELECT
            `p`.`paymentID`,
            `p`.`type`,
            `p`.`transactionID`,
            `p`.`credit_description`,
            `p`.`checkNumber`,
            `p`.`check_description`,
            `p`.`wire_description`,
            `p`.`amount`,
            DATE_FORMAT(`p`.`payment_date`, '%m/%d/%Y') AS 'payment_date'

        FROM
            `payments` p

        WHERE
            `p`.`reservationID` = '$reservationID'

        ORDER BY DATE_FORMAT(`p`.`payment_date`,'%Y%m%d') ASC
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $i = "0";
        $payments = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $payments[$i][$key] = $value;
            }
            $i++; 
        }
        return($payments);
    }

    private function discount_history($em,$reservationID) {
        $AF_DB = $this->container->getParameter('AF_DB');

        $sql = "
        SELECT
            `d`.`discountID`,
            `r`.`general_discount_reason` AS 'details',
            `d`.`amount`,
            DATE_FORMAT(`d`.`date`, '%m/%d/%Y') AS 'date'

        FROM
            `discounts` d, `$AF_DB`.`general_discount_reasons` r

        WHERE
            `d`.`reservationID` = '$reservationID'
            AND `d`.`reasonID` = `r`.`general_discount_reasonID`
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $i = "0";
        $discounts = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $discounts[$i][$key] = $value;
            }
            $i++; 
        }
        return($discounts);        
    }

}