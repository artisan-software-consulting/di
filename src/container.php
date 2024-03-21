<?php

namespace di;

use Closure;
use Exception;
use ReflectionClass;
use Symfony\Component\Yaml\Yaml;

/**
 * Class Container
 */
class container
{

    private static $instance;
    private static array $instances = [];

    public static function getInstance()
    {
        if (null === static::$instance) {
            static::$instance = new static();

        }
        return static::$instance;
    }

    public static function initialize($fileName): void
    {
        $yaml = file_get_contents($fileName);
        // Parse yaml to a PHP array
        $config = Yaml::parse($yaml);
        foreach ($config as $key => $value) {
            static::set($key, $value);
        }
    }

    /**
     * @param $id
     * @param null $concrete
     */
    public static function set($id, $concrete = NULL): void
    {
        if ($concrete === NULL) {
            $concrete = $id;
        }
        static::$instances[$id] = $concrete;
    }

    /**
     * Retrieves an instance by its ID with the specified parameters.
     *
     * @param mixed $id The ID of the instance to retrieve.
     * @param array $parameters Optional parameters to be passed to the instance.
     * @return mixed The resolved instance.
     * @throws Exception
     */
    public static function get($id, array $parameters = []): mixed
    {
        // if we don't have it, just register it
        if (!isset(static::$instances[$id])) {
            static::set($id);
        }

        return static::resolve(static::$instances[$id], $parameters);
    }

    /**
     * Resolves the given concrete object with the provided parameters.
     *
     * @param mixed $concrete The concrete object to resolve.
     * @param array $parameters An array of parameters to be passed to the concrete object's constructor.
     *
     * @return mixed|null|object The resolved object.
     *
     * @throws Exception If the concrete object is not instantiable.
     */
    private static function resolve($concrete, $parameters): mixed
    {
        if ($concrete instanceof Closure) {
            return $concrete(static::$instance, $parameters);
        }

        $reflector = new ReflectionClass($concrete);
        // check if class is instantiable
        if (!$reflector->isInstantiable()) {
            throw new Exception("Class {$concrete} is not instantiable");
        }

        // get class constructor
        $constructor = $reflector->getConstructor();
        if (is_null($constructor)) {
            // get new instance from class
            return $reflector->newInstance();
        }

        // get constructor params
        $parameterDescriptors = $constructor->getParameters();
        $dependencies = static::getDependencies($parameterDescriptors, $parameters);

        // get new instance with dependencies resolved
        return $reflector->newInstanceArgs($dependencies);
    }

    /**
     * Retrieves the dependencies based on the parameter descriptors and provided parameters.
     *
     * @param array $parameterDescriptors An array of parameter descriptors.
     * @param array $parameters An array of parameters.
     *
     * @return array The dependencies resolved from the parameter descriptors and provided parameters.
     *
     * @throws Exception If a parameter cannot be resolved.
     */
    private static function getDependencies($parameterDescriptors, $parameters): array
    {
        $dependencies = [];
        $index = 0;
        foreach ($parameterDescriptors as $parameter) {
            // get the type-hinted class
            $type = $parameter->getType();
            if ($type && !$type->isBuiltin()) {
                $dependency = (string)$type;
                $dependencies[] = $parameters[$index] ?? static::get($dependency); // pass the value through
            } else {
                // if it is a primitive type or no type is defined
                if (isset($parameters[$index])) {
                    $dependencies[] = $parameters[$index]; // pass the value through
                } elseif ($parameter->isDefaultValueAvailable()) {
                    // get default value of parameter
                    $dependencies[] = $parameter->getDefaultValue();
                } else {
                    throw new Exception("Can't resolve parameter {$parameter->name}");
                }
            }
            $index++;
        }

        return $dependencies;
    }

    /**
     * Protected constructor to prevent creating a new instance of the *Singleton* via the `new` operator from outside of this class.
     */
    public function __construct()
    {
    }

    /**
     * Private clone method to prevent cloning of the instance of the *Singleton* instance.
     * @return void
     */
    public function __clone()
    {
    }

    /**
     * Private unserialize method to prevent unserializing of the *Singleton* instance.
     * @return void
     */
    public function __wakeup()
    {
    }
}