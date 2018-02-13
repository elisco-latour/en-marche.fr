<?php

namespace AppBundle\Admin;

use AppBundle\Form\WebHookCallbackType;
use AppBundle\Repository\WebHookRepository;
use Sonata\AdminBundle\Admin\AbstractAdmin;
use Sonata\AdminBundle\Datagrid\ListMapper;
use Sonata\AdminBundle\Form\FormMapper;
use Sonata\AdminBundle\Form\Type\CollectionType;
use Sonata\AdminBundle\Route\RouteCollection;
use Symfony\Component\Form\Extension\Core\Type\TextType;

class WebHookAdmin extends AbstractAdmin
{
    private $repository;

    public function __construct(
        string $code,
        string $class,
        string $baseControllerName,
        WebHookRepository $repository
    ) {
        parent::__construct($code, $class, $baseControllerName);

        $this->repository = $repository;
    }

    /**
     * {@inheritdoc}
     */
    public function getDatagrid()
    {
        if (!$this->datagrid) {
            $this->datagrid = new WebHookDatagrid(parent::getDatagrid(), $this->repository);
        }

        return $this->datagrid;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureRoutes(RouteCollection $collection)
    {
        $collection->clearExcept(['list', 'edit']);
    }

    /**
     * {@inheritdoc}
     */
    protected function configureFormFields(FormMapper $form)
    {
        $form
            ->add('event', TextType::class, [
                'disabled' => true,
                'label' => 'Nom d\'évènement',
            ])
            ->add('callbacks', CollectionType::class, [
                'label' => 'Liste des callbacks',
                'entry_type' => WebHookCallbackType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'by_reference' => false,
                'error_bubbling' => false,
            ])
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function configureListFields(ListMapper $list)
    {
        $list
            ->add('event', null, [
                'label' => 'Nom d\'évènement',
            ])
            ->add('callbacks', null, [
                'label' => 'Liste des callbacks',
                'sortable' => false,
                'template' => 'admin/webHook/_list_callbacks.html.twig',
            ])
            ->add('_action', null, [
                'label' => 'Actions',
                'actions' => [
                    'edit' => [],
                ],
            ])
        ;
    }
}