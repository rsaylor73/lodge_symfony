<?php
/* This is a service class */
namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use AppBundle\Entity\User;

class commonservices extends Controller
{
    
    protected $em;
    protected $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;

    }

    public function get_states() {
    	$em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
        	`s`.`state`,
        	`s`.`state_abbr`

        FROM
        	`$AF_DB`.`state` s

        WHERE
        	1

        ORDER BY `state` ASC
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $state = "";
        $i = "0";
        while ($row = $result->fetch()) {
        	foreach ($row as $key=>$value) {
        		$state[$i][$key] = $value;
        	}
            $i++;
        } 
        return($state);          
    }


    public function get_country() {
        $em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
            `c`.`countryID`,
            `c`.`country`

        FROM
            `$AF_DB`.`countries` c

        WHERE
            1

        ORDER BY `c`.`country` ASC
        ";  
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $country = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $country[$i][$key] = $value;
            }
            $i++;
        } 
        return($country);               
    }

    public function get_resellertype() {
        $em = $this->em;
        $container = $this->container;

        $AF_DB = $container->getParameter('AF_DB');

        $sql = "
        SELECT
            `r`.`reseller_typeID`,
            `r`.`type`,
            `r`.`status`

        FROM
            `$AF_DB`.`reseller_types` r

        WHERE
            1

        ORDER BY `r`.`type` ASC
        ";  
        $result = $em->getConnection()->prepare($sql);
        $result->execute();        
        $type = "";
        $i = "0";
        while ($row = $result->fetch()) {
            foreach ($row as $key=>$value) {
                $type[$i][$key] = $value;
            }
            $i++;
        } 
        return($type);              
    }



}