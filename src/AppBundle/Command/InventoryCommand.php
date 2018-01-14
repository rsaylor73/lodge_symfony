<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class InventoryCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:inventory')
            ->setDescription('This should be ran weekly by a cronjob to create inventory.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
        $base_date = "";

        $sql = "SELECT `inventory_start_date`,`id` FROM `locations` WHERE `auto_inventory` = 'On'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        while ($row = $result->fetch()) {   
            // get last date_code
            $sql2 = "SELECT `date_code` FROM `inventory` WHERE `locationID` = '$row[id]' ORDER BY `date_code` DESC LIMIT 1";
            $result2 = $em->getConnection()->prepare($sql2);
            $result2->execute();
            while ($row2 = $result2->fetch()) {
                $base_date = $row2['date_code'];
            }            
            $start = date("Ymd", strtotime($base_date));
            $end = date("Ymd", strtotime($start . "+7 DAY"));

            $output->writeln("Creating inventory: $start to $end");
            $inventoryResult = $this->getContainer()->get('commonservices')->create_inventory($start,$end);
            $output->writeln("Done");

        } 
		//$sql = $this->getContainer()->get('commonservices')->checkin_report_query();


    }
}