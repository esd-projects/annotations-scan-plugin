<?php
/**
 * Created by PhpStorm.
 * User: administrato
 * Date: 2019/5/14
 * Time: 10:49
 */

namespace ESD\Plugins\AnnotationsScan\Annotation;


use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("CLASS")
 */
class Component extends Annotation
{

}