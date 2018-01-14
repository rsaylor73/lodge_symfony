<?php

namespace AppBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class CheckinCommand extends ContainerAwareCommand
{
    protected function configure()
    {
        $this
            ->setName('app:checkin')
            ->setDescription('This should be ran daily from cron and will email the lodge a list of guests who are checking in today.')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {

        $doctrine = $this->getContainer()->get('doctrine');
        $em = $doctrine->getManager();
		$sql = $this->getContainer()->get('commonservices')->checkin_report_query();

        $site_url = $this->getContainer()->getParameter('site_url');
        $site_name = $this->getContainer()->getParameter('site_name');
        $site_email = $this->getContainer()->getParameter('site_email');
        $email = $this->getContainer()->getParameter('checkinreport');

        $nice_date = date("d M Y");

        $output->writeln('Generating report...');

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

        $title = "Daily guest Check-In list";

        // send welcome email
        $message = (new \Swift_Message($title))
            ->setFrom($site_email)
            ->setTo($email)
            ->setSubject($title)
            ->setBody(
                $this->getContainer()->get('templating')->render(
                    'lodge/checkinreport_email_base64.html.twig',
                    array(
                        'date' => $nice_date,
			            'data' => $data,
			            'format' => 'email',
                    )
                ),
                'text/html'
            )
        ;
        $this->getContainer()->get('mailer')->send($message);


    }
}