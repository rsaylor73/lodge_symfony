<?php

namespace AppBundle\Controller;

# you have to lead the entity for the DB you will be working from
use AppBundle\Entity\User;

use AppBundle\Form\Type\UserType;
use AppBundle\Form\Type\ForgotPasswordType;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;


class RegistrationController extends Controller
{

    /**
     * @Route("/forgotpassword", name="forgotpassword")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \LogicException
     */
    public function forgotpasswordAction(Request $request) {
        $form = $this->createForm(ForgotPasswordType::class);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            // send password
            $em = $this->getDoctrine()->getManager();
            $site_url = $this->container->getParameter('site_url');
            $site_name = $this->container->getParameter('site_name');
            $site_email = $this->container->getParameter('site_email');

            $email = "";
            $postData = $request->request->all();
            if(isset($postData['forgot_password']['email'])) {
                $email = $postData['forgot_password']['email'];
            }
            $sql = "SELECT `id`,`first_name`,`last_name`,`email`,`username` 
            FROM `user` WHERE `email` = '$email' LIMIT 1";

            $result = $em->getConnection()->prepare($sql);
            $result->execute();
            $found = "0";
            while ($row = $result->fetch()) {
                $found = "1";

                $new_password = $this->randomPassword();
                $password = password_hash($new_password, PASSWORD_BCRYPT);

                $sql2 = "UPDATE `user` SET `password` = '$password' WHERE `id` = '$row[id]'";
                $result2 = $em->getConnection()->prepare($sql2);
                $result2->execute();

                $name = $row['first_name'] . " " . $row['last_name'];
                $username = $row['username'];

                // send welcome email
                $message = (new \Swift_Message('Welcome to LiveAboard'))
                    ->setFrom($site_email)
                    ->setTo($email)
                    ->setSubject('Forgot password for LiveAboard')
                    ->setBody(
                        $this->renderView(
                            'Emails/forgotpassword.html.twig',
                            array(
                                'name' => $name,
                                'username' => $username,
                                'password' => $new_password
                            )
                        ),
                        'text/html'
                    )
                ;
                $this->get('mailer')->send($message);
            }
            $this->addFlash('success','You should receive a new password in your email.');
            return $this->redirectToRoute('index');
            //die;
        }

        return $this->render('registration/forgotpassword.html.twig', [
            'forgotpassword_form' => $form->createView(),
        ]);
    }

    /**
     * @Route("/register", name="registration")
     * @return \Symfony\Component\HttpFoundation\Response
     * @throws \LogicException
     */
    public function registerAction(Request $request)
    {

        $user = new User();

        $form = $this->createForm(UserType::class,$user,[

        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // get parameters
            $site_url = $this->container->getParameter('site_url');
            $site_name = $this->container->getParameter('site_name');
            $site_email = $this->container->getParameter('site_email');
            
            // get form parameters (this comes from the serialized getters and setters)
            $username = $user->getUsername();
            $email = $user->getEmail();
            $name = $user->getFirstName();

            // check the database for the username and password
            $repository = $this->getDoctrine()->getRepository('AppBundle:user');
            $sql = $repository->createQueryBuilder('u')
                ->select('u.email','u.username')
                ->where('u.username = :username or u.email = :email')
                ->orderBy('u.email','DESC')
                ->setParameter('username',$username)
                ->setParameter('email',$email)
                ->getQuery();

            $result = $sql->getResult();
            $number = count($result);
            if ($number > 0) {
                $this->addFlash('danger','Either the email address or the username selected is already registered. Please select a different value and try again.');
                return $this->redirectToRoute('registration');
                die; // This will stop the registration
            }

            // save the new user

            $password = $this
                ->get('security.password_encoder')
                ->encodePassword(
                    $user,
                    $user->getPlainPassword()
                )
            ;

            $user->setPassword($password);

            // em means instanceof Entity Manager
            $em = $this->getDoctrine()->getManager();

            $em->persist($user);

            $em->flush();

            // force the new user to login automatically
            // $user is from the entety
            // $password is the encoded password
            // main is the firewall rule from securty
            // roles returns the array USER_ROLE
            $token = new UsernamePasswordToken(
                $user,
                $password,
                'main',
                $user->getRoles()
            );

            // now set the token in Symfony's token database
            $this->get('security.token_storage')->setToken($token);
            $this->get('session')->set('_security_main', serialize($token)); // main is the name of the firewall

            // send welcome email
            $message = (new \Swift_Message('Welcome to LiveAboard'))
                ->setFrom($site_email)
                ->setTo($email)
                ->setSubject('Welcome to LiveAboard')
                ->setBody(
                    $this->renderView(
                        // app/Resources/views/Emails/registration.html.twig
                        'Emails/registration.html.twig',
                        array(
                            'name' => $name,
                            'username' => $username,
                            'password' => $user->getPlainPassword()
                        )
                    ),
                    'text/html'
                )
            ;

            //$mailer->send($message);

            $this->get('mailer')->send($message);


            // redirect to homepage

            $this->addFlash('success','You are now successfully registered!');

            return $this->redirectToRoute('index');
        }

        return $this->render('registration/register.html.twig', [
            'registration_form' => $form->createView(),
        ]);
    }

    private function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 8; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
    }
}