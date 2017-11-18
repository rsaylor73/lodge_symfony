<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class AdminUsersController extends Controller
{

    /**
     * @Route("/users", name="users")
     */
    public function usersAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('users');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();

        $sql = "
        SELECT
            `u`.`id`,
            `u`.`first_name`,
            `u`.`last_name`,
            `u`.`email`,
            `u`.`username`,
            `u`.`role`,
            `u`.`status`

        FROM
            `user` u

        WHERE
            1

        ORDER BY `u`.`last_name`, `u`.`first_name`
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        $i = "0";
        $users = "";
        while ($row = $result->fetch()) {
            foreach($row as $key=>$value) {
                $users[$i][$key] = $value;
            }
            $i++;
        }

        return $this->render('admin/users.html.twig',[
            'users' => $users,
        ]);  
    }


    /**
     * @Route("/edituser", name="edituser")
     * @Route("/edituser/{id}")
     */
    public function edituserAction(Request $request, $id='')
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('users');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();
        $sql = "SELECT `first_name`,`last_name`,`email`,`status`,`role`,`username` FROM `user` WHERE `id` = '$id'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();
        
        $first_name = ""; $last_name = ""; $email = "";
        $role = ""; $username = ""; $status = "";

        while ($row = $result->fetch()) {
            $first_name = $row['first_name'];
            $last_name = $row['last_name'];
            $email = $row['email'];
            $role = $row['role'];
            $username = $row['username'];
            $status = $row['status'];
        }

        return $this->render('admin/edituser.html.twig',[
            'id' => $id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'email' => $email,
            'role' => $role,
            'username' => $username,
            'status' => $status,
        ]);  
    }       

    /**
     * @Route("/updateuser", name="updateuser")
     */
    public function updateuserAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('users');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();

        $first_name = $request->request->get('first_name');
        $last_name = $request->request->get('last_name');
        $email = $request->request->get('email');
        $role = $request->request->get('role');
        $status = $request->request->get('status');
        $id = $request->request->get('id');

        $sql =  "UPDATE `user` SET 
        `first_name` = '$first_name',
        `last_name` = '$last_name',
        `email` = '$email',
        `role` = '$role',
        `status` = '$status'
        WHERE `id` = '$id'
        ";

        $result = $em->getConnection()->prepare($sql);
        $result->execute();

        $text = "The user was updated.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('users');
    }

    /**
     * @Route("/adduser", name="adduser")
     */
    public function adduserAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('users');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $first_name = $request->query->get('first_name');
        $last_name = $request->query->get('last_name');
        $email = $request->query->get('email');
        $role = $request->query->get('role');
        $status = $request->query->get('status');
        $username = $request->query->get('username');

        return $this->render('admin/newuser.html.twig',[
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'username' => $username,
        ]);
    }    

    /**
     * @Route("/saveuser", name="saveuser")
     */
    public function saveuserAction(Request $request)
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('users');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        $em = $this->getDoctrine()->getManager();


        // get parameters
        $site_url = $this->container->getParameter('site_url');
        $site_name = $this->container->getParameter('site_name');
        $site_email = $this->container->getParameter('site_email');
        $first_name = $request->request->get('first_name');
        $last_name = $request->request->get('last_name');
        $email = $request->request->get('email');
        $username = $request->request->get('username');
        $role = $request->request->get('role');
        $status = $request->request->get('status');

        $new_password = $this->randomPassword();
        $password = password_hash($new_password, PASSWORD_BCRYPT);

        // check the database
        $sql = "SELECT `username` FROM `user` WHERE `username` = '$username'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();      
        while ($row = $result->fetch()) {
            $this->addFlash('danger','The username entered already exists');
            return $this->redirectToRoute('adduser',[
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'username' => $username,
            ]);
        }
        $sql = "SELECT `email` FROM `user` WHERE `email` = '$email'";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();      
        while ($row = $result->fetch()) {
            $this->addFlash('danger','The email entered already exists');
            return $this->redirectToRoute('adduser',[
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'username' => $username,
            ]);
        }

        // create the user
        $sql = "INSERT INTO `user`
        (`first_name`,`last_name`,`email`,`username`,`password`,`role`,`status`)
        VALUES
        ('$first_name','$last_name','$email','$username','$password','$role','$status')
        ";
        $result = $em->getConnection()->prepare($sql);
        $result->execute();  
        $userID = $em->getConnection()->lastInsertId();
        if ($userID == "") {
            $this->addFlash('danger','There was an error saving the user. Please remove any special symbols you might have inserted into the name fields.');
            return $this->redirectToRoute('adduser',[
                'first_name' => $first_name,
                'last_name' => $last_name,
                'email' => $email,
                'role' => $role,
                'status' => $status,
                'username' => $username,
            ]);            
        } else {
            // send welcome email
            $name = $first_name . " " . $last_name;
            $title = "Welcome to " . $site_name;
            $message = (new \Swift_Message($title))
                ->setFrom($site_email)
                ->setTo($email)
                ->setSubject($title)
                ->setBody(
                    $this->renderView(
                        'Emails/registration.html.twig',
                        array(
                            'name' => $name,
                            'username' => $username,
                            'password' => $new_password,
                            'site_name' => $site_name,
                            'site_url' => $site_url
                        )
                    ),
                    'text/html'
                )
            ;
            $this->get('mailer')->send($message);            
        }      

        $text = "The user was added.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('users');
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