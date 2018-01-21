<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class ContactsController extends Controller
{
    /**
     * @Route("/assigncontact", name="assigncontact") 
     * @Route("/assigncontact/{reservationID}/{bunk}/{roomID}")
     */
    public function assigncontactAction(Request $request, $reservationID='',$bunk='',$roomID='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

    	$em = $this->getDoctrine()->getManager();

        return $this->render('contacts/assigncontactsearch.html.twig',[
        	'reservationID' => $reservationID,
        	'bunk' => $bunk,
        	'roomID' => $roomID,
        ]); 
    }

    /**
     * @Route("/assignreservationcontact", name="assignreservationcontact") 
     */
    public function assignreservationcontactAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

    	$em = $this->getDoctrine()->getManager();
    	$reservationID = $request->query->get('reservationID');

        return $this->render('contacts/assignreservationcontact.html.twig',[
        	'reservationID' => $reservationID,
        ]); 
    }

    /**
     * @Route("/searchcontact", name="searchcontact") 
     */
    public function searchcontactAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');

		$reservationID = $request->query->get('reservationID');
		$bunk = $request->query->get('bunk');
		$roomID = $request->query->get('roomID');

		$first = $request->query->get('first');
		$middle = $request->query->get('middle');
		$last = $request->query->get('last');
		$dob = $request->query->get('dob');
		$zip = $request->query->get('zip');
		$email = $request->query->get('email');

		if ($dob != "") {
			$dob = date("Ymd", strtotime($dob));
		}

		$sql = "
		SELECT
			`c`.`contactID`,
			`c`.`first`,
			`c`.`middle`,
			`c`.`last`,
			DATE_FORMAT(`c`.`date_of_birth`, '%m/%d/%Y') AS 'dob',
			`c`.`city`,
			`c`.`zip`,
			`ct`.`country`

		FROM
			`$AF_DB`.`contacts` c

		LEFT JOIN `$AF_DB`.`countries` ct ON `c`.`countryID` = `ct`.`countryID`

		WHERE
			1
			AND `c`.`first` LIKE '%$first%'
			AND `c`.`middle` LIKE '%$middle%'
			AND `c`.`last` LIKE '%$last%'
			AND `c`.`date_of_birth` LIKE '%$dob%'
			AND `c`.`zip` LIKE '%$zip%'
			AND `c`.`email` LIKE '%$email%'

		LIMIT 50
		";
        $i = "0";
        $data = "";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
        	foreach ($row as $key=>$value) {
				$data[$i][$key] = $value;
			}
			$i++;
		}
        return $this->render('contacts/searchcontact.html.twig',[
        	'data' => $data,
        	'reservationID' => $reservationID,
        	'bunk' => $bunk,
        	'roomID' => $roomID,
        ]);
    }

    /**
     * @Route("/searchrescontact", name="searchrescontact") 
     */
    public function searchrescontactAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');

		$reservationID = $request->query->get('reservationID');

		$first = $request->query->get('first');
		$middle = $request->query->get('middle');
		$last = $request->query->get('last');
		$dob = $request->query->get('dob');
		$zip = $request->query->get('zip');
		$email = $request->query->get('email');

		if ($dob != "") {
			$dob = date("Ymd", strtotime($dob));
		}

		$sql = "
		SELECT
			`c`.`contactID`,
			`c`.`first`,
			`c`.`middle`,
			`c`.`last`,
			DATE_FORMAT(`c`.`date_of_birth`, '%m/%d/%Y') AS 'dob',
			`c`.`city`,
			`c`.`zip`,
			`ct`.`country`

		FROM
			`$AF_DB`.`contacts` c

		LEFT JOIN `$AF_DB`.`countries` ct ON `c`.`countryID` = `ct`.`countryID`

		WHERE
			1
			AND `c`.`first` LIKE '%$first%'
			AND `c`.`middle` LIKE '%$middle%'
			AND `c`.`last` LIKE '%$last%'
			AND `c`.`date_of_birth` LIKE '%$dob%'
			AND `c`.`zip` LIKE '%$zip%'
			AND `c`.`email` LIKE '%$email%'

		LIMIT 50
		";
        $i = "0";
        $data = "";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
        	foreach ($row as $key=>$value) {
				$data[$i][$key] = $value;
			}
			$i++;
		}
        return $this->render('contacts/searchrescontact.html.twig',[
        	'data' => $data,
        	'reservationID' => $reservationID,

        ]);
    }

    /**
     * @Route("/addpaxtores", name="addpaxtores") 
     */
    public function addpaxtoresAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');

		// POST DATA (use query for GET and request for POST)
		$reservationID = $request->request->get('reservationID');
		$bunk = $request->request->get('bunk'); // bed - just like using the term bunk more like AF
		$roomID = $request->request->get('roomID');
		$contactID = $request->request->get('contactID');

		$sql = "
		SELECT
			`i`.`inventoryID`
			
		FROM
			`inventory` i

		WHERE
			`i`.`reservationID` = '$reservationID'
			AND `i`.`roomID` = '$roomID'
			AND `i`.`bed` = '$bunk'
		";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
        	$sql2 = "UPDATE `inventory` SET `contactID` = '$contactID' WHERE `inventoryID` = '$row[inventoryID]'";
        	$result2 = $em->getConnection()->prepare($sql2);
        	$result2->execute();

            // clear GIS
            $sql2 = "DELETE FROM `gis` WHERE `reservationID` = '$reservationID' AND `inventoryID` = '$row[inventoryID]'";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();            
        }

        $this->addFlash('info','The guest was updated.');
        return $this->redirectToRoute('viewreservationguest',[
        	'reservationID' => $reservationID,
        ]);

	}

    /**
     * @Route("/addrespaxtores", name="addrespaxtores") 
     */
    public function addrespaxtoresAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');

		// POST DATA (use query for GET and request for POST)
		$reservationID = $request->request->get('reservationID');
		$contactID = $request->request->get('contactID');

    	$sql = "UPDATE `reservations` SET `contactID` = '$contactID' WHERE `reservationID` = '$reservationID'";
    	$result = $em->getConnection()->prepare($sql);
    	$result->execute();
        $this->addFlash('info','The reservation contact was updated.');
        return $this->redirectToRoute('viewreservation',[
        	'reservationID' => $reservationID,
        ]);

	}
    /**
     * @Route("/deletepaxtores", name="deletepaxtores") 
     */
    public function deletepaxtoresAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');

		// POST DATA (use query for GET and request for POST)
		$reservationID = $request->request->get('reservationID');
		$bunk = $request->request->get('bunk'); // bed - just like using the term bunk more like AF
		$roomID = $request->request->get('roomID');

		$sql = "
		SELECT
			`i`.`inventoryID`
			
		FROM
			`inventory` i

		WHERE
			`i`.`reservationID` = '$reservationID'
			AND `i`.`roomID` = '$roomID'
			AND `i`.`bed` = '$bunk'
		";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
        	$sql2 = "UPDATE `inventory` SET `contactID` = '0' WHERE `inventoryID` = '$row[inventoryID]'";
        	$result2 = $em->getConnection()->prepare($sql2);
        	$result2->execute();

            // clear GIS
            $sql2 = "DELETE FROM `gis` WHERE `reservationID` = '$reservationID' AND `inventoryID` = '$row[inventoryID]'";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();  

        }
        $this->addFlash('info','The guest was removed.');
        return $this->redirectToRoute('viewreservationguest',[
        	'reservationID' => $reservationID,
        ]);		
    }

    /**
     * @Route("/listcontacts", name="listcontacts") 
     */
    public function listcontactsAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $sql = "
        SELECT
            `c`.`contactID`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`city`,
            `c`.`state`,
            `c`.`province`,
            DATE_FORMAT(`c`.`date_created`, '%m/%d/%Y') AS 'created_date',
            `cn`.`country`

        FROM
            `$AF_DB`.`contacts` c

        LEFT JOIN `$AF_DB`.`countries` cn ON `c`.`countryID` = `cn`.`countryID`

        WHERE
            1

        ORDER BY `c`.`date_created` DESC

        LIMIT 50
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $data = "";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        }

        return $this->render('contacts/listcontacts.html.twig',[
            'data' => $data,
        ]);
    }

    /**
     * @Route("/newcontact", name="newcontact") 
     */
    public function newcontactAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $route = $request->query->get('route');
        $reservationID = $request->query->get('reservationID');

        // get states
        $sql2 = "SELECT `state_abbr` FROM `$AF_DB`.`state` ORDER BY `state_abbr` ASC";
        $result2 = $em->getConnection()->prepare($sql2);
        $result2->execute();
        while ($row2 = $result2->fetch()) {
            $state[] = $row2['state_abbr'];
        }
        // get countries
        $i2 = "0";
        $sql2 = "SELECT `countryID`,`country` FROM `$AF_DB`.`countries` ORDER BY `country` ASC";
        $result2 = $em->getConnection()->prepare($sql2);
        $result2->execute();
        while ($row2 = $result2->fetch()) {
            foreach($row2 as $key2=>$value2) {
                $country[$i2][$key2] = $value2;
            }
            $i2++;
        }

        return $this->render('contacts/newcontact.html.twig',[
            'state' => $state,
            'country' => $country,
            'route' => $route,
            'reservationID' => $reservationID,
        ]);
    }        

    /**
     * @Route("/editcontacts", name="editcontacts") 
     */
    public function editcontactsAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $contactID = $request->request->get('contactID');
        if ($contactID == "") {
            $contactID = $request->query->get('contactID');
        }

        $sql = "
        SELECT
            `c`.`contactID`,
            `c`.`title`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `c`.`address1`,
            `c`.`address2`,
            `c`.`city`,
            `c`.`state`,
            `c`.`province`,
            `c`.`zip`,
            `c`.`email`,
            `c`.`phone1`,
            `c`.`phone1_type`,
            `c`.`phone2`,
            `c`.`phone2_type`,
            `c`.`phone3`,
            `c`.`phone3_type`,
            `c`.`phone4`,
            `c`.`phone4_type`,
            DATE_FORMAT(`c`.`date_of_birth`,'%Y-%m-%d') AS 'date_of_birth',
            `c`.`countryID`

        FROM
            `$AF_DB`.`contacts` c


        WHERE
            `c`.`contactID` = '$contactID'

        LIMIT 1
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $data = "";
        $state = "";
        $country = "";
        $i2 = "";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $data[$key] = $value;
            }
            // get states
            $sql2 = "SELECT `state_abbr` FROM `$AF_DB`.`state` ORDER BY `state_abbr` ASC";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();
            while ($row2 = $result2->fetch()) {
                $state[] = $row2['state_abbr'];
            }
            // get countries
            $sql2 = "SELECT `countryID`,`country` FROM `$AF_DB`.`countries` ORDER BY `country` ASC";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();
            while ($row2 = $result2->fetch()) {
                foreach($row2 as $key2=>$value2) {
                    $country[$i2][$key2] = $value2;
                }
                $i2++;
            }

        }

        return $this->render('contacts/editcontacts.html.twig',[
            'data' => $data,
            'state' => $state,
            'country' => $country,
        ]);
    }

    /**
     * @Route("/updatecontact", name="updatecontact") 
     */
    public function updatecontactAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');   

        $first = $request->request->get('first');
        $middle = $request->request->get('middle');
        $last = $request->request->get('last');
        $address1 = $request->request->get('address1');
        $address2 = $request->request->get('address2');
        $city = $request->request->get('city');
        $state = $request->request->get('state');
        $province = $request->request->get('province');
        $countryID = $request->request->get('countryID');
        $email = $request->request->get('email');
        $date_of_birth = date("Ymd", strtotime($request->request->get('date_of_birth')));
        $phone1_type = $request->request->get('phone1_type');
        $phone2_type = $request->request->get('phone2_type');
        $phone3_type = $request->request->get('phone3_type');
        $phone4_type = $request->request->get('phone4_type');
        $phone1 = $request->request->get('phone1');
        $phone2 = $request->request->get('phone2');
        $phone3 = $request->request->get('phone3');
        $phone4 = $request->request->get('phone4');
        $contactID = $request->request->get('contactID');

        $sql = "
        UPDATE `$AF_DB`.`contacts` SET 
        `first` = ?,
        `middle` = ?,
        `last` = ?,
        `address1` = ?,
        `address2` = ?,
        `city` = '$city',
        `state` = '$state',
        `province` = ?,
        `countryID` = '$countryID',
        `date_of_birth` = '$date_of_birth',
        `email` = '$email',
        `phone1_type` = '$phone1_type',
        `phone2_type` = '$phone2_type',
        `phone3_type` = '$phone3_type',
        `phone4_type` = '$phone4_type',
        `phone1` = '$phone1',
        `phone2` = '$phone2',
        `phone3` = '$phone3',
        `phone4` = '$phone4'
        WHERE `contactID` = '$contactID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $first);
        $result->bindValue(2, $middle);
        $result->bindValue(3, $last);
        $result->bindValue(4, $address1);
        $result->bindValue(5, $address2);
        $result->bindValue(6, $province);
        $result->execute();
        
        $this->addFlash('success','The contact was updated');
        return $this->redirectToRoute('listcontacts'); 
    }

    /**
     * @Route("/savecontact", name="savecontact") 
     */
    public function savecontactAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('contacts');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */
        
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');   
        $route = $request->request->get('route');
        $reservationID = $request->request->get('reservationID');

        $first = $request->request->get('first');
        $middle = $request->request->get('middle');
        $last = $request->request->get('last');
        $address1 = $request->request->get('address1');
        $address2 = $request->request->get('address2');
        $city = $request->request->get('city');
        $state = $request->request->get('state');
        $province = $request->request->get('province');
        $countryID = $request->request->get('countryID');
        $zip = $request->request->get('zip');
        $email = $request->request->get('email');
        $date_of_birth = date("Ymd", strtotime($request->request->get('date_of_birth')));
        $phone1_type = $request->request->get('phone1_type');
        $phone2_type = $request->request->get('phone2_type');
        $phone3_type = $request->request->get('phone3_type');
        $phone4_type = $request->request->get('phone4_type');
        $phone1 = $request->request->get('phone1');
        $phone2 = $request->request->get('phone2');
        $phone3 = $request->request->get('phone3');
        $phone4 = $request->request->get('phone4');
        $date_created = date("Ymd");

        $sql = "INSERT INTO `$AF_DB`.`contacts`
        (
            `first`,`middle`,`last`,`address1`,`address2`,`city`,`state`,`province`,
            `countryID`,`email`,`date_of_birth`,`phone1_type`,`phone2_type`,
            `phone3_type`,`phone4_type`,`phone1`,`phone2`,`phone3`,`phone4`,
            `date_created`,`zip`
        ) VALUES (
            ?,?,?,?,?,'$city','$state',?,
            '$countryID','$email','$date_of_birth','$phone1_type','$phone2_type',
            '$phone3_type','$phone4_type','$phone1','$phone2','$phone3','$phone4',
            '$date_created','$zip'
        )
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $first);
        $result->bindValue(2, $middle);
        $result->bindValue(3, $last);
        $result->bindValue(4, $address1);
        $result->bindValue(5, $address2);
        $result->bindValue(6, $province);
        $result->execute();

        $this->addFlash('success','The contact was added');
        return $this->redirectToRoute($route,[
            'reservationID' => $reservationID,
        ]);

    }

}   	
