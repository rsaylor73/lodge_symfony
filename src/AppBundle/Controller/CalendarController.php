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
        
        $sql = "
        SELECT
            `l`.`id`,
            `l`.`name`
        FROM
            `locations` l

        WHERE
            1

        ORDER BY `l`.`name` ASC
        ";
        $data = "";
        $data2 = "";
        $name = "";
        $i = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 

        while ($row = $result->fetch()) {
            $name = $row['name'];
            foreach ($row as $key=>$value) {
                $data[$name][$i][$key] = $value;
            }

            // get rooms
            $sql2 = "
            SELECT
                `r`.`description`

            FROM
                `rooms` r

            WHERE
                `r`.`locationID` = '$row[id]'

            ORDER BY `r`.`id` ASC
            ";

            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute(); 
            while ($row2 = $result2->fetch()) {
                $data[$name][$i]['description'] = $row2['description'];
                $i++;
            }            
            $lodge['lodge'] = $name;
            
            $data2 = $this->calendar_body(12,$em);


        }

        // param is weeks. Both should eual the same weeks
        $calendar_head = $this->days_template(12);
        $calendar_title = $this->calendar_template(12);


        return $this->render('reports/calendar.html.twig',[
            'data' => $data,
            'lodge' => $lodge,
            'calendar_head' => $calendar_head,
            'calendar_title' => $calendar_title,
            'data2' => $data2,
        ]); 
    }

    private function days_template($weeks) {
        $date = date("Ymd");
        $day1 = date("D");
        $day2 = date("D",strtotime($date . "+1 day"));
        $day3 = date("D",strtotime($date . "+2 day"));
        $day4 = date("D",strtotime($date . "+3 day"));
        $day5 = date("D",strtotime($date . "+4 day"));
        $day6 = date("D",strtotime($date . "+5 day"));
        $day7 = date("D",strtotime($date . "+6 day"));

        $html = "";
        $weeks++; // add 1
        for ($x=0; $x < $weeks; $x++) {
            $html .= "<td>$day1</td><td>$day2</td><td>$day3</td><td>$day4</td><td>$day5</td><td>$day6</td><td>$day7</td>";
        }

        return($html);
    }

    private function calendar_template($weeks) {
        $date = date("Ymd");
        
        $weeks++;
        $html = "";
        $date = "Ymd";
        $day = date("n/j");
        $html .= "<td>$day</td>";

        $next_day = date("Ymd");
        $weeks = $weeks * 7; // turn it into days
        $weeks = $weeks - 1; // remove last day
        for ($x=0; $x < $weeks; $x++) {
            $next_day = date("Ymd", strtotime($next_day . "+1 day"));
            $day = date("n/j", strtotime($next_day));
            $html .= "<td>$day</td>";
        }
        return($html); 
    }

    private function calendar_body($weeks,$em) {
        $weeks++;
        $date1 = date("Ymd");
        $date2 = date("Ymd", strtotime($date1 . "+$weeks weeks"));

        $sql = "
        SELECT
            `i`.`roomID`,
            `r`.`description`,
            GROUP_CONCAT(`i`.`date_code`) AS 'date_code',
            GROUP_CONCAT(`i`.`status`) AS 'status'

        FROM
            `inventory` i, `rooms` r

        WHERE
            `i`.`date_code` BETWEEN '$date1' AND '$date2'
            AND `i`.`roomID` = `r`.`id`

        GROUP BY `i`.`roomID`,`i`.`date_code`
        ";

        $date_code = "";
        $last_room = "";
        $html = "<tr>";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            if ($last_room == "") {
                $last_room = $row['roomID'];
            }
            $date_code = explode(",",$row['date_code']);
            $status = explode(",",$row['status']);
            $date = date("n/j", strtotime($date_code[0]));
            if (($status[0] == "avail") or ($status[1] == "avail")) {
                $stat = "avail";
            } elseif (($status[0] == "tentative") or ($status[1] == "tentative")) {
                $stat = "tentative";
            } elseif (($status[0] == "booked") or ($status[1] == "booked")) {
                $stat = "booked";
            }

            if ($last_room != $row['roomID']) {
                $html .= "</tr><tr>";
                $last_room = $row['roomID'];
            }
            if ($stat == "avail") {
                $html .= "<td bgcolor=\"#d8edf6\">&nbsp;</td>";
            } elseif ($stat == "tentative") {
                $html .= "<td bgcolor=\"#ef9633\">&nbsp;</td>";
            } elseif ($stat == "booked") {
                $html .= "<td bgcolor=\"#23ce39\">&nbsp;</td>";
            }
        }
        $html .= "</tr>"; 
        return($html);
    }

}