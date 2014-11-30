<?php
/**
 * @author  Almog Baku
 *          almog@GoDisco.net
 *          http://www.GoDisco.net/
 *
 * AclHelper - Based by gist authored by Anil (https://gist.github.com/mailaneel/1363377)
 */

namespace GoDisco\AclTreeBundle\Security\Helper;

use Doctrine\ORM\Query;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Symfony\Component\PropertyAccess\PropertyAccessorInterface;
use Doctrine\ORM\Query\ResultSetMapping;

class AclUsersHelper
{
    /** @var  ObjectManager */
    private $em;
    /** @var PropertyAccessorInterface */
    private $accessor;

    private $maskBuilderClass;
    private $aclConnection;

    /**
     * Constructor
     *
     * @param RegistryInterface $doctrine
     * @param $aclConnection
     */
    function __construct(RegistryInterface $doctrine, PropertyAccessorInterface $accessor, $aclConnection, $maskBuilderClass = 'Symfony\Component\Security\Acl\Permission\MaskBuilder')
    {
        $this->em = $doctrine->getManager();
        $this->accessor = $accessor;
        $this->aclConnection = $doctrine->getConnection($aclConnection);
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