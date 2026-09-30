<?php

namespace App\Form;

use App\Entity\GroupeSanguin;
use App\Entity\LignePrecommande;
use App\Repository\GroupeSanguinRepository;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class LignePrecommandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('groupeSanguin', EntityType::class, [
                'label' => 'Groupe sanguin',
                'class' => GroupeSanguin::class,
                'choice_label' => 'code',
                'query_builder' => static fn (GroupeSanguinRepository $r) => $r
                    ->createQueryBuilder('g')->orderBy('g.rang', 'ASC'),
                'placeholder' => 'Choisir un groupe',
            ])
            ->add('quantiteDemandee', IntegerType::class, [
                'label' => 'Nombre de poches',
                'attr' => ['min' => 1, 'max' => 50, 'inputmode' => 'numeric'],
                'constraints' => [
                    new Assert\Positive(message: 'Indiquez au moins une poche.'),
                    new Assert\LessThanOrEqual(50,
                        message: 'Au-delà de 50 poches, contactez directement la banque.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => LignePrecommande::class]);
    }
}
