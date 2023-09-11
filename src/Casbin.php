<?php

namespace Cake\Permission;

use Casbin\Model\Model;
use Casbin\Enforcer;
use Cake\Core\Configure;
use Cake\ORM\TableRegistry;
use Cake\Database\Schema\TableSchema;
use Cake\Datasource\ConnectionManager;

/**
 * Casbin.
 *
 * @author techlee@qq.com
 */
class Casbin
{
    public $enforcer;
    public $adapter;
    public $model;
    public $config = [];

    public function __construct($config = [])
    {
        Configure::load('casbin');
        $config = Configure::consume('Casbin');

        $this->config = $this->mergeConfig(
            require dirname(__DIR__).'/config/casbin.php',
            $config
        );
        if (is_string($config['adapter'])) {
            $this->adapter = new $config['adapter']();
        } else {
            $this->adapter = $config['adapter'];
        }

        $this->model = new Model();
        if ('file' == $this->config['model']['config_type']) {
            $this->model->loadModel($this->config['model']['config_file_path']);
        } elseif ('text' == $this->configType) {
            $this->model->loadModel($this->config['model']['config_text']);
        }
    }

    /**
     * Initializes the object.
     * This method is invoked at the end of the constructor after the object is initialized with the
     * given configuration.
     */
    public function init()
    {
        $table = TableRegistry::getTableLocator()->get('CasbinRule');

        $tableName = $table->getTable();
        $schema = new TableSchema($tableName);
        $schema->addColumn('id', 'integer')
        ->addColumn('ptype', 'string')
          ->addColumn('v0', 'string')
          ->addColumn('v1', 'string')
          ->addColumn('v2', 'string')
          ->addColumn('v3', 'string')
          ->addColumn('v4', 'string')
          ->addColumn('v5', 'string')
        ->addConstraint('primary', [
            'type' => 'primary',
            'columns' => ['id'],
        ]);
        $db = ConnectionManager::get('default');
        try {
            // Create a table
            $queries = $schema->createSql($db);
            foreach ($queries as $sql) {
                $db->execute($sql);
            }
        } catch (\PDOException $e) {
            // die;
        }
    }

    public function enforcer($newInstance = false)
    {
        if ($newInstance || is_null($this->enforcer)) {
            $this->init();
            $this->enforcer = new Enforcer($this->model, $this->adapter);
        }

        return $this->enforcer;
    }

    private function mergeConfig(array $a, array $b)
    {
        foreach ($a as $key => $val) {
            if (isset($b[$key])) {
                if (gettype($a[$key]) != gettype($b[$key])) {
                    continue;
                }
                if (is_array($a[$key])) {
                    $a[$key] = $this->mergeConfig($a[$key], $b[$key]);
                } else {
                    $a[$key] = $b[$key];
                }
            }
        }

        return $a;
    }

    public function __call($name, $params)
    {
        return call_user_func_array([$this->enforcer(), $name], $params);
    }
}
