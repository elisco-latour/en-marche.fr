<?php

namespace AppBundle\Admin;

use AppBundle\Form\PurifiedTextareaType;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Datagrid\DatagridMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class ExecutiveOfficePersonAdmin extends AbstractAdmin
{
    protected $datagridValues = [
        '_page' => 1,
        '_per_page' => 32,
        '_sort_order' => 'DESC',
        '_sort_by' => 'createdAt',
    ];

    protected function configureFormFields(FormMapper $formMapper)
    {
        $formMapper
            ->with('Général', ['class' => 'col-md-6'])
                ->add('firstName', TextType::class, [
                    'label' => 'Prénom',
                    'filter_emojis' => true,
                ])
                ->add('lastName', TextType::class, [
                    'label' => 'Nom',
                    'filter_emojis' => true,
                ])
                ->add('job', TextType::class, [
                    'label' => 'Poste',
                    'filter_emojis' => true,
                ])
                ->add('description', TextType::class, [
                    'label' => 'Description',
                    'required' => false,
                    'filter_emojis' => true,
                ])
                ->add('content', PurifiedTextareaType::class, [
                    'label' => 'Contenu',
                    'required' => false,
                    'attr' => ['class' => 'ck-editor'],
                    'purifier_type' => 'enrich_content',
                    'filter_emojis' => true,
                ])

            ->end()
            ->with('Paramètres', ['class' => 'col-md-6'])
                ->add('executiveOfficer', null, [
                    'label' => 'Délégué Général',
                ])
                ->add('published', null, [
                    'label' => 'Publié',
                ])
            ->end()
            ->with('Réseaux sociaux', ['class' => 'col-md-6'])
                ->add('facebookProfile', TextType::class, [
                    'label' => 'Facebook',
                    'required' => false,
                    'filter_emojis' => true,
                ])
                ->add('twitterProfile', TextType::class, [
                    'label' => 'Twitter',
                    'required' => false,
                    'filter_emojis' => true,
                ])
                ->add('instagramProfile', TextType::class, [
                    'label' => 'Instagram',
                    'required' => false,
                    'filter_emojis' => true,
                ])
                ->add('linkedInProfile', TextType::class, [
                    'label' => 'Instagram',
                    'required' => false,
                    'filter_emojis' => true,
                ])
            ->end()
        ;
    }

    protected function configureDatagridFilters(DatagridMapper $datagridMapper)
    {
        $datagridMapper
            ->add('firstName', null, [
                'label' => 'Prénom',
                'show_filter' => true,
            ])
            ->add('lastName', null, [
                'label' => 'Nom',
            ])
            ->add('job', null, [
                'label' => 'Poste',
            ])
        ;
    }

    protected function configureListFields(ListMapper $listMapper)
    {
        $listMapper
            ->addIdentifier('firstName', null, [
                'label' => 'Nom',
            ])
            ->addIdentifier('lastName', null, [
                'label' => 'Prénom',
            ])
            ->addIdentifier('slug', null, [
                'label' => 'Slug',
            ])
            ->add('job', null, [
                'label' => 'Poste',
            ])
            ->add('executiveOfficer', null, [
                'label' => 'Délégué général',
                'editable' => true,
            ])
            ->add('published', null, [
                'label' => 'Publié',
                'editable' => true,
            ])
            ->add('updatedAt', null, [
                'label' => 'Dernière mise à jour',
            ])
            ->add('_action', null, [
                'actions' => [
                    'edit' => [],
                    'delete' => [],
                ],
            ])
        ;
    }
}
