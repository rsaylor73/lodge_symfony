<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class PaymentController extends Controller
{

    /**
     * @Route("/reservationpayment", name="reservationpayment")
     */
    public function reservationpaymentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $reservationID = $request->request->get('reservationID');

        return $this->render('payments/reservationpayment.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '3',
        ]);  
    }

    /**
     * @Route("/editreservationpayment", name="editreservationpayment")
     */
    public function editreservationpaymentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');
        $paymentID = $request->request->get('paymentID');

        $sql = "SELECT * FROM `payments` WHERE `paymentID` = '$paymentID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $payment = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $payment[$i][$key] = $value;
            }
            $i++;
        }        

        return $this->render('payments/editpayment.html.twig',[
            'reservationID' => $reservationID,
            'paymentID' => $paymentID,
            'tab' => '3',
            'payment' => $payment,
        ]);  
    }

    /**
     * @Route("/deletereservationpayment", name="deletereservationpayment")
     */
    public function deletereservationpaymentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');
        $paymentID = $request->request->get('paymentID');

        $sql = "DELETE FROM `payments` WHERE `paymentID` = '$paymentID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $text = "The payment was deleted.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/updatereservationpayment", name="updatereservationpayment")
     */
    public function updatereservationpaymentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->request->get('reservationID');
        $paymentID = $request->request->get('paymentID');
        $type = $request->request->get('type');
        $transactionID = $request->request->get('transactionID');
        $checkNumber = $request->request->get('checkNumber');
        $credit_description = $request->request->get('credit_description');
        $check_description = $request->request->get('check_description');
        $wire_description = $request->request->get('wire_description');
        $amount = $request->request->get('amount');
        $payment_date = $request->request->get('payment_date');

        $sql = "UPDATE `payments` SET
        `type` = '$type',
        `transactionID` = '$transactionID',
        `checkNumber` = '$checkNumber',
        `credit_description` = '$credit_description',
        `check_description` = '$check_description',
        `wire_description` = '$wire_description',
        `amount` = '$amount',
        `payment_date` = '$payment_date'
        WHERE `paymentID` = '$paymentID'
        ";        

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $text = "The payment was updated.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);

    }

     /**
     * @Route("/processpayment", name="processpayment")
     */
    public function processpaymentAction(Request $request, authorizenet $authorizenet)
    {   
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AUTHNET_LOGIN = $this->container->getParameter('AUTHNET_LOGIN');
        $AUTHNET_KEY = $this->container->getParameter('AUTHNET_KEY');
        $AUTHNET_TEST = $this->container->getParameter('AUTHNET_TEST');

        $reservationID = $request->request->get('reservationID');
        $payment_type = $request->request->get('payment_type');
        $cc_name = $request->request->get('cc_name');
        $cc_num = $request->request->get('cc_num');
        $cc_month = $request->request->get('cc_month');
        $cc_year = $request->request->get('cc_year');
        $cvv = $request->request->get('cvv');
        $check_number = $request->request->get('check_number');
        $check_description = $request->request->get('check_description');
        $wire_description = $request->request->get('wire_description');
        $payment_amount = $request->request->get('payment_amount');
        $payment_amount = number_format((float)$payment_amount, 2, '.', '');
        $payment_date = $request->request->get('payment_date');


        // init vars
        $text = "";
        $test = "";
        $payment_recorded = "";
        $status = "";

        switch ($payment_type) {

            case 1: // credit    
            $a = $authorizenet;

            $a->add_field('x_login', $AUTHNET_LOGIN);
            $a->add_field('x_tran_key', $AUTHNET_KEY);
            $a->add_field('x_version', '3.1');
            $a->add_field('x_type', 'AUTH_CAPTURE');

            if ($AUTHNET_TEST == "true") {
                $a->add_field('x_test_request', 'TRUE');
            }        

            $a->add_field('x_relay_response', 'FALSE');
            $a->add_field('x_delim_data', 'TRUE');
            $a->add_field('x_delim_char', '|');
            $a->add_field('x_encap_char', '');
            $a->add_field('x_email_customer', 'FALSE');
            $a->add_field('x_description', "ATSL $reservationID");

            $a->add_field('x_method', 'CC');
            $a->add_field('x_card_num', $cc_num);
            $a->add_field('x_amount', $payment_amount);
            $exp_date = $cc_month . $cc_year;
            $a->add_field('x_exp_date', $exp_date);    // march of 2008
            $a->add_field('x_card_code', $cvv);    // Card CAVV Security code

            $transactionID = "";
            switch ($a->process()) {
                case 1: // Accepted
                $transactionID = $a->get_transaction_id();
                $payment_recorded = $this->recordccpayment($em,$request,$reservationID,$transactionID);
                if ($payment_recorded == "false") {
                    $this->addFlash('danger','The credit card was processed but failed to record the payment details.');
                    return $this->redirectToRoute('viewreservationdollars',[
                        'reservationID' => $reservationID,
                    ]);
                }
                $text = $a->get_response_reason_text();
                $status = "success";
                break;

                case 2: // Declined
                $text = $a->get_response_reason_text();
                $status = "danger";
                break;

                case 3: // Error
                $text = $a->get_response_reason_text();
                $status = "danger";
                break;
            }
            break;

            case 2: // check
            case 3: // wire
                $payment_recorded = $this->recordmanualpayment($em,$request,$reservationID);
                if ($payment_recorded == "false") {
                    $this->addFlash('danger','The payment failed to record the payment details.');
                    return $this->redirectToRoute('viewreservationdollars',[
                        'reservationID' => $reservationID,
                    ]);
                }
                $text = "The payment was recorded.";
                $status = "success";                
            break;

        }
        

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);

    }


    private function recordccpayment($em,$request,$reservationID,$transactionID) {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $cc_name = $request->request->get('cc_name');
        $payment_amount = $request->request->get('payment_amount');
        $payment_amount = number_format((float)$payment_amount, 2, '.', '');
        $payment_date = $request->request->get('payment_date');
        $credit_description = "";
        $paymentID = "";

        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();
        $userID = "";
        $sql = "SELECT `id` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $userID = $row['id'];
        } 

        $credit_description = "CC - $cc_name";
        $date = date("Ymd");

        $sql = "
        INSERT INTO `payments` 
        (`userID`,`reservationID`,`type`,`transactionID`,`credit_description`,
        `amount`,`payment_date`,`date`)
        VALUES
        ('$userID','$reservationID','Credit','$transactionID','$credit_description',
        '$payment_amount','$payment_date','$date')
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $paymentID = $em->getConnection()->lastInsertId();
        if ($paymentID == "") {
            return('false');
        } else {
            return('true');
        }
    }

    private function recordmanualpayment($em,$request,$reservationID) {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('payments');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
        $payment_amount = $request->request->get('payment_amount');
        $payment_amount = number_format((float)$payment_amount, 2, '.', '');
        $payment_date = $request->request->get('payment_date');
        $payment_type = $request->request->get('payment_type');
        $check_number = $request->request->get('check_number');
        $check_description = $request->request->get('check_description');
        $wire_description = $request->request->get('wire_description');        
        $paymentID = "";

        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();
        $userID = "";
        $sql = "SELECT `id` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $userID = $row['id'];
        } 

        $date = date("Ymd");

        $sql = "";
        switch ($payment_type) {
            case 2: // check
            $sql = "
            INSERT INTO `payments` 
            (`userID`,`reservationID`,`type`,`checkNumber`,`check_description`,
            `amount`,`payment_date`,`date`)
            VALUES
            ('$userID','$reservationID','Check','$check_number','$check_description',
            '$payment_amount','$payment_date','$date')
            ";
            break;

            case 3: // wire
            $sql = "
            INSERT INTO `payments` 
            (`userID`,`reservationID`,`type`,`wire_description`,
            `amount`,`payment_date`,`date`)
            VALUES
            ('$userID','$reservationID','Wire','$wire_description',
            '$payment_amount','$payment_date','$date')
            ";
            break;
        }

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $paymentID = $em->getConnection()->lastInsertId();
        if ($paymentID == "") {
            return('false');
        } else {
            return('true');
        }
    }

}      