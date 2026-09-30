<?php

namespace App\Form;

use App\Entity\Demandeur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class DemandeurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('type', ChoiceType::class, [
                'label' => 'Vous êtes',
                'choices' => [
                    'Un établissement de soins' => 'ETABLISSEMENT',
                    'Un particulier (don dirigé)' => 'PARTICULIER',
                ],
                'expanded' => true,
            ])
            ->add('nom', TextType::class, [
                'label' => 'Nom de l’établissement ou de la famille',
                'constraints' => [new Assert\NotBlank(message: 'Ce champ est obligatoire.')],
            ])
            ->add('responsable', TextType::class, [
                'label' => 'Personne à contacter',
                'required' => false,
            ])
            ->add('telephone', TelType::class, [
                'label' => 'Téléphone',
                'help' => 'C’est sur ce numéro que partira le SMS de confirmation.',
                'attr' => ['placeholder' => '+229 97 00 00 00', 'inputmode' => 'tel'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le téléphone est indispensable au suivi.'),
                    new Assert\Length(min: 8, max: 25,
                        minMessage: 'Numéro trop court pour être valide.'),
                ],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Adresse électronique',
                'required' => false,
            ])
            ->add('ville', TextType::class, ['label' => 'Ville', 'required' => false])
            ->add('numeroAgrement', TextType::class, [
                'label' => 'Numéro d’agrément (établissements)',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Demandeur::class]);
    }
}
