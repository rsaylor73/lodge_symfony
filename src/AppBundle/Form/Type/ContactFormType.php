<?php

namespace AppBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;

class ContactFormType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('from', EmailType::class, [
            		'label' => 'Your Email Address',
            	])
            ->add('message', TextareaType::class, [
            	// This is just an HTML attribute
            	'attr' => [
            		'rows' => 10,
            	]
            ])
            ->add('send', SubmitType::class, [
            		// Another HTML attribute
            		'attr' => [
            			'class' => 'btn btn-success btn-lg btn-block',
            		]
            	])
        ;
    }
}