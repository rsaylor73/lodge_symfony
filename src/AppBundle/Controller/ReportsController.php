<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class ReportsController extends Controller
{

    /**
     * @Route("/balancereport", name="balancereport")
     */
    public function balancereportAction()
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $balance_details = "";
        $i = "0";

        $date = date("Ymd");
        $start = date("Ymd", strtotime($date . "-90 DAY"));
        $end = date("Ymd", strtotime($date . "+90 DAY"));

        $sql = "
        SELECT
            `r`.`reservationID`

        FROM
            `reservations` r

        WHERE
            `r`.`status` = 'Active'
            AND DATE_FORMAT(`r`.`checkin_date`, '%Y%m%d') BETWEEN '$start' AND '$end'

        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        while ($row = $result->fetch()) {
            $reservationID = $row['reservationID'];

            // tent total
            $total = $this
                ->get('reservationdetails')
                ->tenttotal($reservationID); 

            // reservation details
            $details = $this
                ->get('reservationdetails')
                ->getresdetails($reservationID);
            
            $guests = $details['pax'] + $details['children'];

            // transfers
            $nights = $details['nights'] - 1;
            $transfer_amount = $this
                ->get('reservationdetails')
                ->transfer_amount($nights);
            $transfer_total = $transfer_amount * $guests;

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
            $sql2 = "
            SELECT
                `rs`.`commission`
            FROM
                `reservations` r

            LEFT JOIN `$AF_DB`.`resellers` rs ON `r`.`resellerID` = `rs`.`resellerID`

            WHERE
                `r`.`reservationID` = '$reservationID'
            ";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();
            $commission = "0";
            while ($row2 = $result2->fetch()) {
                $commission = $row2['commission'];
            }

            $manual_commission_override = $details['manual_commission_override'];
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

            $balance_details[$i]['reservationID'] = $reservationID;
            $balance_details[$i]['checkin_date'] = $details['checkin_date'];
            $balance_details[$i]['nights'] = $details['nights'];
            $balance_details[$i]['first'] = $details['first'];
            $balance_details[$i]['last'] = $details['last'];
            $balance_details[$i]['email'] = $details['email'];
            $balance_details[$i]['balance'] = $balance;
            $balance_details[$i]['payment_total'] = $payment_total;


            $i++;

        }

        $date = date("m/d/Y");

        return $this->render('reports/balance.html.twig',[
            'date' => $date,
            'balance_details' => $balance_details,
        ]);        
    }

    /**
     * @Route("/paymentsreport", name="paymentsreport")
     */
    public function paymentsreportAction()
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();

        $date = date("Ymd");
        $start = date("Ymd", strtotime($date . "-90 DAY"));

        $sql = "
        SELECT
            `p`.`reservationID`,
            `p`.`type`,
            `p`.`credit_description`,
            `p`.`check_description`,
            `p`.`wire_description`,
            `p`.`amount`,
            DATE_FORMAT(`p`.`payment_date`, '%m/%d/%Y') AS 'payment_date',
            `r`.`status`

        FROM
            `payments` p, `reservations` r

        WHERE
            1
            AND DATE_FORMAT(`p`.`payment_date`, '%Y%m%d') BETWEEN '$start' AND '$date'
            AND `p`.`reservationID` = `r`.`reservationID`

        ORDER BY `p`.`payment_date` DESC

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

        $date = date("m/d/Y");
        return $this->render('reports/payments.html.twig',[
            'date' => $date,
            'data' => $data,
        ]);        
    }

}