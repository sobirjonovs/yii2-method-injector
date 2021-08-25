<?php

declare(strict_types=1);

namespace yii2\dependency;

use ReflectionException;
use ReflectionMethod;
use yii\base\Exception as WebException;
use yii\base\InvalidRouteException;
use yii\console\Exception;
use yii\web\Application;

/**
 * Add first to be injected classes followed by parameters
 * @package common\helpers
 */
trait MethodInjector
{
    /**
     * @throws ReflectionException
     * @throws Exception|WebException
     * @throws InvalidRouteException
     * @return string|int
     */
    public function runAction($id, $params = [])
    {
        $methodName = "action" . ucfirst($id);
        $method = new ReflectionMethod($this, $methodName);
        return parent::runAction($id, array_merge($this->inject($method->getParameters()), $params));
    }

    /**
     * @param array $parameters
     * @return array
     */
    private function inject(array $parameters): array
    {
        $invokables = [];
        foreach ($parameters as $parameter) {
            if ($dependency = (string) $parameter->getType()) {
                $className = substr(strrchr($dependency, "\\"), 1);
                if ($className !== ucfirst($className)) continue;

                $this->setInvokable($invokables, $parameter, $dependency);
            }
        }
        return $invokables;
    }

    /**
     * @param array $invokables
     * @param $parameter
     * @param $dependency
     */
    private function setInvokable(array &$invokables, $parameter, $dependency)
    {
        $environment = current($this->getModules());

        if ($environment instanceof Application) {
            $invokables[$parameter->getName()] = new $dependency;
        } else {
            $invokables[] = new $dependency;
        }
    }
}