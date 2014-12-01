<?php
/**
 * @author  Almog Baku
 *          almog@GoDisco.net
 *          http://www.GoDisco.net/
 */

namespace GoDisco\AclTreeBundle\Security\Helper;

use Doctrine\DBAL\Driver\Connection;
use Doctrine\ORM\Query;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class AclUsersHelper
{
    /** @var  EntityManagerInterface */
    private $em;
    /** @var PropertyAccessorInterface */
    private $accessor;
    /** @var string */
    private $maskBuilderClass;
    /** @var Connection */
    private $aclConnection;

    /**
     * Constructor
     *
     * @param EntityManagerInterface $em
     * @param PropertyAccessorInterface $accessor
     * @param $aclConnection
     * @param string $maskBuilderClass - Mask builder class name
     */
    function __construct(EntityManagerInterface $em, PropertyAccessorInterface $accessor, Connection $aclConnection, $maskBuilderClass)
    {
        $this->em = $em;
        $this->accessor = $accessor;
        $this->aclConnection = $aclConnection;

        if(is_null($maskBuilderClass)) {
            $maskBuilderClass = 'Symfony\Component\Security\Acl\Permission\MaskBuilder';
        }
        if(!class_exists($maskBuilderClass)) {
            throw new \InvalidArgumentException("maskBuilderClass not exists");
        }
        $this->maskBuilderClass = $maskBuilderClass;
    }


    public function get($entity, $user_class, array $permissions = array("VIEW"))
    {
        //Build permissions-mask
        $builder = new $this->maskBuilderClass();
        foreach ($permissions as $permission) {
            $mask = constant(get_class($builder) . '::MASK_' . strtoupper($permission));
            $builder->add($mask);
        }
        $mask = $builder->get();

        //MetaData
        $entityMeta = $this->em->getClassMetadata(get_class($entity));
        $eid        = $this->accessor->getValue($entity, $entityMeta->getSingleIdentifierColumnName());
        $userMeta   = $this->em->getClassMetadata($user_class);
        $userTable  = $userMeta->table['name'];

        //get the database name of the ACL
        $database = $this->aclConnection->getDatabase();

        $sql = <<<SQL
SELECT id, u.username, email, mask FROM {$userTable} u INNER JOIN
  (
    SELECT REPLACE(s.identifier, :user_class_replace,'') username, obj.mask FROM {$database}.acl_security_identities s
        INNER JOIN (
            SELECT e.security_identity_id identity, e.mask FROM {$database}.acl_entries e
                INNER JOIN {$database}.acl_classes c ON ( e.class_id=c.id AND c.class_type=:entity_class )
                INNER JOIN {$database}.acl_object_identities o ON ( e.object_identity_id=o.id AND o.object_identifier=:eid )
            WHERE e.mask>=:mask
        ) obj ON obj.identity=s.id
        WHERE s.username=1
  ) i ON u.username=i.username
;
SQL;

        $rsm = new ResultSetMapping();
        $rsm->addScalarResult('id','id');
        $rsm->addScalarResult('username','username');
        $rsm->addScalarResult('mask','mask');
        $rsm->addScalarResult('email','email');

        $query = $this->em->createNativeQuery($sql, $rsm);
        $query->setParameter("user_class_replace", $user_class.'-');
        $query->setParameter("entity_class", get_class($entity));
        $query->setParameter("mask", $mask);
        $query->setParameter("eid", $eid);

        return $query->getResult();
    }
}