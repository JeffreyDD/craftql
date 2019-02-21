<?php

namespace markhuot\CraftQL\Factories;

use GraphQL\Type\Definition\EnumType;
use markhuot\CraftQL\Request;

abstract class BaseFactory {

    static $enums = [];

    protected $repository;

    /**
     * @var Request
     */
    protected $request;
    private $objects = [];

    function __construct($repository, $request) {
        $this->repository = $repository;
        $this->request = $request;
    }

    // function repository() {
    //     return $this->repository;
    // }

    function getRaw($id) {
        return $this->repository->get($id);
    }

    function get($id, $mode='query') {
        if ($this->can($id, $mode) === false) {
            return false;
        }

        if (isset($this->objects[$id])) {
            return $this->objects[$id];
        }

        return $this->objects[$id] = $this->make($this->repository->get($id), $this->request);
    }

    abstract function can($id, $mode='query');
    abstract function make($raw, $request);

    function all($mode='query') {
        $objects = [];

        foreach ($this->repository->all() as $raw) {
            if ($object = $this->get($raw->id, $mode)) {
                $objects[] = $object;
            }
        }

        return $objects;
    }

    function count() {
        return count($this->repository->all());
    }

    function getEnumKey($object) {
        $rawObject = $this->repository->get($object->getContext()->id);
        return $rawObject->handle;
    }

    function enum() {
        $reflect = new \ReflectionClass($this);
        $name = $reflect->getShortName().'sEnum';

        if (!empty(static::$enums[$name])) {
            return static::$enums[$name];
        }

        $values = [];

        foreach ($this->all() as $index => $object) {
            $values[$this->getEnumKey($object)] = $object->getContext()->id;
        }

        // Enums can't be emtpy so fake it. Craft can expose the Category or
        // Tag top level fields without any category or tag groups defined.
        if (empty($values)) {
            $values['empty'] = 'Empty';
        }

        static::$enums[$name] = new EnumType([
            'name' => $name,
            'values' => $values,
        ]);

        $this->request->registerType($name, static::$enums[$name]);

        return static::$enums[$name];
    }

}