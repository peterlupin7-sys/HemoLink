<?php

namespace App\Form;

use App\Entity\Demandeur;
use App\Entity\Precommande;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class PrecommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('demandeur', DemandeurType::class, [
                'label' => false,
                'data_class' => Demandeur::class,
            ])
            ->add('urgence', ChoiceType::class, [
                'label' => 'Niveau d’urgence',
                'choices' => [
                    'Normale — sous 48 heures' => 'NORMALE',
                    'Urgente — dans la journée' => 'URGENTE',
                    'Vitale — pronostic engagé' => 'VITALE',
                ],
                'expanded' => true,
                'help' => 'L’urgence vitale remonte en haut de la liste de la banque.',
            ])
            ->add('dateSouhaitee', DateType::class, [
                'label' => 'Date de retrait souhaitée',
                'widget' => 'single_text',
                'constraints' => [new Assert\GreaterThanOrEqual('today',
                    message: 'La date de retrait ne peut pas être dans le passé.')],
            ])
            ->add('motif', TextType::class, [
                'label' => 'Motif de la demande',
                'required' => false,
                'attr' => ['placeholder' => 'Césarienne programmée, drépanocytose, accident…'],
            ])
            ->add('referencePatient', TextType::class, [
                'label' => 'Référence du dossier patient',
                'required' => false,
                'help' => 'Une référence anonyme, jamais le nom du patient.',
            ])
            ->add('lignes', CollectionType::class, [
                'label' => 'Poches demandées',
                'entry_type' => LignePrecommandeType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'prototype' => true,
                'constraints' => [new Assert\Count(min: 1,
                    minMessage: 'Indiquez au moins un groupe sanguin.')],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Precommande::class,
            'csrf_protection' => true,
        ]);
    }
}
