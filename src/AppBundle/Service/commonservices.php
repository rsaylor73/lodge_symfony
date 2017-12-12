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

        /*
        Note: This is MySQL 5.x so when production is updated replace the DATEDIFF age with
        TIMESTAMPDIFF(YEAR,`c`.`date_of_birth`,NOW()) AS 'age',
        */

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
            DATEDIFF(CURRENT_DATE, STR_TO_DATE(`c`.`date_of_birth`, '%d-%m-%Y'))/365 AS 'age',
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

        /*
        Note: This is MySQL 5.x so when production is updated replace the DATEDIFF age with
        TIMESTAMPDIFF(YEAR,`c`.`date_of_birth`,NOW()) AS 'age',
        */

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
            DATEDIFF(CURRENT_DATE, STR_TO_DATE(`c`.`date_of_birth`, '%d-%m-%Y'))/365 AS 'age',
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

    public function create_initial_inventory() {
        // This will be ran manually with a new lodge
        $em = $this->em;
        $container = $this->container;    

        $results = "true";

        $em = $this->getDoctrine()->getManager();

        $bed_map[0] = "A";
        $bed_map[1] = "B";
        $bed_map[2] = "C";
        $bed_map[3] = "D";

        $child_map[0] = "Child1";
        $child_map[1] = "Child2";

        $sql = "
        SELECT 
            `l`.`id`,
            DATE_FORMAT(`l`.`inventory_start_date`,'%Y%m%d') AS 'start',
            DATE_FORMAT(`l`.`inventory_stop_date`,'%Y%m%d') AS 'end',
            `r`.`beds`,
            `r`.`children`,
            `r`.`nightly_rate`,
            `r`.`id` AS 'roomID',
            `r`.`type` 

        FROM 
            `locations` l, `rooms` r

        WHERE 
            1
            AND `l`.`init` = 'Yes'
            AND `l`.`id` = `r`.`locationID`
        ";


        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {

            $start = $row['start'];
            $end = $row['end'];
            $next = "";

            while ($next != $end) {
                    $next = date("Ymd", strtotime($next . "+1 day"));

                    for($x=0; $x < $row['beds']; $x++) {
                        $sql2 = "INSERT INTO `inventory`
                        (`locationID`,`reservationID`,`contactID`,`type`,`status`,`roomID`,`typeID`,
                        `date_code`,`nightly_rate`,`bed`)
                        VALUES
                        ('$row[id]','0','0','adult','avail','$row[roomID]','$row[type]',
                        '$next','$row[nightly_rate]','".$bed_map[$x]."')
                        ";
                        $result2 = $em->getConnection()->prepare($sql2);
                        $result2->execute();                        
                    }
                    for($x=0; $x < $row['children']; $x++) {
                        $sql2 = "INSERT INTO `inventory`
                        (`locationID`,`reservationID`,`contactID`,`type`,`status`,`roomID`,`typeID`,
                        `date_code`,`nightly_rate`,`bed`)
                        VALUES
                        ('$row[id]','0','0','child','avail','$row[roomID]','$row[type]',
                        '$next','$row[nightly_rate]','".$child_map[$x]."')
                        ";  
                        $result2 = $em->getConnection()->prepare($sql2);
                        $result2->execute();                          
                    }
            }           
            $sql2 = "UPDATE `locations` SET `init` = 'No' WHERE `id` = '$row[id]'";   
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();  
        }
        return('done');
    }

    public function create_inventory($start='',$stop='') {
        // This will be ran weekly
        $em = $this->em;
        $container = $this->container;    

        $results = "true";

        $em = $this->getDoctrine()->getManager();

        $bed_map[0] = "A";
        $bed_map[1] = "B";
        $bed_map[2] = "C";
        $bed_map[3] = "D";

        $child_map[0] = "Child1";
        $child_map[1] = "Child2";

        $sql = "
        SELECT 
            `l`.`id`,
            `r`.`beds`,
            `r`.`children`,
            `r`.`nightly_rate`,
            `r`.`id` AS 'roomID',
            `r`.`type` 

        FROM 
            `locations` l, `rooms` r

        WHERE 
            1
            AND `l`.`init` = 'No'
            AND `l`.`auto_inventory` = 'On'
            AND `l`.`id` = `r`.`locationID`
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $next = $start;
            while ($next != $stop) {
                $next = date("Ymd", strtotime($next . "+1 day"));
                for($x=0; $x < $row['beds']; $x++) {
                    $sql2 = "INSERT INTO `inventory`
                    (`locationID`,`reservationID`,`contactID`,`type`,`status`,`roomID`,`typeID`,
                    `date_code`,`nightly_rate`,`bed`)
                    VALUES
                    ('$row[id]','0','0','adult','avail','$row[roomID]','$row[type]',
                    '$next','$row[nightly_rate]','".$bed_map[$x]."')
                    ";
                    $result2 = $em->getConnection()->prepare($sql2);
                    $result2->execute();                        
                }
                for($x=0; $x < $row['children']; $x++) {
                    $sql2 = "INSERT INTO `inventory`
                    (`locationID`,`reservationID`,`contactID`,`type`,`status`,`roomID`,`typeID`,
                    `date_code`,`nightly_rate`,`bed`)
                    VALUES
                    ('$row[id]','0','0','child','avail','$row[roomID]','$row[type]',
                    '$next','$row[nightly_rate]','".$child_map[$x]."')
                    ";  
                    $result2 = $em->getConnection()->prepare($sql2);
                    $result2->execute();                          
                }
            }            
        }
        return($result);
    }    

}