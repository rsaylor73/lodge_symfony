<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class ResellerController extends Controller
{

    /**
     * @Route("/assignreseller", name="assignreseller") 
     */
    public function assignresellerAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

    	$em = $this->getDoctrine()->getManager();
    	$AF_DB = $this->container->getParameter('AF_DB');

    	$reservationID = $request->query->get('reservationID');

        return $this->render('resellers/assignreseller.html.twig',[
        	'reservationID' => $reservationID,
        ]); 
    }

    /**
     * @Route("/searchreseller", name="searchreseller") 
     */
    public function searchresellerAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

		$em = $this->getDoctrine()->getManager();
		$AF_DB = $this->container->getParameter('AF_DB');    

        $data = "";
		$reservationID = $request->query->get('reservationID');
		
        $company = $request->query->get('company');
        $resellerID = $request->query->get('resellerID');
        $sql_pre = "";

        if ($resellerID != "") {
            $sql_pre = "AND `r`.`resellerID` = '$resellerID'";
        } else {
            $sql_pre = "AND `r`.`company` LIKE '%$company%'";
        }

        $sql = "
        SELECT
            `r`.`resellerID`,
            `r`.`company`
        FROM
            `$AF_DB`.`resellers` r

        WHERE
            1
            $sql_pre
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
		// change
        return $this->render('resellers/searchreseller.html.twig',[
        	'reservationID' => $reservationID,
            'data' => $data,
        ]);
	}

    /**
     * @Route("/addresellertores", name="addresellertores") 
     */
    public function addresellertoresAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reservationID = $request->request->get('reservationID');
        $resellerID = $request->request->get('resellerID');

        $sql = "UPDATE `reservations` SET `resellerID` = '$resellerID', `resellerAgentID` = '' 
        WHERE `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $this->addFlash('info','The reseller was updated.');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
        ]);       
    }

}