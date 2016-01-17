<?php

namespace Mapbender\DataSourceBundle\Element\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;

/**
 * Class DataBaseType
 *
 * @package Mapbender\DataSourceBundle\Element\Type
 * @author  Andriy Oblivantsev <eslider@gmail.com>
 */
class DataBaseType extends AbstractType
{

    /**
     * Returns the name of this type.
     *
     * @return string The name of this type
     */
    public function getName()
    {
        /** Name binding? */
        return "source";
    }

    /**
     * @inheritdoc
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array());
    }

    /**
     * @inheritdoc
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $fields = array();
        for ($i = 0; $i < rand(3, 12); $i++) {
            $id       = rand(1, 1000);
            $key      = "field" . $id;
            $fields[] = array($key => "Field #" . $id);
        }
        $builder
            ->add('sqlField', 'choice', array(
                    'choices'  => $fields,
                    'required' => true,
                )
            );
    }
}