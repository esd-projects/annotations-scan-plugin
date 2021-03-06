<?php
/**
 * Created by PhpStorm.
 * User: 白猫
 * Date: 2019/5/13
 * Time: 17:21
 */

namespace ESD\Plugins\AnnotationsScan;


use DI\DependencyException;
use Doctrine\Common\Annotations\Annotation;
use Doctrine\Common\Annotations\AnnotationReader;
use Doctrine\Common\Annotations\CachedReader;
use Doctrine\Common\Cache\ArrayCache;
use Doctrine\Common\Cache\FilesystemCache;
use ESD\Core\Context\Context;
use ESD\Core\Exception;
use ESD\Core\PlugIn\AbstractPlugin;
use ESD\Core\PlugIn\PluginInterfaceManager;
use ESD\Core\Plugins\Logger\GetLogger;
use ESD\Core\Server\Server;
use ESD\Plugins\AnnotationsScan\Annotation\Component;
use ESD\Plugins\Aop\AopPlugin;
use ReflectionClass;
use ReflectionException;

class AnnotationsScanPlugin extends AbstractPlugin
{
    use GetLogger;
    /**
     * @var AnnotationsScanConfig|null
     */
    private $annotationsScanConfig;

    /**
     * @var CachedReader
     */
    private $cacheReader;
    /**
     * @var ScanClass
     */
    private $scanClass;

    /**
     * AnnotationsScanPlugin constructor.
     * @param AnnotationsScanConfig|null $annotationsScanConfig
     * @throws DependencyException
     * @throws ReflectionException
     * @throws \DI\NotFoundException
     */
    public function __construct(?AnnotationsScanConfig $annotationsScanConfig = null)
    {
        parent::__construct();
        if ($annotationsScanConfig == null) {
            $annotationsScanConfig = new AnnotationsScanConfig();
        }
        $this->annotationsScanConfig = $annotationsScanConfig;
        $this->atAfter(AopPlugin::class);
    }

    /**
     * @param PluginInterfaceManager $pluginInterfaceManager
     * @return mixed|void
     * @throws DependencyException
     * @throws Exception
     * @throws ReflectionException
     * @throws \DI\NotFoundException
     */
    public function onAdded(PluginInterfaceManager $pluginInterfaceManager)
    {
        parent::onAdded($pluginInterfaceManager);
        $pluginInterfaceManager->addPlug(new AopPlugin());
    }

    /**
     * 获取插件名字
     * @return string
     */
    public function getName(): string
    {
        return "AnnotationsScan";
    }

    private function scanPhp($dir, &$files = null)
    {
        //定义一个数组
        if ($files == null) {
            $files = array();
        }
        //检测是否存在文件
        if (is_dir($dir)) {
            //打开目录
            if ($handle = opendir($dir)) {
                //返回当前文件的条目
                while (($file = readdir($handle)) !== false) {
                    //去除特殊目录
                    if ($file != "." && $file != "..") {
                        //判断子目录是否还存在子目录
                        if (is_dir($dir . "/" . $file)) {
                            //递归调用本函数，再次获取目录
                            $this->scanPhp($dir . "/" . $file, $files);
                        } else {
                            //获取目录数组
                            if (pathinfo($file, PATHINFO_EXTENSION) == "php") {
                                $files[] = $dir . "/" . $file;
                            }
                        }
                    }
                }
                //关闭文件夹
                closedir($handle);
                //返回文件夹数组
                return $files;
            }
        } else {
            return $files;
        }
    }

    /**
     * 在服务启动前
     * @param Context $context
     * @return mixed
     */
    public function beforeServerStart(Context $context)
    {
        return;
    }

    /**
     * 从PHP中的文件内容获取完全限定的类名
     * @param $path_to_file
     * @return mixed|string|null
     */
    public function getClassFromFile($path_to_file)
    {
        //Grab the contents of the file
        $contents = file_get_contents($path_to_file);

        //Start with a blank namespace and class
        $namespace = $class = "";

        //Set helper values to know that we have found the namespace/class token and need to collect the string values after them
        $getting_namespace = $getting_class = false;

        //Go through each token and evaluate it as necessary
        foreach (token_get_all($contents) as $token) {

            //If this token is the namespace declaring, then flag that the next tokens will be the namespace name
            if (is_array($token) && $token[0] == T_NAMESPACE) {
                $getting_namespace = true;
            }

            //If this token is the class declaring, then flag that the next tokens will be the class name
            if (is_array($token) && ($token[0] == T_CLASS || $token[0] == T_INTERFACE)) {
                $getting_class = true;
            }

            //While we're grabbing the namespace name...
            if ($getting_namespace === true) {

                //If the token is a string or the namespace separator...
                if (is_array($token) && in_array($token[0], [T_STRING, T_NS_SEPARATOR])) {

                    //Append the token's value to the name of the namespace
                    $namespace .= $token[1];

                } else if ($token === ';') {

                    //If the token is the semicolon, then we're done with the namespace declaration
                    $getting_namespace = false;

                }
            }

            //While we're grabbing the class name...
            if ($getting_class === true) {

                //If the token is a string, it's the name of the class
                if (is_array($token) && $token[0] == T_STRING) {

                    //Store the token's value as the class name
                    $class = $token[1];

                    //Got what we need, stope here
                    break;
                }
            }
        }
        if (empty($class)) return null;
        //Build the fully-qualified class name and return it
        return $namespace ? $namespace . '\\' . $class : $class;
    }

    /**
     * 在进程启动前
     * @param Context $context
     * @throws DependencyException
     * @throws Exception
     * @throws ReflectionException
     * @throws \DI\NotFoundException
     * @throws \Doctrine\Common\Annotations\AnnotationException
     * @throws \ESD\Core\Plugins\Config\ConfigException
     */
    public function beforeProcessStart(Context $context)
    {
        //默认添加src目录
        $this->annotationsScanConfig->addIncludePath(Server::$instance->getServerConfig()->getSrcDir());
        $this->annotationsScanConfig->merge();
        if ($this->annotationsScanConfig->isFileCache()) {
            $cache = new FilesystemCache(
                Server::$instance->getServerConfig()->getCacheDir() . DIRECTORY_SEPARATOR . '_annotations_scan' . DIRECTORY_SEPARATOR,
                '.annotations.cache');
        } else {
            $cache = new ArrayCache();
        }
        $this->cacheReader = new CachedReader(new AnnotationReader(), $cache);
        $this->scanClass = new ScanClass($this->cacheReader);
        $this->setToDIContainer(CachedReader::class, $this->cacheReader);
        $this->setToDIContainer(ScanClass::class, $this->scanClass);
        $paths = array_unique($this->annotationsScanConfig->getIncludePaths());
        foreach ($paths as $path) {
            $files = $this->scanPhp($path);
            foreach ($files as $file) {
                $class = $this->getClassFromFile($file);
                if ($class != null) {
                    if (interface_exists($class) || class_exists($class)) {
                        $reflectionClass = new ReflectionClass($class);
                        $has = $this->cacheReader->getClassAnnotation($reflectionClass, Component::class);
                        //只有继承Component注解的才会被扫描
                        if ($has != null) {
                            //查看类上的注解
                            $annotations = $this->cacheReader->getClassAnnotations($reflectionClass);
                            foreach ($annotations as $annotation) {
                                $annotationClass = get_class($annotation);
                                if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                    $this->debug("Find a class annotation $annotationClass in $class");
                                }
                                $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                                $annotationClass = get_parent_class($annotation);
                                if ($annotationClass != Annotation::class) {
                                    if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                        $this->debug("Find a class annotation $annotationClass in $class");
                                    }
                                    $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                                }
                            }
                            //添加类接口中的注解
                            $reflectionInterfaces = $reflectionClass->getInterfaces();
                            foreach ($reflectionInterfaces as $reflectionInterface) {
                                $annotations = $this->cacheReader->getClassAnnotations($reflectionInterface);
                                foreach ($annotations as $annotation) {
                                    $annotationClass = get_class($annotation);
                                    if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                        $this->debug("Find a class annotation $annotationClass in $class");
                                    }
                                    $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                                    $annotationClass = get_parent_class($annotation);
                                    if ($annotationClass != Annotation::class) {
                                        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                            $this->debug("Find a class annotation $annotationClass in $class");
                                        }
                                        $this->scanClass->addAnnotationClass($annotationClass, $reflectionClass);
                                    }
                                }
                            }
                            //查看方法上的注解
                            foreach ($reflectionClass->getMethods() as $reflectionMethod) {
                                $scanReflectionMethod = new ScanReflectionMethod($reflectionClass, $reflectionMethod);

                                foreach ($reflectionMethod->getDeclaringClass()->getInterfaces() as $reflectionInterface) {
                                    try {
                                        $reflectionInterfaceMethod = $reflectionInterface->getMethod($reflectionMethod->getName());
                                    } catch (\Throwable $e) {
                                        $reflectionInterfaceMethod = null;
                                    }
                                    if ($reflectionInterfaceMethod != null) {
                                        $annotations = $this->cacheReader->getMethodAnnotations($reflectionInterfaceMethod);
                                        foreach ($annotations as $annotation) {
                                            $annotationClass = get_class($annotation);
                                            if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                                $this->debug("Find a method annotation $annotationClass in $class::$reflectionMethod->name");
                                            }
                                            $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                                            $annotationClass = get_parent_class($annotation);
                                            if ($annotationClass != Annotation::class) {
                                                if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                                    $this->debug("Find a method annotation $annotationClass in $class::$reflectionMethod->name");
                                                }
                                                $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                                            }
                                        }
                                    }
                                }


                                $annotations = $this->cacheReader->getMethodAnnotations($reflectionMethod);
                                foreach ($annotations as $annotation) {
                                    $annotationClass = get_class($annotation);
                                    if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                        $this->debug("Find a method annotation $annotationClass in $class::$reflectionMethod->name");
                                    }
                                    $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                                    $annotationClass = get_parent_class($annotation);
                                    if ($annotationClass != Annotation::class) {
                                        if (Server::$instance->getProcessManager()->getCurrentProcess()->getProcessId() == 0) {
                                            $this->debug("Find a method annotation $annotationClass in $class::$reflectionMethod->name");
                                        }
                                        $this->scanClass->addAnnotationMethod($annotationClass, $scanReflectionMethod);
                                    }
                                }
                            }
                        }
                    }
                }
            }
        }
        $this->ready();
    }
}