<?php
/**
 * Created by PhpStorm.
 * User: administrato
 * Date: 2019/5/14
 * Time: 11:29
 */

namespace ESD\Plugins\AnnotationsScan;


use ESD\BaseServer\Plugins\Event\Event;

class ScanEvent extends Event
{
    const type = "ScanEvent";
    public function __construct()
    {
        parent::__construct(self::type, "");
    }
}