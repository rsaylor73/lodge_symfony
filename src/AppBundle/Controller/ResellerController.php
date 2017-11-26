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


    /**
     * @Route("/listresellers", name="listresellers") 
     */
    public function listresellersAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $company = $request->request->get('company');
        $status = $request->request->get('status');
        $city = $request->request->get('city');

        $company_sql = "";
        $status_sql = "";
        $city_sql = "";

        if ($company != "") {
            $company_sql = "AND `r`.`company` LIKE '%$company%'";
        }

        if ($city != "") {
            $city_sql = "AND `r`.`city` LIKE '%$city%'";
        }

        if ($status != "") {
            $status_sql = "AND `r`.`status` = '$status'";
        } else {
            $status_sql = "AND `r`.`status` = 'Active'";
        }

        $sql = "
        SELECT
            `r`.`resellerID`,
            `rt`.`type`,
            `r`.`status`,
            `r`.`commission`,
            `r`.`company`,
            `r`.`city`,
            `cn`.`country`,
            DATE_FORMAT(`r`.`created`,'%m/%d/%Y') AS 'created_date'

        FROM
            `$AF_DB`.`resellers` r

        LEFT JOIN `$AF_DB`.`reseller_types` rt ON 
            `r`.`reseller_typeID` = `rt`.`reseller_typeID`
        LEFT JOIN `$AF_DB`.`countries` cn ON
            `r`.`countryID` = `cn`.`countryID`

        WHERE
            1
            $status_sql
            $company_sql
            $city_sql

        ORDER BY `r`.`created` DESC, `r`.`company` ASC

        LIMIT 50
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "";
        $data = "";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
        }

        return $this->render('resellers/listresellers.html.twig',[
            'data' => $data,
        ]);
    }

}