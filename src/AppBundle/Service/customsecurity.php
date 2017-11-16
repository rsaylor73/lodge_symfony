<?php
/* This is a service class */
namespace AppBundle\Service;

use Symfony\Component\DependencyInjection\ContainerInterface;

class customsecurity
{


	public function check_access($role,$section) {



        print "Test: $role :: $section<br>";	
	}




}