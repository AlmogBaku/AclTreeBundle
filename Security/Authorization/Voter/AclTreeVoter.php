<?php
/**
 * Authored by  AlmogBaku
 *              almog@GoDisco.net
 *              http://www.GoDisco.net/
 *
 * 7/1/14 11:42 PM
 */

namespace GoDisco\AclTreeBundle\Security\Authorization\Voter;

use GoDisco\AclTreeBundle\Annotation\AclParentReader;
use Symfony\Component\Security\Acl\Voter\AclVoter;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

use Symfony\Component\Security\Acl\Model\AclProviderInterface;
use Symfony\Component\Security\Acl\Permission\PermissionMapInterface;
use Symfony\Component\Security\Acl\Model\SecurityIdentityRetrievalStrategyInterface;
use Symfony\Component\Security\Acl\Model\ObjectIdentityRetrievalStrategyInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Doctrine\Common\Persistence\ObjectManager;
use Doctrine\Common\Persistence\Mapping\MappingException;

class AclTreeVoter extends AclVoter
{
    /** @var ObjectManager */
    private $em;
    /** @var AclParentReader */
    private $aclReader;

    public function __construct(
        AclProviderInterface $aclProvider,
        ObjectIdentityRetrievalStrategyInterface $oidRetrievalStrategy,
        SecurityIdentityRetrievalStrategyInterface $sidRetrievalStrategy,
        PermissionMapInterface $permissionMap,
        LoggerInterface $logger = null,
        $allowIfObjectIdentityUnavailable = true,
        RegistryInterface $doctrine,
        AclParentReader $aclReader
    ) {
        $this->aclReader = $aclReader;
        $this->em = $doctrine->getManager();
        parent::__construct($aclProvider,$oidRetrievalStrategy,$sidRetrievalStrategy,$permissionMap,$logger,$allowIfObjectIdentityUnavailable);
    }

    public function vote(TokenInterface $token, $obj, array $attributes)
    {
        if (!$this->supportsClass(get_class($obj)))
            return self::ACCESS_ABSTAIN;

        $aclTree = $this->aclReader->getAclEntityTree($obj);
        $abstain = true;
        foreach($aclTree as $node) {
            $aclVote = parent::vote($token, $node, $attributes);
            if($aclVote == self::ACCESS_GRANTED) return self::ACCESS_GRANTED;
            if($aclVote == self::ACCESS_DENIED) $abstain=false;
        }
        if($abstain) return self::ACCESS_ABSTAIN;
        else return self::ACCESS_DENIED;
    }

    public function supportsClass($class)
    {
        try {
            $this->em->getClassMetadata($class);
            return true;
        } catch(MappingException $e) {
            return false;
        }
    }
} 