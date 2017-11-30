<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class ResellerAgentController extends Controller
{

    /**
     * @Route("/assignagent", name="assignagent") 
     */
    public function assignagentAction(Request $request)
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

    	$sql = "
		SELECT
			`a`.`reseller_agentID`,
			`a`.`resellerID`,
			`a`.`first`,
			`a`.`last`,
			`a`.`waiver`

		FROM
			`reservations` r, `$AF_DB`.`reseller_agents` a

		WHERE
			`r`.`reservationID` = '$reservationID'
			AND `r`.`resellerID` = `a`.`resellerID`
			AND `a`.`status` = 'Active'
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

        return $this->render('agents/assignagent.html.twig',[
        	'reservationID' => $reservationID,
        	'data' => $data,
        ]); 
    } 

    /**
     * @Route("/selectagent", name="selectagent") 
     */
    public function selectagentAction(Request $request)
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
        $reseller_agentID = $request->request->get('reseller_agentID');

        $sql = "UPDATE `reservations` SET `resellerAgentID` = '$reseller_agentID'  
        WHERE `reservationID` = '$reservationID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $this->addFlash('info','The agent was updated.');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
        ]);  

    }

    /**
     * @Route("/listagents", name="listagents") 
     */
    public function listagentsAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $resellerID = $request->request->get('resellerID');

        $sql = "SELECT `company` FROM `$AF_DB`.`resellers` WHERE `resellerID` = '$resellerID'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $company = "";
        while ($row = $result->fetch()) {
            $company = $row['company'];
        }        

        $sql = "
        SELECT
            `a`.`reseller_agentID`,
            `a`.`resellerID`,
            `a`.`status`,
            `a`.`title`,
            `a`.`first`,
            `a`.`middle`,
            `a`.`last`,
            `a`.`address1`,
            `a`.`address2`,
            `a`.`city`,
            `a`.`state`,
            `a`.`zip`,
            `a`.`countryID`,
            `a`.`phone1`,
            `a`.`phone2`,
            `a`.`phone3`,
            `a`.`phone4`,
            `a`.`phone1_type`,
            `a`.`phone2_type`,
            `a`.`phone3_type`,
            `a`.`phone4_type`,
            `a`.`email`,
            `a`.`waiver`,
            `cn`.`country`

        FROM
            `$AF_DB`.`reseller_agents` a

        LEFT JOIN `$AF_DB`.`countries` cn ON
            `a`.`countryID` = `cn`.`countryID`

        WHERE
            `a`.`resellerID` = '$resellerID'

        ORDER BY `status` ASC, `last` ASC, `first` ASC
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

        return $this->render('agents/listagents.html.twig',[
            'data' => $data,
            'company' => $company,
        ]);
    }

    /**
     * @Route("/editagent", name="editagent") 
     */
    public function editagentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $states = $this->get('commonservices')->get_states();
        $countries = $this->get('commonservices')->get_country();

        $reseller_agentID = $request->request->get('reseller_agentID');

        $sql = "
        SELECT
            `a`.`reseller_agentID`,
            `a`.`resellerID`,
            `a`.`status`,
            `a`.`title`,
            `a`.`first`,
            `a`.`middle`,
            `a`.`last`,
            `a`.`address1`,
            `a`.`address2`,
            `a`.`city`,
            `a`.`state`,
            `a`.`province`,
            `a`.`zip`,
            `a`.`countryID`,
            `a`.`phone1`,
            `a`.`phone2`,
            `a`.`phone3`,
            `a`.`phone4`,
            `a`.`phone1_type`,
            `a`.`phone2_type`,
            `a`.`phone3_type`,
            `a`.`phone4_type`,
            `a`.`email`,
            `a`.`waiver`,
            `cn`.`country`

        FROM
            `$AF_DB`.`reseller_agents` a

        LEFT JOIN `$AF_DB`.`countries` cn ON
            `a`.`countryID` = `cn`.`countryID`

        WHERE
            `a`.`reseller_agentID` = '$reseller_agentID'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $data = "";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $data[$key] = $value;
            }
        }

        return $this->render('agents/editagent.html.twig',[
            'data' => $data,
            'states' => $states,
            'countries' => $countries,
        ]);
    }

    /**
     * @Route("/updateagent", name="updateagent") 
     */
    public function updateagentAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $resellerID = $request->request->get('resellerID');  
        $reseller_agentID = $request->request->get('reseller_agentID');
        $first = $request->request->get('first');
        $middle = $request->request->get('middle');
        $last = $request->request->get('last');
        $address1 = $request->request->get('address1');
        $address2 = $request->request->get('address2');
        $city = $request->request->get('city');
        $state = $request->request->get('state');
        $province = $request->request->get('province');
        $zip = $request->request->get('zip');
        $countryID = $request->request->get('countryID');
        $phone1 = $request->request->get('phone1');
        $phone2 = $request->request->get('phone2');
        $phone3 = $request->request->get('phone3');
        $phone4 = $request->request->get('phone4');
        $phone1_type = $request->request->get('phone1_type');
        $phone2_type = $request->request->get('phone2_type');
        $phone3_type = $request->request->get('phone3_type');
        $phone4_type = $request->request->get('phone4_type');
        $email = $request->request->get('email');

        $sql = "
        UPDATE `$AF_DB`.`reseller_agents` SET
        `first` = ?,
        `middle` = ?,
        `last` = ?,
        `address1` = '$address1',
        `address2` = '$address2',
        `city` = '$city',
        `state` = '$state',
        `province` = '$province',
        `zip` = '$zip',
        `countryID` = '$countryID',
        `phone1` = '$phone1',
        `phone2` = '$phone2',
        `phone3` = '$phone3',
        `phone4` = '$phone4',
        `phone1_type` = '$phone1_type',
        `phone2_type` = '$phone2_type',
        `phone3_type` = '$phone3_type',
        `phone4_type` = '$phone4_type',
        `email` = '$email'
        WHERE `reseller_agentID` = '$reseller_agentID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $first);
        $result->bindValue(2, $middle);
        $result->bindValue(3, $last);
        $result->execute(); 
        $this->addFlash('success','The agent was updated');
        return $this->redirectToRoute('listresellers');         
    }

}   	