<?php

namespace CasbinAdapter\Cake\Tests;

use PHPUnit\Framework\TestCase;
use \Cake\ORM\TableRegistry;
use CasbinAdapter\Cake\Model\Table\CasbinRuleTable;
use Cake\Datasource\ConnectionManager;
use CasbinAdapter\Cake\Casbin;
use Cake\Filesystem\File;
use Cake\Filesystem\Folder;
use Casbin\Persist\Adapters\Filter;
use Casbin\Exceptions\InvalidFilterTypeException;

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

    public function testAddPolicies()
    {
        $policies = [
            ['u1', 'd1', 'read'],
            ['u2', 'd2', 'read'],
            ['u3', 'd3', 'read'],
        ];
        $e = $this->getEnforcer();
        $e->clearPolicy();
        $this->assertEquals([], $e->getPolicy());
        $e->addPolicies($policies);
        $this->assertEquals($policies, $e->getPolicy());
    }

    public function testRemovePolicy()
    {
        $e = $this->getEnforcer();
        $this->assertFalse($e->enforce('alice', 'data5', 'read'));

        $e->addPermissionForUser('alice', 'data5', 'read');
        $this->assertTrue($e->enforce('alice', 'data5', 'read'));

        $e->deletePermissionForUser('alice', 'data5', 'read');
        $this->assertFalse($e->enforce('alice', 'data5', 'read'));
    }

    public function testRemovePolicies()
    {
        $e = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $e->getPolicy());

        $e->removePolicies([
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ]);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write']
        ], $e->getPolicy());
    }

    public function testUpdatePolicy()
    {
        $e = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $e->getPolicy());

        $e->updatePolicy(
            ['alice', 'data1', 'read'],
            ['alice', 'data1', 'write']
        );

        $e->updatePolicy(
            ['bob', 'data2', 'write'],
            ['bob', 'data2', 'read']
        );

        $this->assertEquals([
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $e->getPolicy());
    }

    public function testLoadFilteredPolicy()
    {
        $e = $this->getEnforcer();
        $e->clearPolicy();
        $adapter = $e->getAdapter();
        $adapter->setFiltered(true);
        $this->assertEquals([], $e->getPolicy());
        
        // invalid filter type
        try {
            $filter = ['alice', 'data1', 'read'];
            $e->loadFilteredPolicy($filter);
            $exception = InvalidFilterTypeException::class;
            $this->fail("Expected exception $exception not thrown");
        } catch (InvalidFilterTypeException $exception) {
            $this->assertEquals("invalid filter type", $exception->getMessage());
        }

        // string
        $filter = "v0 = 'bob'";
        $e->loadFilteredPolicy($filter);
        $this->assertEquals([
            ['bob', 'data2', 'write']
        ], $e->getPolicy());
        
        // Filter
        $filter = new Filter(['v2'], ['read']);
        $e->loadFilteredPolicy($filter);
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['data2_admin', 'data2', 'read'],
        ], $e->getPolicy());

        // Closure
        $e->loadFilteredPolicy(function ($query) {
            return $query->and(['v1' => 'data1']);
        });

        $this->assertEquals([
            ['alice', 'data1', 'read'],
        ], $e->getPolicy());
    }
}
