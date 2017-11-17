<?php
/* This is a service class */
namespace AppBundle\Service;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class customsecurity
{

    protected $em;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->em = $entityManager;

    }


	public function check_access($role,$section) {

		$em = $this->em;
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
        	// return nothing
        	break;
        }

	}




}