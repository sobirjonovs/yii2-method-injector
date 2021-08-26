<?php

declare(strict_types=1);

namespace yii2\dependency;

use ReflectionException;
use ReflectionMethod;
use yii\base\Exception as WebException;
use yii\base\InvalidRouteException;
use yii\console\Application as ConsoleApplication;
use yii\console\Exception;
use yii\web\Application;

/**
 * MethodInjector trait
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
        $injections = $this->inject($method->getParameters());
        if ( isset($injections['parameter']) ) {
            $params = $this->positionParameters($injections['parameter'], $params);
        }
        $params = isset($injections['injection']) ? $injections['injection'] + $params : $params;
        return parent::runAction($id, $params);
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
            } else {
                $this->setInvokable($invokables, $parameter);
            }
        }
        return $invokables;
    }

    /**
     * @param array $invokables
     * @param $parameter
     * @param $dependency
     */
    private function setInvokable(array &$invokables, $parameter, $dependency = null)
    {
        $environment = current($this->getModules());

        if ($dependency === null) {
            if ($environment instanceof Application) {
                $invokables['parameter'][$parameter->getName()] = $parameter->isOptional() ? $parameter->getDefaultValue() : null;
            } else {
                $invokables['parameter'][$parameter->getPosition()] = $parameter->getName();
            }
        } elseif ($environment instanceof Application) {
            $invokables['injection'][$parameter->getName()] = new $dependency;
        } else {
            $invokables['injection'][$parameter->getPosition()] = new $dependency;
        }
    }

    /**
     * @param array $injecteds
     * @param array $params
     * @return array
     */
    public function positionParameters(array $injecteds, array $params): array
    {
        $environment = current($this->getModules());

        if ($environment instanceof Application) {
            $params = array_replace($injecteds, $params);
        } elseif ($environment instanceof ConsoleApplication) {
            $oldParams = $params;
            $params = array_filter($params, function ($par) {
                return !is_string($par);
            }, ARRAY_FILTER_USE_KEY);
            $params = array_pad($params, count($injecteds), null);
            $params = array_combine(array_keys($injecteds), $params);
            $params = array_replace($params, $oldParams);
        }

        return $params;
    }
}
