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
    	$em = $this->getDoctrine()->getManager();

        return $this->render('contacts/assigncontactsearch.html.twig',[
        	'reservationID' => $reservationID,
        	'bunk' => $bunk,
        	'roomID' => $roomID,
        ]); 
    }

    /**
     * @Route("/searchcontact", name="searchcontact") 
     */
    public function searchcontactAction(Request $request)
    {
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
     * @Route("/addpaxtores", name="addpaxtores") 
     */
    public function addpaxtoresAction(Request $request)
    {
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
        }
        $this->addFlash('info','The guest was updated.');
        return $this->redirectToRoute('viewreservationguest',[
        	'reservationID' => $reservationID,
        ]);

	}

    /**
     * @Route("/deletepaxtores", name="deletepaxtores") 
     */
    public function deletepaxtoresAction(Request $request)
    {
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
        }
        $this->addFlash('info','The guest was removed.');
        return $this->redirectToRoute('viewreservationguest',[
        	'reservationID' => $reservationID,
        ]);		
    }

}   	
