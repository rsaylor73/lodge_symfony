<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class ReportsController extends Controller
{

    /**
     * @Route("/balancereport", name="balancereport")
     */
    public function balancereportAction()
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');

        $date = date("m/d/Y");


        // Past Due
        $start = date("Ymd", strtotime($date . "- 1 year"));
        $end = date("Ymd", strtotime($date . "-1 day"));
        $sql = $this->balance_report_sql($start,$end,$AF_DB);
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 
        $data1 = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $data1[$i][$key] = $value;
            }
            $i++;
        }
        // 90 Days
        $start = date("Ymd", strtotime($date . "+ 1 day"));
        $end = date("Ymd", strtotime($start . "+ 90 day"));
        $sql = $this->balance_report_sql($start,$end,$AF_DB);
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 
        $data2 = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $data2[$i][$key] = $value;
            }
            $i++;
        }

        // 90 Days To 6 Months
        $start = date("Ymd", strtotime($date . "+ 91 day"));
        $end = date("Ymd", strtotime($date . "+ 182 day"));
        $sql = $this->balance_report_sql($start,$end,$AF_DB);
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 
        $data3 = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $data3[$i][$key] = $value;
            }
            $i++;
        }

        // 6 Months To 9 Months
        $start = date("Ymd", strtotime($date . "+ 182 day"));
        $end = date("Ymd", strtotime($date . "+ 273 day"));
        $sql = $this->balance_report_sql($start,$end,$AF_DB);
        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 
        $data4 = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $data4[$i][$key] = $value;
            }
            $i++;
        }

        return $this->render('reports/balance.html.twig',[
            'date' => $date,
            'data1' => $data1,
            'data2' => $data2,
            'data3' => $data3,
            'data4' => $data4,
        ]);        
    }

    private function balance_report_sql($start,$end,$AF_DB) {
        $sql = "
        SELECT
            `r`.`reservationID`,
            `r`.`cron_grand_total`,
            `r`.`cron_discount_total`,
            `r`.`cron_payments_total`,
            `r`.`cron_commission_total`,
            `r`.`cron_payment_status`,
            `r`.`reservationType`,
            `rs`.`company`,
            `c`.`first`,
            `c`.`last`,
            `c`.`contactID`,
            DATE_FORMAT(`r`.`checkin_date`, '%M %d, %Y') AS 'checkin_date'

        FROM
            `reservations` r

        LEFT JOIN `$AF_DB`.`resellers` rs ON `r`.`resellerID` = `rs`.`resellerID`
        LEFT JOIN `$AF_DB`.`contacts` c ON `r`.`contactID` = `c`.`contactID`

        WHERE
            `r`.`status` = 'Active'
            AND DATE_FORMAT(`r`.`checkin_date`,'%Y%m%d') BETWEEN '$start' AND '$end'
            AND `r`.`cron_grand_total` - `r`.`cron_discount_total` - `r`.`cron_payments_total` - `r`.`cron_commission_total` > 0
        ";
        return($sql);
    }

    /**
     * @Route("/paymentsreport", name="paymentsreport")
     */
    public function paymentsreportAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();

        $date = date("Ymd");
        $start = date("Ymd", strtotime($date . "-90 DAY"));

        $date1 = $request->request->get('date1');
        $date2 = $request->request->get('date2');
        $payment_type = $request->request->get('payment_type');

        if (($date1 != "") && ($date2 != "")) {
            $start = date("Ymd", strtotime($date1));
            $date = date("Ymd", strtotime($date2));
        }

        $payment_type_sql = "";
        if ($payment_type != "") {
            $payment_type_sql = "AND `p`.`type` = '$payment_type'";
        }

        $sql = "
        SELECT
            `p`.`reservationID`,
            `p`.`type`,
            `p`.`credit_description`,
            `p`.`check_description`,
            `p`.`wire_description`,
            `p`.`amount`,
            DATE_FORMAT(`p`.`payment_date`, '%m/%d/%Y') AS 'payment_date',
            `r`.`status`,
            DATE_FORMAT(`p`.`payment_date`, '%Y%m%d') AS 'payment_date_ymd'

        FROM
            `payments` p, `reservations` r

        WHERE
            1
            AND DATE_FORMAT(`p`.`payment_date`, '%Y%m%d') BETWEEN '$start' AND '$date'
            AND `p`.`reservationID` = `r`.`reservationID`
            $payment_type_sql

        ORDER BY `p`.`payment_date` DESC

        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute(); 
        $last_date = "";
        $date_code = "";

        $i = "0";
        $data = "";
        while ($row = $result->fetch()) {
            $date_code = $row['payment_date_ymd'];
            
            $data[$i]['summary'] = "";

            if (($last_date != $date_code) && ($last_date != "")) {

                $sql2 = "
                SELECT
                    DATE_FORMAT(`p`.`payment_date`, '%Y%m%d') AS 'payment_date',
                    DATE_FORMAT(`p`.`payment_date`, '%m/%d/%Y') AS 'payment_date2',
                    SUM(`p`.`amount`) AS 'amount'


                FROM
                    `payments` p, `reservations` r

                WHERE
                    1
                    AND DATE_FORMAT(`p`.`payment_date`, '%Y%m%d') = '$last_date'
                    AND `p`.`reservationID` = `r`.`reservationID`
                    $payment_type_sql

                GROUP BY DATE_FORMAT(`p`.`payment_date`, '%Y%m%d')

                ORDER BY `p`.`payment_date` DESC
                ";        
                $result2 = $em->getConnection()->prepare($sql2);
                $result2->execute(); 
                while ($row2 = $result2->fetch()) {
                    $data[$i]['summary'] = "SUBTOTAL (" . $row2['payment_date2'] . ")";
                    $data[$i]['total'] = $row2['amount'];
                }
            }

            foreach ($row as $key=>$value) {
                $data[$i][$key] = $value;
            }
            $i++;
            $last_date = $date_code;
        }

        $date = date("m/d/Y");
        return $this->render('reports/payments.html.twig',[
            'date' => $date,
            'data' => $data,
        ]);        
    }

    /**
     * @Route("/gisreport", name="gisreport")
     */
    public function gisreportAction()
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $data = "";

        $date1 = date("Y-m-d");
        $date2 = date("Y-m-d", strtotime($date1 . "+ 30 DAY"));

        $date1a = date("m/d/Y", strtotime($date1));
        $date2a = date("m/d/Y", strtotime($date2));

        $sql = "
        SELECT
            `r`.`reservationID`,
            `i`.`contactID`,
            `c`.`first`,
            `c`.`middle`,
            `c`.`last`,
            `l`.`name`,
            DATE_FORMAT(`r`.`checkin_date`, '%m/%d/%Y') AS 'checkin_date',
            `i`.`inventoryID`

        FROM
            `reservations` r

        LEFT JOIN `inventory` i ON
            `r`.`reservationID` = `i`.`reservationID` 
            AND DATE_FORMAT(`r`.`checkin_date`, '%Y%m%d') = `i`.`date_code`

        LEFT JOIN `locations` l ON
            `i`.`locationID` = `l`.`id`


        LEFT JOIN `$AF_DB`.`contacts` c ON
            `i`.`contactID` = `c`.`contactID`

        WHERE
            `r`.`checkin_date` BETWEEN '$date1' AND '$date2' AND `r`.`status` = 'Active'

        ORDER BY `l`.`name` ASC, `r`.`checkin_date` ASC

        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $i = "0";
        $name = "";
        $lodge = "";

        while ($row = $result->fetch()) {
            $name = $row['name'];
            foreach ($row as $key=>$value) {
                $data[$name][$i][$key] = $value;
            }

            // Get GIS Sent
            $sql2 = "SELECT `id` FROM `gis_log` WHERE `reservationID` = '$row[reservationID]' AND `contactID` = '$row[contactID]' LIMIT 1";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute(); 
            $gis_sent = "";
            while ($row2 = $result2->fetch()) {
                $data[$name][$i]['gis_sent'] = 'Yes';
                $gis_sent = "1";
            }
            if ($gis_sent == "") {
                $data[$name][$i]['gis_sent'] = 'No';
            }

            // Waiver and GIS complete
            $sql2 = "SELECT `gis_waiver`,`gis_confirmation` FROM `gis_action` WHERE `reservationID` = '$row[reservationID]' AND `contactID` = '$row[contactID]' AND `inventoryID` = '$row[inventoryID]'";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute(); 
            $gis_waiver = "";
            $gis_confirmation = "";

            while ($row2 = $result2->fetch()) {
                if (($row2['gis_waiver'] == "complete") or ($row2['gis_waiver'] == "verified")) {
                    $data[$name][$i]['gis_waiver'] = 'Yes';
                    $gis_waiver = "1";
                }
                if (($row2['gis_confirmation'] == "complete") or ($row2['gis_confirmation'] == "verified")) {
                    $data[$name][$i]['gis_confirmation'] = 'Yes';
                    $gis_confirmation = "1";
                }
            } 

            if ($gis_waiver == "") {
                $data[$name][$i]['gis_waiver'] = 'No';
            }
            if ($gis_confirmation == "") {
                $data[$name][$i]['gis_confirmation'] = 'No';
            }           

            $lodge['lodge'] = $name;
            $i++;
        }

        return $this->render('reports/gisreport.html.twig',[
            'date1a' => $date1a,
            'date2a' => $date2a,
            'data' => $data,
            'lodge' => $lodge,
        ]);        
    }

    /**
     * @Route("/reservationsreport", name="reservationsreport")
     */
    public function reservationsreportAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $AF_DB = $this->container->getParameter('AF_DB');
        $site_path = $this->container->getParameter('site_path');

        $date = date("Ymd");
        $start = date("Ymd", strtotime($date . "-30 DAY"));

        $reportdate1 = $request->request->get('reportdate1');
        $reportdate2 = $request->request->get('reportdate2');
        $format = $request->request->get('format');

        if (($reportdate1 != "") && ($reportdate2 != "")) {
            $start = date("Ymd", strtotime($reportdate1));
            $end = date("Ymd", strtotime($reportdate2));
        }

        $date1 = date("m/d/Y", strtotime($start));
        $date2 = date("m/d/Y", strtotime($date));

        $sql = "
        (
        SELECT
            DATE_FORMAT(`r`.`date_booked`, '%m/%d/%Y') AS 'date_booked',
            DATE_FORMAT(`r`.`cxl_date`, '%m/%d/%Y') AS 'date_cancelled',
            `r`.`reservationID`,
            `u`.`first_name`,
            `u`.`last_name`,
            DATE_FORMAT(`r`.`checkin_date`, '%m/%d/%Y') AS 'checkin_date',
            `rs`.`company`,
            `cxl`.`cxl_reason`,
            `cn`.`country`,
            `r`.`pax` + `r`.`children` AS 'total_pax'

        FROM
            `reservations` r

        LEFT JOIN `user` u ON `r`.`userID` = `u`.`id`
        LEFT JOIN `$AF_DB`.`resellers` rs ON `r`.`resellerID` = `rs`.`resellerID`
        LEFT JOIN `inventory_cxl` cxl ON `r`.`reservationID` = `cxl`.`reservationID`
        LEFT JOIN `$AF_DB`.`contacts` c ON `r`.`contactID` = `c`.`contactID`
        LEFT JOIN `$AF_DB`.`countries` cn ON `c`.`countryID` = `cn`.`countryID`

        WHERE
            DATE_FORMAT(`r`.`date_booked`, '%Y%m%d') BETWEEN '$start' AND '$date'

        GROUP BY `r`.`reservationID`

        ORDER BY `r`.`date_booked`
        ) UNION (
        SELECT
            DATE_FORMAT(`r`.`date_booked`, '%m/%d/%Y') AS 'date_booked',
            DATE_FORMAT(`r`.`cxl_date`, '%m/%d/%Y') AS 'date_cancelled',
            `r`.`reservationID`,
            `u`.`first_name`,
            `u`.`last_name`,
            DATE_FORMAT(`r`.`checkin_date`, '%m/%d/%Y') AS 'checkin_date',
            `rs`.`company`,
            `cxl`.`cxl_reason`,
            `cn`.`country`,
            `r`.`pax` + `r`.`children` AS 'total_pax'

        FROM
            `reservations` r

        LEFT JOIN `user` u ON `r`.`userID` = `u`.`id`
        LEFT JOIN `$AF_DB`.`resellers` rs ON `r`.`resellerID` = `rs`.`resellerID`
        LEFT JOIN `inventory_cxl` cxl ON `r`.`reservationID` = `cxl`.`reservationID`
        LEFT JOIN `$AF_DB`.`contacts` c ON `r`.`contactID` = `c`.`contactID`
        LEFT JOIN `$AF_DB`.`countries` cn ON `c`.`countryID` = `cn`.`countryID`

        WHERE
            DATE_FORMAT(`r`.`cxl_date`, '%Y%m%d') BETWEEN '$start' AND '$date'

        GROUP BY `r`.`reservationID`

        ORDER BY `r`.`date_booked`
        )

        ORDER BY `date_booked` DESC
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();  

        if ($format == "") {
            $data = "";
            $i = 0;
            while ($row = $result->fetch()) {
                foreach ($row as $key=>$value) {
                    $data[$i][$key] = $value;
                }
                $i++;
            }

            return $this->render('reports/reservationsreport.html.twig',[
                'date1' => $date1,
                'date2' => $date2,
                'data' => $data
            ]);
        }  else {
            // excel
            $data = "";
            $i = 0;
            while ($row = $result->fetch()) {
                $data[$i]['a'] = $row['date_booked'];
                $data[$i]['b'] = $row['date_cancelled'];
                $data[$i]['c'] = $row['reservationID'];
                $data[$i]['d'] = $row['first_name'] . " " . $row['last_name'];
                $data[$i]['e'] = $row['checkin_date'];
                if ($row['date_cancelled'] == "") {
                    $data[$i]['f'] = $row['total_pax'];
                    $data[$i]['g'] = "0";
                } else {
                    $data[$i]['f'] = "0";
                    $data[$i]['g'] = $row['total_pax'] * -1;
                }
                $data[$i]['h'] = $row['company'];
                $data[$i]['i'] = $row['cxl_reason'];
                $data[$i]['j'] = $row['country'];
                $i++;
            }
            // call the service class
            $this->get('commonservices')->reservationreportsexcel($data,$site_path); 
            $filename = "dailyreservations.xlsx";
            $newfile = $site_path . "/reports/" . $filename;

            // send email
            $usr = $this->get('security.token_storage')->getToken()->getUser();
            $username = $usr->getUsername();

            $site_name = $this->container->getParameter('site_name');
            $site_email = $this->container->getParameter('site_email');

            // check if active
            $sql = "SELECT `email`,`first_name`,`last_name` FROM `user` WHERE `username` = '$username'";
            $result = $em->getConnection()->prepare($sql);
            $result->execute();        
            $name = "";
            $email = "";
            while ($row = $result->fetch()) {
                $name = $row['first_name'] . " " . $row['last_name'];
                $email = $row['email'];
            }

            $title = "Daily Reservations Report";
            $message = \Swift_Message::newInstance()
              ->setFrom($site_email)
              ->setTo($email)
              ->setSubject($title)
              ->setBody(
                $this->renderView(
                    'Emails/reservationsreport.html.twig',
                    array(
                        'name' => $name,
                        'site_name' => $site_name,
                        'date1' => $date1,
                        'date2' => $date2
                    )
                ),'text/html'
              )
              ->attach(\Swift_Attachment::fromPath($newfile))
            ;

            $this->get('mailer')->send($message);             

            $this->addFlash('success','Please check your email for the attached excel file');
            return $this->redirectToRoute('reservationsreport');

        }      
    }
}