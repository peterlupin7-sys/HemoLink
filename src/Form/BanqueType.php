<?php

namespace App\Form;

use App\Entity\Banque;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class BanqueType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('code', TextType::class, [
                'label' => 'Code',
                'help' => 'Format conseillé : BQ-COT-001',
            ])
            ->add('nom', TextType::class, ['label' => 'Nom de la banque'])
            ->add('type', ChoiceType::class, [
                'label' => 'Type d’établissement',
                'choices' => array_combine(Banque::TYPES, Banque::TYPES),
            ])
            ->add('adresse', TextType::class, ['label' => 'Adresse'])
            ->add('ville', TextType::class, ['label' => 'Ville'])
            ->add('departement', TextType::class, ['label' => 'Département'])
            ->add('latitude', NumberType::class, [
                'label' => 'Latitude',
                'scale' => 7,
                'constraints' => [new Assert\Range(min: -90, max: 90)],
                'help' => 'Relevez la position sur place : une banque mal placée sur la '
                    .'carte envoie les gens au mauvais endroit.',
            ])
            ->add('longitude', NumberType::class, [
                'label' => 'Longitude',
                'scale' => 7,
                'constraints' => [new Assert\Range(min: -180, max: 180)],
            ])
            ->add('telephone', TelType::class, ['label' => 'Téléphone'])
            ->add('telephoneUrgence', TelType::class, [
                'label' => 'Téléphone d’urgence',
                'required' => false,
            ])
            ->add('email', EmailType::class, ['label' => 'Courriel', 'required' => false])
            ->add('horaires', TextType::class, [
                'label' => 'Horaires',
                'required' => false,
                'attr' => ['placeholder' => 'Lun-Ven 07h30-18h00 · Urgences 24h/24'],
            ])
            ->add('capaciteStockage', IntegerType::class, [
                'label' => 'Capacité de stockage (poches)',
                'required' => false,
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => array_combine(Banque::STATUTS, Banque::STATUTS),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Banque::class]);
    }
}
