<?php
/**
 * Created by PhpStorm.
 * User: administrato
 * Date: 2019/5/14
 * Time: 11:30
 */

namespace ESD\Plugins\AnnotationsScan;


use Doctrine\Common\Annotations\CachedReader;

class ScanClass
{
    private $annotationClass = [];
    /**
     * @var CachedReader
     */
    private $cachedReader;

    public function __construct(CachedReader $cachedReader)
    {
        $this->cachedReader = $cachedReader;
    }

    /**
     * @return array
     */
    public function getAnnotationClass(): array
    {
        return $this->annotationClass;
    }

    public function addAnnotationClass($annClass, $class)
    {
        if (isset($this->annotationClass[$annClass])) {
            $this->annotationClass[$annClass] = [];
        }
        $this->annotationClass[$annClass][] = $class;
    }

    /**
     * 获取注解类
     * @param $annClass
     * @return mixed|null
     */
    public function getAnnotationClasses($annClass)
    {
        return $this->annotationClass[$annClass] ?? null;
    }

    /**
     * @return CachedReader
     */
    public function getCachedReader(): CachedReader
    {
        return $this->cachedReader;
    }
}