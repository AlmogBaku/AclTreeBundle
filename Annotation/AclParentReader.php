<?php
/**
 * @author  Almog Baku
 *          almog@GoDisco.net
 *          http://www.GoDisco.net/
 *
 * 7/4/14 1:43 AM
 */


namespace GoDisco\AclTreeBundle\Annotation;

use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\Mapping\ClassMetadata;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Doctrine\Common\Annotations\Reader;

class AclParentReader
{
    /** @var PropertyAccessorInterface */
    private $accessor;
    /** @var Reader */
    private $reader;
    /** @var ObjectManager */
    private $em;

    public function __construct(RegistryInterface $doctrine, Reader $reader, PropertyAccessorInterface $accessor)
    {
        $this->em = $doctrine->getManager();
        $this->reader = $reader;
        $this->accessor = $accessor;
    }

    /**
     * @param {Entity/Field/Class} $obj
     * @return array of trees
     */
    public function getAclEntityTree($obj)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->em->getClassMetadata(get_class($obj));
        foreach($metadata->getReflectionClass()->getProperties() as $property)
            if($this->reader->getPropertyAnnotation($property, "GoDisco\\AclTreeBundle\\Annotation\\AclParent")) {
                $parent = $this->accessor->getValue($obj, $property->getName());
                if(is_null($parent)) return array($obj);

                return array_merge(array($obj), $this->getAclEntityTree($parent));
            }

        return array($obj);
    }

    /**
     * @param {Entity/Field/Class} $class
     * @return array - meta tree
     */
    public function getAclMetaTree($class)
    {
        /** @var ClassMetadata $metadata */
        $metadata = $this->em->getClassMetadata($class);
        foreach($metadata->getReflectionClass()->getProperties() as $property)
            if($this->reader->getPropertyAnnotation($property, "GoDisco\\AclTreeBundle\\Annotation\\AclParent"))
                return array(
                    "class" => $class,
                    "field" => $property->getName(),
                    "metadata" => $metadata,
                    "parent" => $this->getAclMetaTree($metadata->associationMappings[$property->getName()]['targetEntity'])
                );

        return array(
            "class"=>$class,
            "metadata" => $metadata
        );
    }

    /**
     *
     * @param $classesMap
     * @return array
     */
    public function classesMap_to_list($classesMap)
    {
        if(isset($classesMap['parent']))
            return array_merge(
                array($classesMap['class']),
                $this->classesMap_to_list($classesMap['parent'])
            );

        return array($classesMap['class']);
    }
} 