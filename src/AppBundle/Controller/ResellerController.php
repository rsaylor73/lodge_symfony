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
            `r`.`company`,
            `t`.`type`
        FROM
            `$AF_DB`.`resellers` r, `$AF_DB`.`reseller_types` t

        WHERE
            1
            AND `r`.`reseller_typeID` = `t`.`reseller_typeID`
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

    /**
     * @Route("/addreseller", name="addreseller") 
     */
    public function addresellerAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $states = $this->get('commonservices')->get_states();
        $countries = $this->get('commonservices')->get_country();
        $resellertypes = $this->get('commonservices')->get_resellertype();

        return $this->render('resellers/addreseller.html.twig',[
            'states' => $states,
            'countries' => $countries,
            'resellertypes' => $resellertypes,
        ]);
    }                

    /**
     * @Route("/editreseller", name="editreseller") 
     */
    public function editresellerAction(Request $request)
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
        $states = $this->get('commonservices')->get_states();
        $countries = $this->get('commonservices')->get_country();
        $resellertypes = $this->get('commonservices')->get_resellertype();

        $sql = "
        SELECT
            `r`.`resellerID`,
            `r`.`reseller_typeID`,
            `r`.`status`,
            `r`.`commission`,
            `r`.`company`,
            `r`.`first`,
            `r`.`middle`,
            `r`.`last`,
            `r`.`address`,
            `r`.`city`,
            `r`.`state`,
            `r`.`zip`,
            `r`.`countryID`,
            `r`.`email`,
            `r`.`url`,
            `r`.`phone`,
            `r`.`phone2`,
            `r`.`province`

        FROM
            `$AF_DB`.`resellers` r

        WHERE
            `r`.`resellerID` = '$resellerID'
        ";


        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $company = ""; $reseller_typeID = "";
        $first = ""; $middle = ""; $last = "";
        $address = ""; $state = ""; $province = "";
        $countryID = ""; $city = ""; $email = "";
        $url = ""; $phone = ""; $phone2 = "";
        $status = ""; $commission = ""; $zip = "";

        while ($row = $result->fetch()) {
            $company = $row['company'];
            $reseller_typeID = $row['reseller_typeID'];
            $first = $row['first'];
            $middle = $row['middle'];
            $last = $row['last'];
            $address = $row['address'];
            $city = $row['city'];
            $state = $row['state'];
            $province = $row['province'];
            $countryID = $row['countryID'];
            $email = $row['email'];
            $url = $row['url'];
            $phone = $row['phone'];
            $phone2 = $row['phone2'];
            $status = $row['status'];
            $commission = $row['commission'];
            $zip = $row['zip'];

        }
        return $this->render('resellers/editreseller.html.twig',[
            'company' => $company,
            'resellerID' => $resellerID,
            'reseller_typeID' => $reseller_typeID,
            'first' => $first,
            'middle' => $middle,
            'last' => $last,
            'address' => $address,
            'city' => $city,
            'state' => $state,
            'province' => $province,
            'countryID' => $countryID,
            'states' => $states,
            'countries' => $countries,
            'email' => $email,
            'url' => $url,
            'phone' => $phone,
            'phone2' => $phone2,
            'resellertypes' => $resellertypes,
            'status' => $status,
            'zip' => $zip,
            'commission' => $commission,
        ]);
    }

    /**
     * @Route("/updatereseller", name="updatereseller") 
     */
    public function updateresellerAction(Request $request)
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
        $reseller_typeID = $request->request->get('reseller_typeID');
        $company = $request->request->get('company');
        $first = $request->request->get('first');
        $middle = $request->request->get('middle');
        $last = $request->request->get('last');
        $email = $request->request->get('email');
        $address = $request->request->get('address');
        $city = $request->request->get('city');
        $state = $request->request->get('state');
        $province = $request->request->get('province');
        $countryID = $request->request->get('countryID');
        $url = $request->request->get('url');
        $phone = $request->request->get('phone');
        $phone2 = $request->request->get('phone2');
        $status = $request->request->get('status');
        $commission = $request->request->get('commission');
        $zip = $request->request->get('zip');

        $sql = "UPDATE `$AF_DB`.`resellers` SET
        `reseller_typeID` = '$reseller_typeID',
        `company` = ?,
        `first` = '$first',
        `middle` = '$middle',
        `last` = '$last',
        `email` = '$email',
        `address` = '$address',
        `city` = '$city',
        `state` = '$state',
        `province` = '$province',
        `countryID` = '$countryID',
        `url` = '$url',
        `phone` = '$phone',
        `phone2` = '$phone2',
        `status` = '$status',
        `zip` = '$zip',
        `commission` = '$commission'
        WHERE `resellerID` = '$resellerID'
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $company);
        $result->execute(); 

        $this->addFlash('success','The reseller was updated');
        return $this->redirectToRoute('listresellers'); 
    }

    /**
     * @Route("/savereseller", name="savereseller") 
     */
    public function saveresellerAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('resellers');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $reseller_typeID = $request->request->get('reseller_typeID');
        $company = $request->request->get('company');
        $first = $request->request->get('first');
        $middle = $request->request->get('middle');
        $last = $request->request->get('last');
        $email = $request->request->get('email');
        $address = $request->request->get('address');
        $city = $request->request->get('city');
        $state = $request->request->get('state');
        $province = $request->request->get('province');
        $countryID = $request->request->get('countryID');
        $url = $request->request->get('url');
        $phone = $request->request->get('phone');
        $phone2 = $request->request->get('phone2');
        $status = $request->request->get('status');
        $commission = $request->request->get('commission');
        $zip = $request->request->get('zip');

        $date = date("Ymd");

        $sql = "INSERT INTO `$AF_DB`.`resellers`
        (`reseller_typeID`,`company`,`first`,`middle`,`last`,`email`,`address`,`zip`,`created`,
        `city`,`state`,`province`,`countryID`,`url`,`phone`,`phone2`,`status`,`commission`)
        VALUES
        ('$reseller_typeID',?,'$first','$middle','$last','$email','$address','$zip','$date',
        '$city','$state','$province','$countryID','$url','$phone','$phone2','$status','$commission')        
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->bindValue(1, $company);
        $result->execute(); 
        $resellerID = $em->getConnection()->lastInsertId();
        if ($resellerID == "") {
            $text = "The reseller failed to add.";
            $status = "danger";         
        } else {
            $text = "The reseller was added.";
            $status = "success";
        } 
        $this->addFlash($status,$text);
        return $this->redirectToRoute('listresellers');  
    }              
}