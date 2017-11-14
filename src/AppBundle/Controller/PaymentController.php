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
        $reservationID = $request->request->get('reservationID');

        return $this->render('payments/reservationpayment.html.twig',[
            'reservationID' => $reservationID,
        ]);  
    }

     /**
     * @Route("/processpayment", name="processpayment")
     */
    public function processpaymentAction(Request $request, authorizenet $authorizenet)
    {   
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

            break;

            case 3: // wire

            break;
        }
        

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);

    }


    private function recordccpayment($em,$request,$reservationID,$transactionID) {
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

}      