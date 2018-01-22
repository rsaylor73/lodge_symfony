<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class ReservationUpdateFinancialsCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:financials')
            ->setDescription('This will update the financial data once an hour for all reservations.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
        $AF_DB = $this->getContainer()->getParameter('AF_DB');

        // Get reservations that are future only
        $date = date("Ymd");
        $sql0 = "
        SELECT
        	`r`.`reservationID`
        FROM
        	`reservations` r

        WHERE
        	`r`.`status` = 'Active'
        	AND DATE_FORMAT(`r`.`checkin_date`, '%Y%m%d') >= '$date'
        ";
        $result0 = $em->getConnection()->prepare($sql0);
        $result0->execute();

        while ($row0 = $result0->fetch()) {
        	// duplicate from InvoiceController
        	$reservationID = $row0['reservationID'];

	        // tent total
	        $total = $this->getContainer()
	            ->get('reservationdetails')
	            ->tenttotal($reservationID); 

	        $details = $this->getContainer()
	            ->get('reservationdetails')
	            ->getresdetails($reservationID);

	        $guests = $details['pax'] + $details['children'];
	        $total_guests = $guests;

	        // transfers
	        $nights = $details['nights'] - 1;
	        $transfer_amount = $this->getContainer()
	            ->get('reservationdetails')
	            ->transfer_amount($nights);
	        $transfer_total = $transfer_amount * $guests;

	        // payment history
	        $payment_history = $this->getContainer()
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
	        $discount_history = $this->getContainer()
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

	        $cron_grand_total = "0";
	        $cron_grand_total = $total + $transfer_total;

	        $cron_discount_total = "0";
	        $cron_discount_total = $discount_total;

	        $cron_payments_total = "0";
	        $cron_payments_total = $payment_total;

	        $cron_commission_total = "0";
	        $cron_commission_total = $comm_amount;

	        $payment_policy = $this->getContainer()->get('commonservices')->payment_policy($reservationID);

	        $cron_deposit1_date = date("Ymd", strtotime($payment_policy['deposit1_date']));
	        $cron_deposit2_date = date("Ymd", strtotime($payment_policy['deposit2_date']));
	        $cron_deposit3_date = date("Ymd", strtotime($payment_policy['deposit3_date']));
	        $cron_final_date = date("Ymd", strtotime($payment_policy['final_date']));

	        if ($cron_deposit2_date == "19691231") {
	        	$cron_deposit2_date = "";
	        }
	        if ($cron_deposit3_date == "19691231") {
	        	$cron_deposit3_date = "";
	        }
	        
	        $sql2 = "UPDATE `reservations` SET 
	        `cron_grand_total` = '$cron_grand_total',
	        `cron_discount_total` = '$cron_discount_total',
	        `cron_payments_total` = '$cron_payments_total',
	        `cron_commission_total` = '$cron_commission_total',
	        `cron_deposit1_date` = '$cron_deposit1_date',
	        `cron_deposit2_date` = '$cron_deposit2_date',
	        `cron_deposit3_date` = '$cron_deposit3_date',
	        `cron_final_date` = '$cron_final_date'

	        WHERE `reservationID` = '$reservationID'
	        ";

	        $result2 = $em->getConnection()->prepare($sql2);
	        $result2->execute();	
	        $output->writeln("Updating reservation $reservationID");        

        }        
    }


}