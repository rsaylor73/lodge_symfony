<?php
/* This is a service class */
namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use AppBundle\Entity\User;

class commonservices extends Controller
{
    
    protected $em;
    protected $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;

    }

    public function get_states() {
    	$em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
        	`s`.`state`,
        	`s`.`state_abbr`

        FROM
        	`$AF_DB`.`state` s

        WHERE
        	1

        ORDER BY `state` ASC
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $state = "";
        $i = "0";
        while ($row = $result->fetch()) {
        	foreach ($row as $key=>$value) {
        		$state[$i][$key] = $value;
        	}
            $i++;
        } 
        return($state);          
    }


    public function get_country() {
        $em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
            `c`.`countryID`,
            `c`.`country`

        FROM
            `$AF_DB`.`countries` c

        WHERE
            1

        ORDER BY `c`.`country` ASC
        ";  
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $country = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $country[$i][$key] = $value;
            }
            $i++;
        } 
        return($country);               
    }

    public function get_resellertype() {
        $em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
            `r`.`reseller_typeID`,
            `r`.`type`,
            `r`.`status`

        FROM
            `$AF_DB`.`reseller_types` r

        WHERE
            1

        ORDER BY `r`.`type` ASC
        ";  
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $type = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $type[$i][$key] = $value;
            }
            $i++;
        } 
        return($type);              
    }

    public function checkin_report_query() {
        $container = $this->container;
        $AF_DB = $container->getParameter('AF_DB');
        
        $today = date("Y-m-d");
        $date_code = date("Ymd");

        $sql = "
        SELECT
            `r`.`reservationID`,
            `r`.`nights`,
            `c`.`contactID`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`,
            `c`.`sex`,
            `c`.`passport_number`,
            DATE_FORMAT(`c`.`date_of_birth`, '%m/%d/%Y') AS 'dob',
            TIMESTAMPDIFF(YEAR,`c`.`date_of_birth`,NOW()) AS 'age',
            `c`.`emergency_name`,
            `c`.`emergency_phone`,
            `c`.`emergency_address_city_state_zip`,
            `c`.`special_passenger_details`,
            `ct`.`country` AS 'passport_nationality',
            `rm`.`description`,
            `i`.`bed`            

        FROM
            `reservations` r

        LEFT JOIN `inventory` i ON `r`.`reservationID` = `i`.`reservationID` AND `i`.`date_code` = '$date_code'

        LEFT JOIN `$AF_DB`.`contacts` c ON `i`.`contactID` = `c`.`contactID`
        LEFT JOIN `$AF_DB`.`countries` ct ON `c`.`nationality_countryID` = `ct`.`countryID`

        LEFT JOIN `rooms` rm ON `i`.`roomID` = `rm`.`id`

        WHERE
            `r`.`checkin_date` = '$today'
            AND `r`.`status` = 'Active'

        ";
        return($sql);        
    }

    public function checkout_report_query() {

        $container = $this->container;
        $AF_DB = $container->getParameter('AF_DB');
        
        $today = date("Y-m-d");
        $date_code = date("Ymd");
        $prior_date = date("Ymd", strtotime($date_code . "-1 DAY"));

        $sql = "
        SELECT
            `r`.`reservationID`,
            `r`.`nights`,
            `c`.`contactID`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`email`,
            `c`.`sex`,
            `c`.`passport_number`,
            DATE_FORMAT(`c`.`date_of_birth`, '%m/%d/%Y') AS 'dob',
            TIMESTAMPDIFF(YEAR,`c`.`date_of_birth`,NOW()) AS 'age',
            `c`.`emergency_name`,
            `c`.`emergency_phone`,
            `c`.`emergency_address_city_state_zip`,
            `c`.`special_passenger_details`,
            `ct`.`country` AS 'passport_nationality',
            `rm`.`description`,
            `i`.`bed`            

        FROM
            `reservations` r

        LEFT JOIN `inventory` i ON `r`.`reservationID` = `i`.`reservationID` AND `i`.`date_code` = '$prior_date'

        LEFT JOIN `$AF_DB`.`contacts` c ON `i`.`contactID` = `c`.`contactID`
        LEFT JOIN `$AF_DB`.`countries` ct ON `c`.`nationality_countryID` = `ct`.`countryID`

        LEFT JOIN `rooms` rm ON `i`.`roomID` = `rm`.`id`

        WHERE
            DATE_FORMAT(DATE_ADD(`r`.`checkin_date`, INTERVAL `r`.`nights` DAY),'%Y%m%d') = '$date_code'

        ";
        return($sql);
    }

}