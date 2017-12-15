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

        // tent total
        $total = $this->tenttotal($em,$reservationID);

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

        $date = date("m/d/Y");

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


        switch ($mode) {
            case "view":
            return $this->render('invoice/invoice.html.twig',[
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

            ]);
            break;

            case "print":
            $print = "Yes";
            return $this->render('invoice/invoice.html.twig',[
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
                            'invoice/invoice.html.twig',
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
                            'nights' => $details['nights']
                            )
                        ),
                        'text/html'
                    )
                ;
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



    private function tenttotal($em,$reservationID) {
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
        $total = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $total = $row['total'];
        }
        return($total);
    }



}        
