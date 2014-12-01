<?php
/**
 * @author  Almog Baku
 *          almog@GoDisco.net
 *          http://www.GoDisco.net/
 */

namespace GoDisco\AclTreeBundle\Security\ORM\SqlWalker;

use Doctrine\ORM\Query\SqlWalker;
use Doctrine\ORM\Query;

class AclTreeWalker extends SqlWalker
{
    /**
     * Walks down a FromClause AST node, thereby generating the appropriate SQL.
     *
     * @param Query\AST\FromClause $fromClause
     * @return string SQL
     */
    public function walkFromClause($fromClause)
    {
        $sql = parent::walkFromClause($fromClause);

        $extraQuery = $this->getQuery()->getHint('acl.query');
        $classesMap = $this->getQuery()->getHint('acl.tree.classes.map');
        $rootTableAlias = $this->getSQLTableAlias(
            $classesMap['metadata']->table['name'],
            $this->getQuery()->getHint('acl.original.dqlAlias')
        );
        $joinParents = $this->joinParents($classesMap, $rootTableAlias);
        $ON = $this->buildOn($classesMap, $rootTableAlias);

        return  $sql . ' ' . $joinParents . " INNER JOIN ({$extraQuery}) acl_ ON {$ON}";
    }

    /**
     * Creating joins to the parents
     *
     * @param $classesMap
     * @param $alias
     * @param int $lvl
     * @return string
     */
    private function joinParents($classesMap, $alias, $lvl=0) {
        if(!isset($classesMap['parent'])) return "";

        $field = $classesMap['field'];
        if(isset($classesMap['metadata']->associationMappings[$field])) {
            $field = $classesMap['metadata']->associationMappings[$field]['targetToSourceKeyColumns']['id'];
        }

        $joinTable = $classesMap['parent']['metadata']->table['name'];
        $joinID = $classesMap['parent']['metadata']->getSingleIdentifierColumnName();

        return "LEFT JOIN {$joinTable} jn{$lvl} ON {$alias}.{$field} = jn{$lvl}.{$joinID} " . $this->joinParents($classesMap['parent'], "jn{$lvl}", $lvl+1);
    }

    /**
     * Building ON phase for joining the AclQuery with the original query
     * @param $classesMap
     * @param $alias
     * @param int $lvl
     * @return string
     */
    private function buildOn($classesMap, $alias, $lvl=0) {
        $class = $this->escapeNamespace($classesMap['class']);
        $ID = $classesMap['metadata']->getSingleIdentifierColumnName();
        $sql = "( acl_.class = \"{$class}\" AND acl_.id={$alias}.{$ID} )";

        if(!isset($classesMap['parent'])) {
            return $sql;
        }
        return $sql . " OR " . $this->buildOn($classesMap['parent'], "jn{$lvl}", $lvl+1);
    }

    /**
     * Escaping namespace
     *
     * @param $namespace
     * @return string
     */
    private function escapeNamespace($namespace) {
        return str_replace('\\', '\\\\', $namespace);
    }
}