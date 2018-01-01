<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class CalendarController extends Controller
{

    /**
     * @Route("/viewcalendar", name="viewcalendar")
     */
    public function viewcalendarAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('lodgereports');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        
        $start_date = $request->request->get('start_date');
        $days = $request->request->get('days');

        if ($start_date == "") {
            $date1 = date("Ymd");
            $date2 = date("Ymd", strtotime($date1 . "+30 DAY"));
        } else {
            $date1 = date("Ymd", strtotime($start_date));
            $date2 = date("Ymd", strtotime($date1 . "+$days DAY"));
        }

        $date1F = date("m/d/Y", strtotime($date1));
        $date2F = date("m/d/Y", strtotime($date2));

        $sql = "
        SELECT
            `r`.`description`,
            `i`.`bed`,
            `i`.`nightly_rate`,
            `i`.`type`,
            `i`.`status`,
            `i`.`reservationID`,
            DATE_FORMAT(`i`.`date_code`,'%m/%d/%Y') AS 'date'

        FROM
            `inventory` i

        LEFT JOIN `rooms` r ON `i`.`roomID` = `r`.`id`

        WHERE
            `i`.`date_code` BETWEEN '$date1' AND '$date2'

        ORDER BY `i`.`date_code` ASC, `r`.`description` ASC, `i`.`bed` ASC
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
        return $this->render('reports/calendar.html.twig',[
            'data' => $data,
            'date1F' => $date1F,
            'date2F' => $date2F,
        ]); 
    }

}