<?php

namespace AppBundle\Controller;

use AppBundle\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Component\HttpFoundation\Request;

class AdminController extends Controller
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
    public function adduserAction()
    {
        /* user security needed in each controller function */
        $check = $this->get('customsecurity')->check_access('users');
        if ($check != "ok") {
            return($check);
        }
        /* end user security */

        return $this->render('admin/newuser.html.twig');
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

        $text = "The user was added.";
        $status = "success";          

        $this->addFlash($status,$text);
        return $this->redirectToRoute('users');
    }

}      