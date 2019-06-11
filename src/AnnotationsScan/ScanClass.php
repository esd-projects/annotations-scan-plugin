<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/5/14
 * Time: 11:30
 */

namespace ESD\Plugins\AnnotationsScan;


use Doctrine\Common\Annotations\CachedReader;
use ReflectionClass;

class ScanClass
{
    private $annotationMethod = [];
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

    public function addAnnotationClass($annClass, ReflectionClass $reflectionClass)
    {
        if (!array_key_exists($annClass, $this->annotationClass)) {
            $this->annotationClass[$annClass] = [];
        }
        if (!in_array($reflectionClass, $this->annotationClass[$annClass])) {
            $this->annotationClass[$annClass][] = $reflectionClass;
        }
    }

    public function addAnnotationMethod(string $annClass, ScanReflectionMethod $reflectionMethod)
    {
        if (!array_key_exists($annClass, $this->annotationMethod)) {
            $this->annotationMethod[$annClass] = [];
        }
        if (!in_array($reflectionMethod, $this->annotationMethod[$annClass])) {
            $this->annotationMethod[$annClass][] = $reflectionMethod;
        }
    }

    /**
     * 通过注解类名获取相关类
     * @param $annClass
     * @return ReflectionClass[]
     */
    public function findClassesByAnn($annClass)
    {
        return $this->annotationClass[$annClass] ?? [];
    }

    /**
     * @return CachedReader
     */
    public function getCachedReader(): CachedReader
    {
        return $this->cachedReader;
    }

    /**
     * 通过注解类名获取相关方法
     * @param $annClass
     * @return ScanReflectionMethod[]
     */
    public function findMethodsByAnn($annClass)
    {
        return $this->annotationMethod[$annClass] ?? [];
    }

    /**
     * @return array
     */
    public function getAnnotationMethod(): array
    {
        return $this->annotationMethod;
    }

    /**
     * 包含接口的
     * @param ReflectionClass $class
     * @param $annotationName
     * @return object|null
     */
    public function getClassAndInterfaceAnnotation(ReflectionClass $class, $annotationName)
    {
        $result = $this->cachedReader->getClassAnnotation($class, $annotationName);
        if ($result == null) {
            foreach ($class->getInterfaces() as $reflectionClass) {
                $result = $this->cachedReader->getClassAnnotation($reflectionClass, $annotationName);
                if ($result != null) {
                    return $result;
                }
            }
        }
        return $result;
    }

    /**
     * 包含接口的
     * @param ReflectionClass $class
     * @return array|mixed
     */
    public function getClassAndInterfaceAnnotations(ReflectionClass $class)
    {
        $result = $this->cachedReader->getClassAnnotations($class);
        foreach ($class->getInterfaces() as $reflectionClass) {
            $result = array_merge($this->cachedReader->getClassAnnotation($reflectionClass), $result);
        }
        return $result;
    }

    public function getMethodAndInterfaceAnnotation(\ReflectionMethod $method, $annotationName)
    {
        $result = $this->cachedReader->getMethodAnnotation($method, $annotationName);
        if ($result == null) {
            foreach ($method->getDeclaringClass()->getInterfaces() as $reflectionClass) {
                try {
                    $reflectionMethod = $reflectionClass->getMethod($method->getName());
                } catch (\Throwable $e) {
                    $reflectionMethod = null;
                }
                if ($reflectionMethod != null) {
                    $result = $this->cachedReader->getMethodAnnotation($reflectionMethod, $annotationName);
                    if ($result != null) {
                        return $result;
                    }
                }
            }
        }
        return $result;
    }

    public function getMethodAndInterfaceAnnotations(\ReflectionMethod $method)
    {
        $result = $this->cachedReader->getMethodAnnotations($method);
        foreach ($method->getDeclaringClass()->getInterfaces() as $reflectionClass) {
            try {
                $reflectionMethod = $reflectionClass->getMethod($method->getName());
            } catch (\Throwable $e) {
                $reflectionMethod = null;
            }
            if ($reflectionMethod != null) {
                $result = array_merge($result, $this->cachedReader->getMethodAnnotations($reflectionMethod));
            }
        }
        return $result;
    }
}