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

        $date = date("Ymd");
        $start = date("Ymd", strtotime($date . "-90 DAY"));
        $end = date("Ymd", strtotime($date . "+90 DAY"));

        $sql = "
        SELECT
            `r`.`reservationID`

        FROM
            `reservations` r

        WHERE
            `r`.`status` = 'Active'
            AND DATE_FORMAT(`r`.`checkin_date`, '%Y%m%d') BETWEEN '$start' AND '$end'

        ";

        print "$sql<Br>";


        return $this->render('reports/balance.html.twig');        
    }

    /**
     * @Route("/paymentsreport", name="paymentsreport")
     */
    public function paymentsreportAction()
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();

        return $this->render('reports/payments.html.twig');        
    }

}