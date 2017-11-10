<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class LoginType extends AbstractType
{
    /**
     * {@inheritdoc}
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->setMethod('POST')
            ->add('_username', TextType::class,[
                'attr' => [
                    'placeholder' => 'Your registered username'
                    ],
                'label' => 'Username'
            ])
            ->add('_password', PasswordType::class, [
                'attr' => [
                    'placeholder' => 'Your registered password'
                    ],
                'label' => 'Password'            
            ])
            ->add('login', SubmitType::class, [
                'attr' => [
                    'class' => 'btn btn-success btn-lg btn-block'
                ]
            ])
        ;
    }
}