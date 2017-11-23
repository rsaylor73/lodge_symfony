<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use AppBundle\Service\authorizenet;

class InvoiceController extends Controller
{

    /**
     * @Route("/invoice", name="invoice")
     */
    public function invoiceAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('reservations');
        if ($check != "ok") {
            return($check);
        }

        $em = $this->getDoctrine()->getManager();
        $reservationID = $request->query->get('reservationID');
        $mode = $request->query->get('mode');

        // tent total
        $total = $this->tenttotal($em,$reservationID);

        $details = $this
            ->get('reservationdetails')
            ->getresdetails($reservationID);

        $transfer_amount = $this
            ->get('reservationdetails')
            ->transfer_amount($details['nights']);

        switch ($mode) {
            case "view":

            break;

            case "print":

            break;

            case "email":

            break;

            default:
            // error
            die;
            break;
        }

        $this->addFlash('success','Test');
        return $this->redirectToRoute('viewreservation',[
            'reservationID' => $reservationID,
        ]);
    }



    private function tenttotal($em,$reservationID) {
        $sql = "
        SELECT
            SUM(`i`.`nightly_rate`) AS 'total',
            MIN(`i`.`nightly_rate`) AS 'nightly_rate',
            `r`.`pax`,
            `r`.`children`,
            `r`.`nights`,
            `r`.`manual_commission_override`

        FROM
            `inventory` i

        LEFT JOIN `reservations` r ON `i`.`reservationID` = `r`.`reservationID`

        WHERE
            `i`.`reservationID` = '$reservationID'

        GROUP BY `r`.`pax`, `r`.`children`, `r`.`nights`
        ";
        $total = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        while ($row = $result->fetch()) {
            $total = $row['total'];
        }
        return($total);
    }



}        