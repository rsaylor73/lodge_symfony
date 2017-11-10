<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class TestController extends Controller
{
    /**
     * @Route("/test", name="test")
     */
    public function testAction()
    {
    	// This creates the inventory
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

        return $this->render('test/test.html.twig',[
        	'results' => $results,
        ]);

    }





}