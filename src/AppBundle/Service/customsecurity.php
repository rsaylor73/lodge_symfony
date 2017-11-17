<?php
/* This is a service class */
namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use AppBundle\Entity\User;

class customsecurity extends Controller
{
    
    protected $em;
    protected $container;

    public function __construct(EntityManagerInterface $entityManager, ContainerInterface $container)
    {
        $this->em = $entityManager;
        $this->container = $container;

    }
    

	public function check_access($section) {
		$em = $this->em;
        $container = $this->container;

        $usr = $this->get('security.token_storage')->getToken()->getUser();
        $username = $usr->getUsername();
        $role = $usr->getRole();


		$description = "";

		$sql = "
		SELECT 
			`section`,`role`,`description` 

		FROM 
			`access` 

		WHERE 
			`section` = '$section'
		";

		$found = "0";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $access = "";
        while ($row = $result->fetch()) {
        	$access = explode(",",$row['role']);
        	$description = $row['description'];
        	if(is_array($access)) {
        		foreach ($access as $key=>$value) {
        			if ($value == $role) {
        				$found = "1";
        			}
        		}
        	}
        }
        switch ($found) {
        	case 0: // access denied
		        return $this->redirectToRoute('accessdenied',[
		            'section' => $description,
		        ]);
		        die; // this might not be needed
        	break;

        	case 1: // access granted
        	   return "ok";
        	break;
        }

	}




}