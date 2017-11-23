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
			`r`.`status`,
            `r`.`pax`,
            `r`.`children`,
            `r`.`nights`,
            `r`.`manual_commission_override`
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

    public function transfer_amount($nights) {
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