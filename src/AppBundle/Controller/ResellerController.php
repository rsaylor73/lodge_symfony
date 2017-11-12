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
        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        // TO DO
        return $this->render('resellers/searchreseller.html.twig',[
            'reservationID' => $reservationID,
            'data' => $data,
        ]);        
    }

}