<?php

namespace CasbinAdapter\Cake\Tests;

use PHPUnit\Framework\TestCase;
use \Cake\ORM\TableRegistry;
use CasbinAdapter\Cake\Model\Table\CasbinRuleTable;
use Cake\Datasource\ConnectionManager;
use CasbinAdapter\Cake\Casbin;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;

class DatabaseAdapterTest extends TestCase
{
    protected $table;

    protected function getEnforcer()
    {
        $this->initConfig();
        $casbin = new Casbin();
        $casbin->init();
        $this->table = $this->getTable();
        $this->initDb();
        return $casbin->enforcer();
    }

    protected function initConfig()
    {
        ConnectionManager::drop('default');

        ConnectionManager::setConfig('default', [
            'className' => 'Cake\Database\Connection',
            'driver' => 'Cake\Database\Driver\Mysql',
            'persistent' => false,
            'host' => '127.0.0.1',
            'username' => 'root',
            'password' => '',
            'port' => '3306',
            'database' => 'cake_adapter',
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
            'cacheMetadata' => true,
        ]);

        $casbin = new File(__DIR__ . '/casbin.php');
        
        if ($casbin->exists()) {
            $dir = new Folder(dirname(__DIR__) . '/vendor/cakephp/app/config/', true);
            $casbin->copy($dir->path . DS . $casbin->name);
        }

        $casbinModel = new File(__DIR__ . '/casbin-model.conf');

        if ($casbinModel->exists()) {
            $dir = new Folder(dirname(__DIR__) . '/vendor/cakephp/app/config/', true);
            $casbinModel->copy($dir->path . DS . $casbinModel->name);
        }
    }

    protected function getTable()
    {
        return TableRegistry::getTableLocator()->get('CasbinRule', [
            'className' => CasbinRuleTable::class,
        ]);
    }

    protected function initDb()
    {
        $this->table->deleteAll(array('1 = 1'));
        $entity = $this->table->newEntity(['ptype' => 'p', 'v0'  => 'alice', 'v1' => 'data1', 'v2' => 'read']);
        $this->table->save($entity);
        $entity = $this->table->newEntity(['ptype' => 'p', 'v0'  => 'bob', 'v1' => 'data2', 'v2' => 'write']);
        $this->table->save($entity);
        $entity = $this->table->newEntity(['ptype' => 'p', 'v0'  => 'data2_admin', 'v1' => 'data2', 'v2' => 'read']);
        $this->table->save($entity);
        $entity = $this->table->newEntity(['ptype' => 'p', 'v0'  => 'data2_admin', 'v1' => 'data2', 'v2' => 'write']);
        $this->table->save($entity);
        $entity = $this->table->newEntity(['ptype' => 'g', 'v0'  => 'alice', 'v1' => 'data2_admin']);
        $this->table->save($entity);
    }

    public function testAddPolicy()
    {
        $e = $this->getEnforcer();
        $this->assertFalse($e->enforce('eve', 'data3', 'read'));
        $e->addPermissionForUser('eve', 'data3', 'read');
        $this->assertTrue($e->enforce('eve', 'data3', 'read'));
    }
}
