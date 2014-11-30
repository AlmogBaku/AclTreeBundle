<?php
/**
 * @author  Almog Baku
 *          almog@GoDisco.net
 *          http://www.GoDisco.net/
 *
 * 7/4/14 1:43 AM
 */
namespace GoDisco\AclTreeBundle\Annotation;

/**
 * @Annotation
 * @Target("PROPERTY")
 */
final class AclParent
{
    public $parent;
}
