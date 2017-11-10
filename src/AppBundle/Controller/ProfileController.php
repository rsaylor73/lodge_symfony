<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use AppBundle\Form\Type\ProfileType;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

class ProfileController extends Controller
{
    /**
     * @Route("/profile", name="profile")
     */
    public function profileAction(Request $request)
    {

    	$usr = $this->get('security.token_storage')->getToken()->getUser();
    	$username = $usr->getUsername();

        $repository = $this->getDoctrine()->getRepository('AppBundle:user');
        $sql = $repository->createQueryBuilder('u')
            ->select('u.id','u.username','u.email','u.firstName','u.lastName')
            ->where('u.username = :username')
            ->setParameter('username',$username)
            ->getQuery();

        $result = $sql->getResult();
        $number = count($result);
	
		if ($number != "1") {
        	// do error
			$this->addFlash('danger','There was an error getting your details.');
			return $this->redirectToRoute('profile');
			die; // This will stop the registration
        }
        $id = $result[0]['id'];
        $username = $result[0]['username'];
        $email = $result[0]['email'];
        $firstName = $result[0]['firstName'];
        $lastName = $result[0]['lastName'];

        $user = new User();
        $user->setUsername($username);
        $user->setfirstName($firstName);
        $user->setlastName($lastName);
        $user->setemail($email);

		$form = $this->createForm(ProfileType::class,$user,[
			]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            $firstName = $_POST['profile']['firstName'];
            $lastName = $_POST['profile']['lastName'];
            $email = $_POST['profile']['email'];
            $new_password = $_POST['profile']['password'];

            $new_pw_sql = "";
            if ($new_password != "") {
                $password = password_hash($new_password, PASSWORD_BCRYPT);
                $new_pw_sql = ",`password` = '$password'";
            }

            $em = $this->getDoctrine()->getManager();
            $sql = "
            UPDATE `user` SET 
            
            `first_name` = '$firstName',
            `last_name` = '$lastName',
            `email` = '$email'
            $new_pw_sql

            WHERE `username` = '$username'";
            $result = $em->getConnection()->prepare($sql);
            $result->execute();

            $this->addFlash('success','Your account was updated.');
            return $this->redirectToRoute('profile');
        }

		return $this->render('registration/profile.html.twig', [
            'profile_form' => $form->createView(),
        ]);
    }



}