<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class DollarsController extends Controller
{

    /**
     * @Route("/viewreservationdollars", name="viewreservationdollars")
     * @Route("/viewreservationdollars/{reservationID}")
     */
    public function viewreservationdollarsAction(Request $request,$reservationID='')
    {
        $em = $this->getDoctrine()->getManager();

        // init vars
        $transfer_amount = "";
        $pax = "";
        $transfer_total = "";

        if ($reservationID == "") {
            $reservationID = $request->query->get('reservationID');
        }

        $sql = "
        SELECT
        	SUM(`i`.`nightly_rate`) AS 'total',
        	MIN(`i`.`nightly_rate`) AS 'nightly_rate',
        	`r`.`pax`,
        	`r`.`children`,
        	`r`.`nights`

        FROM
        	`inventory` i

        LEFT JOIN `reservations` r ON `i`.`reservationID` = `r`.`reservationID`

        WHERE
        	`i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`pax`, `r`.`children`, `r`.`nights`
        ";
		$result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $dollars = "";
        while ($row = $result->fetch()) {
        	foreach($row as $key=>$value) {
        		$dollars[$i][$key] = $value;
        	}
        	$i++;
            $transfer_amount = $this->transfer_amount($row['nights']);
            $pax = $row['pax'] + $row['children'];
            $transfer_total = $transfer_amount * $pax;
        }


        return $this->render('reservations/viewreservationdollars.html.twig',[
            'reservationID' => $reservationID,
            'dollars' => $dollars,
            'transfer_amount' => $transfer_amount,
            'transfer_total' => $transfer_total,
        ]);
    }

    private function transfer_amount($nights) {
        $amount = "";
        switch ($nights) {
            case "3":
            $amount = "150";
            break;
            case "4":
            $amount = "150";
            break;
            case "5":
            $amount = "180";
            break;
            case "6":
            $amount = "210";
            break;
            default:
            $amount = "150";
            break;
        }
        return($amount);
    }

}