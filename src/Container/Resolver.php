<?php
declare(strict_types=1);

namespace Branch\Container;

use Branch\Interfaces\Container\ContainerInterface;
use Branch\Interfaces\Container\DefinitionInfoInterface;
use Branch\Interfaces\Container\ResolverInterface;

class Resolver implements ResolverInterface
{
    protected ContainerInterface $container;

    protected DefinitionInfoInterface $definitionInfo;

    public function __construct(
        ContainerInterface $container,
        DefinitionInfoInterface $definitionInfo
    )
    {
        $this->container = $container;
        $this->definitionInfo = $definitionInfo;
    }

    public function resolve($definition)
    {
        $resolved = null;

        if ($this->definitionInfo->isClosureDefinition($definition)) {
            $resolved = call_user_func($definition, $this->container);
        } elseif ($this->definitionInfo->isArrayObjectDefinition($definition)) {
            $resolved = $this->resolveObject($definition);
        } elseif ($this->definitionInfo->isStringObjectDefinition($definition)) {
            $resolved = $this->resolveObject(['class' => $definition]);
        } else {
            $resolved = $definition;
        }
        
        return $resolved;
    }

    public function resolveObject(array $config): object
    {
        $reflectionClass = new \ReflectionClass($config['class']);
        // TODO: check for fallback to parent constructor
        $constructor = $reflectionClass->getConstructor();
        if (!$constructor) {
            return $reflectionClass->newInstance();
        }

        $arguments = $this->resolveArgs(
            $constructor->getParameters(),
            $config['args'] ?? []
        );
        
        return $reflectionClass->newInstanceArgs($arguments);
    }

    public function resolveArgs(array $parameters, array $predefined = []): array
    {
        $arguments = [];

        foreach ($parameters as $parameter) {
            $name = $parameter->getName();

            if (isset($predefined[$name])) {
                $arguments[] = $predefined[$name];
                continue;
            }

            $type = $parameter->getType();

            if ($type) {
                $typeName = $type->getName();

                if (!$this->container->has($typeName) && $parameter->isDefaultValueAvailable()) {
                    continue;
                }
                
                $arguments[] = $this->container->get($type->getName());
            } else if (!$parameter->isDefaultValueAvailable()){
                throw new \LogicException("No type available for \"$name\"");
            }
        }

        return $arguments;
    }
}