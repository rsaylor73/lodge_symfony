<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class DiscountController extends Controller
{

    /**
     * @Route("/reservationdiscount", name="reservationdiscount")
     */
    public function reservationdiscountAction(Request $request)
    {
    	$em = $this->getDoctrine()->getManager();
    	$AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->request->get('reservationID');

        $sql = "
        SELECT
        	`d`.`general_discount_reasonID` AS 'id',
        	`d`.`general_discount_reason` AS 'details'

        FROM
        	`$AF_DB`.`general_discount_reasons` d

        WHERE
        	1
        	AND `d`.`status` = 'active'

        ORDER BY `d`.`general_discount_reason` ASC
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $discount = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $discount[$i][$key] = $value;
            }
            $i++;
        } 

        return $this->render('discounts/reservationdiscount.html.twig',[
            'reservationID' => $reservationID,
            'tab' => '3',
            'discount' => $discount,
        ]);  
    }

   /**
     * @Route("/processreservationdiscount", name="processreservationdiscount")
     */
    public function processreservationdiscountAction(Request $request)
    {
    	$em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');    
        $discount_reason = $request->request->get('discount_reason');
        $discount_amount = $request->request->get('discount_amount');

        $date = date("Ymd");

        $sql = "
        INSERT INTO `discounts` 
        (`reasonID`,`amount`,`reservationID`,`date`) 
        VALUES
        ('$discount_reason','$discount_amount','$reservationID','$date')
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $discountID = $em->getConnection()->lastInsertId();

        if ($discountID == "") {
        	$text = "The discount failed to add.";
        	$status = "danger";
        } else {
        	$text = "The discount was added.";
        	$status = "success";          
        }

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);
    }

    /**
     * @Route("/editreservationdiscount", name="editreservationdiscount")
     */
    public function editreservationdiscountAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $reservationID = $request->request->get('reservationID');
        $discountID = $request->request->get('discountID');

        $sql = "
        SELECT
        	`d`.`general_discount_reasonID` AS 'id',
        	`d`.`general_discount_reason` AS 'details'

        FROM
        	`$AF_DB`.`general_discount_reasons` d

        WHERE
        	1
        	AND `d`.`status` = 'active'

        ORDER BY `d`.`general_discount_reason` ASC
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $discount = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $discount[$i][$key] = $value;
            }
            $i++;
        } 


        $sql = "SELECT * FROM `discounts` WHERE `discountID` = '$discountID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $data = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        }        

        return $this->render('discounts/editdiscount.html.twig',[
            'reservationID' => $reservationID,
            'discountID' => $discountID,
            'tab' => '3',
            'discount' => $discount,
            'data' => $data,
        ]);  
    }

   /**
     * @Route("/updatereservationdiscount", name="updatereservationdiscount")
     */
    public function updatereservationdiscountAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');  
        $discountID = $request->request->get('discountID');  
        $discount_reason = $request->request->get('discount_reason');
        $discount_amount = $request->request->get('discount_amount');

        $sql = "
        UPDATE `discounts` SET 
        `reasonID` = '$discount_reason',
        `amount` = '$discount_amount'
        WHERE `discountID` = '$discountID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $text = "The discount was updated.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);

    }

   /**
     * @Route("/deletereservationdiscount", name="deletereservationdiscount")
     */
    public function deletereservationdiscountAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $reservationID = $request->request->get('reservationID');  
        $discountID = $request->request->get('discountID');  

        $sql = "DELETE FROM `discounts` WHERE `discountID` = '$discountID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $text = "The discount was deleted.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('viewreservationdollars',[
            'reservationID' => $reservationID,
        ]);
    }


}