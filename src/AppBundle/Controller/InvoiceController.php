<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class InvoiceController extends Controller
{

    /**
     * @Route("/invoice", name="invoice")
     */
    public function invoiceAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $reservationID = $request->query->get('reservationID');
        $mode = $request->query->get('mode');

        $site_url = $this->container->getParameter('site_url');
        $site_name = $this->container->getParameter('site_name');
        $site_email = $this->container->getParameter('site_email');
        $site_path = $this->container->getParameter('site_path');
        $cid1 = "";

        // tent total
        $total = $this
            ->get('reservationdetails')
            ->tenttotal($reservationID);

        $details = $this
            ->get('reservationdetails')
            ->getresdetails($reservationID);

        $guests = $details['pax'] + $details['children'];
        $total_guests = $guests;

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
        $res_total = ($total + $transfer_total)  - $discount_total - $comm_amount;

        $date = date("F d, Y");

        // contact details
        $first = $details['first'];
        $last = $details['last'];
        $address1 = $details['address1'];
        $address2 = $details['address2'];
        $city = $details['city'];
        $state = $details['state'];
        $province = $details['province'];
        $zip = $details['zip'];
        $country = $details['country'];
        $begin_date = $details['checkin_date'];
        $end_date = $details['checkout_date'];
        $print = "";
        $company = "";

        // guests
        $sql = "
        SELECT
            `r`.`description`,
            `i`.`bed`,
            `i`.`type` AS 'class',
            `i`.`status`,
            `t`.`type`,
            `i`.`roomID`,
            SUM(`i`.`nightly_rate`) AS 'nightly_rate',
            `c`.`contactID`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            MIN(`i`.`inventoryID`) AS 'inventoryID',
            `g`.`gisPW`

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

        WHERE
            `i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`description`, `i`.`bed`, `i`.`type`, `i`.`status`, `i`.`type`,`i`.`roomID`,
        `i`.`nightly_rate`

        ORDER BY `r`.`description` ASC, `i`.`bed` ASC
        ";

        $i = "0";
        $guests = "";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $guests[$i][$key] = $value;
            }
            $i++;
        }        

        $payment_policy = $this->get('commonservices')->payment_policy($reservationID);
        
        if ($payment_policy['reservationType'] == "Individuals") {
            $deposit_amount = $res_total * .40;
            $final_amount = $res_total - $deposit_amount;
        } elseif ($payment_policy['reservationType'] == "Groups") {
            $deposit_amount = ($res_total - 5000) * .40;
            $final_amount = ($res_total - 5000) - $deposit_amount;
        }

        $invoice_file = "";
        if ($payment_policy['reservationType'] == "Individuals") {
            $invoice_file = "invoice_individuals.html.twig";
        } elseif ($payment_policy['reservationType'] == "Groups") {
            $invoice_file = "invoice_groups.html.twig";
        }

        switch ($mode) {
            case "view":

            return $this->render('invoice/'.$invoice_file,[
                'reservationID' => $reservationID,
                'tab' => '3',
                'total' => $total,
                'transfer_total' => $transfer_total,
                'commission' => $commission,
                'total_commissionable' => $total_commissionable,
                'discount_total' => $discount_total,
                'comm_amount' => $comm_amount,
                'balance' => $balance,
                'payment_total' => $payment_total,
                'first' => $first,
                'last' => $last,
                'address1' => $address1,
                'address2' => $address2,
                'city' => $city,
                'state' => $state,
                'province' => $province,
                'country' => $country,
                'zip' => $zip,
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'print' => $print,
                'date' => $date,
                'company' => $company,
                'nights' => $details['nights'],
                'guests' => $guests,
                'total_guests' => $total_guests,
                'payment_policy' => $payment_policy,
                'details' => $details,
                'transfer_amount' => $transfer_amount,
                'deposit_amount' => $deposit_amount,
                'final_amount' => $final_amount,
                'payment_history' => $payment_history,
                'res_total' => $res_total,
                'format' => 'html',
                'site_url' => $site_url,

            ]);
            break;

            case "print":
            $print = "Yes";
            return $this->render('invoice/'.$invoice_file,[
                'reservationID' => $reservationID,
                'tab' => '3',
                'total' => $total,
                'transfer_total' => $transfer_total,
                'commission' => $commission,
                'total_commissionable' => $total_commissionable,
                'discount_total' => $discount_total,
                'comm_amount' => $comm_amount,
                'balance' => $balance,
                'payment_total' => $payment_total,
                'first' => $first,
                'last' => $last,
                'address1' => $address1,
                'address2' => $address2,
                'city' => $city,
                'state' => $state,
                'province' => $province,
                'country' => $country,
                'zip' => $zip,
                'begin_date' => $begin_date,
                'end_date' => $end_date,
                'print' => $print,
                'date' => $date,
                'company' => $company,
                'nights' => $details['nights'],
                'guests' => $guests,
                'payment_policy' => $payment_policy,
                'details' => $details,
                'transfer_amount' => $transfer_amount,
                'deposit_amount' => $deposit_amount,
                'final_amount' => $final_amount,
                'payment_history' => $payment_history,
                'res_total' => $res_total,
                'format' => 'html',
                'site_url' => $site_url,
            ]);
            break;

            case "email":
                $name = $details['first'] . " " . $details['last'];
                $email = $details['email'];


                $title = "Aggressor Safari Lodge Conf # $reservationID Invoice";

                // send welcome email

                $message = (new \Swift_Message($title))
                    ->setFrom($site_email)
                    ->setTo($email)
                    ->setSubject($title)
                    ->setBody(
                        $this->renderView(
                            'invoice/'.$invoice_file,
                            array(
                            'reservationID' => $reservationID,
                            'tab' => '3',
                            'total' => $total,
                            'transfer_total' => $transfer_total,
                            'commission' => $commission,
                            'total_commissionable' => $total_commissionable,
                            'discount_total' => $discount_total,
                            'comm_amount' => $comm_amount,
                            'balance' => $balance,
                            'payment_total' => $payment_total,
                            'first' => $first,
                            'last' => $last,
                            'address1' => $address1,
                            'address2' => $address2,
                            'city' => $city,
                            'state' => $state,
                            'province' => $province,
                            'country' => $country,
                            'zip' => $zip,
                            'begin_date' => $begin_date,
                            'end_date' => $end_date,
                            'print' => $print,
                            'date' => $date,
                            'company' => $company,
                            'nights' => $details['nights'],
                            'guests' => $guests,
                            'payment_policy' => $payment_policy,
                            'details' => $details,
                            'transfer_amount' => $transfer_amount,
                            'deposit_amount' => $deposit_amount,
                            'final_amount' => $final_amount,
                            'payment_history' => $payment_history,
                            'res_total' => $res_total,
                            'format' => 'email',
                            'site_url' => $site_url,
                            )
                        ),
                        'text/html'
                    )
                ;
                //->attach(\Swift_Attachment::fromPath($image1)->setDisposition('inline'))

                $this->get('mailer')->send($message);

                $this->addFlash('success',"The invoice was emailed to $email.");
                return $this->redirectToRoute('viewreservation',[
                    'reservationID' => $reservationID,
                ]);

            break;

            default:
            // error
            die;
            break;
        }

        $this->addFlash('success','Test');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
            'tab' => '3',
            'total' => $total,
            'transfer_total' => $transfer_total,
            'commission' => $commission,
            'total_commissionable' => $total_commissionable,
            'discount_total' => $discount_total,
            'comm_amount' => $comm_amount,
            'balance' => $balance,
            'payment_total' => $payment_total,   
        ]);
    }

}        
