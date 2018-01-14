<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class GisReportCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:gisreport')
            ->setDescription('This should be ran once weekly.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();

        $site_url = $this->getContainer()->getParameter('site_url');
        $site_name = $this->getContainer()->getParameter('site_name');
        $site_email = $this->getContainer()->getParameter('site_email');
        $email = $this->getContainer()->getParameter('systemreport');
        $AF_DB = $this->getContainer()->getParameter('AF_DB');
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

        $title = "GIS Report";

        // send welcome email
        $message = (new \Swift_Message($title))
            ->setFrom($site_email)
            ->setTo($email)
            ->setSubject($title)
            ->setBody(
                $this->getContainer()->get('templating')->render(
                    'reports/gisreport_email.html.twig',
                    array(
                        'date1a' => $date1a,
                        'date2a' => $date2a,
                        'data' => $data,
                        'lodge' => $lodge,
                    )
                ),
                'text/html'
            )
        ;
        $this->getContainer()->get('mailer')->send($message);                

    }
}