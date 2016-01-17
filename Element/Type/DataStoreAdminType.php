<?php

namespace Mapbender\DataSourceBundle\Element\Type;

use Doctrine\Bundle\DoctrineBundle\Registry;
use Mapbender\CoreBundle\Entity\Application;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilder;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class DataStoreAdminType
 *
 * @package Mapbender\DataStoreBundle\Element\Type
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataStoreAdminType extends AbstractType
{

    /**
     * @inheritdoc
     */
    public function getName()
    {
        return 'queryBuilder';
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'application' => null,
            'element'     => null,
        ));
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        /** @var Application $application */
        /** @var DataStoreAdminType $element */
        /** @var FormBuilder $builder */
        /** @var \AppKernel $kernel */
        /** @var Registry $doctrine */
        global $kernel;
        $application     = $options["application"];
        $element         = $options["element"];
        $container       = $kernel->getContainer();
        $doctrine        = $container->get("doctrine");
        $connectionNames = $doctrine->getConnectionNames();
        $connectionNames = array_combine(array_values($connectionNames), array_keys($connectionNames));
        $dataStores      = $container->hasParameter("dataStores") ? array_keys($container->getParameter("dataStores")) : array();

        $builder
            ->add('source', 'choice', array(
                    'choices'  => $dataStores,
                    'required' => true,
                    'empty_value' => null
                )
            );
            //->add('sqlField', 'choice', array(
            //        'choices'  => array(),
            //        'required' => true,
            //    )
            //)
            //->add('allowEdit', 'checkbox', array('required' => false))
            //->add('allowSql', 'checkbox', array('required' => false));
    }
}