<?php
/* This is a service class */
namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use AppBundle\Entity\User;

class reservationdetails extends Controller
{
    
    protected $em;
    protected $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;

    }

    public function getresdetails($reservationID) {
    	$em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
        `r`.`status`,
        `r`.`pax`,
        `r`.`children`,
        `r`.`nights`,
        `r`.`manual_commission_override`,
        DATE_FORMAT(`r`.`checkin_date`, '%m/%d/%Y') AS 'checkin_date',
        DATE_FORMAT(
            DATE_ADD(`r`.`checkin_date`, INTERVAL `r`.`nights` DAY),
            '%m/%d/%Y'
        ) AS 'checkout_date',
        `c`.`first`,
        `c`.`middle`,
        `c`.`last`,
        `c`.`email`,
        `c`.`address1`,
        `c`.`address2`,
        `c`.`city`,
        `c`.`state`,
        `c`.`province`,
        `c`.`zip`,
        `c`.`contactID`,
        `ct`.`country`,
        DATE_FORMAT(`r`.`checkin_date`, '%M %e, %Y') AS 'checkin_date_invoice',
        DATE_FORMAT(
            DATE_ADD(`r`.`checkin_date`, INTERVAL `r`.`nights` DAY),
            '%M %e, %Y'
        ) AS 'checkout_date_invoice',
        DATE_FORMAT(`r`.`date_booked`, '%M %e, %Y') AS 'date_booked_invoice',
        `l`.`name` AS 'lodge_name',
        `l`.`location` AS 'lodge_location'


        FROM
            `reservations` r
        LEFT JOIN `$AF_DB`.`contacts` c ON `r`.`contactID` = `c`.`contactID`
        LEFT JOIN `$AF_DB`.`countries` ct ON `c`.`countryID` = `ct`.`countryID`
        LEFT JOIN `locations` l ON `r`.`locationID` = `l`.`id`
        WHERE
            `r`.`reservationID` = '$reservationID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $details = "";
        while ($row = $result->fetch()) {
        	foreach ($row as $key=>$value) {
        		$details[$key] = $value;
        	}
        } 
        return($details);   	
    }

    public function transfer_amount($nights) {
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

    public function payment_history($reservationID) {
        $em = $this->em;

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

    public function discount_history($reservationID) {
        $em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

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

    public function tenttotal($reservationID) {
        $em = $this->em;
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
