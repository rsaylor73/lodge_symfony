<?php
/* This is a service class */
namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use AppBundle\Entity\User;

class reservationdetails extends Controller
{
    
    protected $em;
    protected $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;

    }

    public function getresdetails($reservationID) {
    	$em = $this->em;
    	$sql = "
    	SELECT
			`r`.`status`
    	FROM
    		`reservations` r

    	WHERE
    		`r`.`reservationID` = '$reservationID'
    	";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $details = "";
        while ($row = $result->fetch()) {
        	foreach ($row as $key=>$value) {
        		$details[$key] = $value;
        	}
        } 
        return($details);   	
    }

}