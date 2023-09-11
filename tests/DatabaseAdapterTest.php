<?php

namespace Cake\Permission\Tests;

use PHPUnit\Framework\TestCase;
use \Cake\ORM\TableRegistry;
use Cake\Datasource\ConnectionManager;
use Cake\Permission\Casbin;
use Casbin\Persist\Adapters\Filter;
use Casbin\Exceptions\InvalidFilterTypeException;
use Cake\Core\Configure;
use Cake\Core\Configure\Engine\PhpConfig;

define('CONFIG', dirname(__DIR__) . '/vendor/cakephp/cakephp/config/');

class DatabaseAdapterTest extends TestCase
{
    protected $table;

    protected function getEnforcer()
    {
        Configure::config('default', new PhpConfig());
        Configure::write('App', [
            'namespace' => ''
        ]);
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
            'host' => getenv('DB_HOST') ? getenv('DB_HOST') : '127.0.0.1',
            'username' => getenv('DB_USERNAME') ? getenv('DB_USERNAME') : 'root',
            'password' => getenv('DB_PASSWORD') ? getenv('DB_PASSWORD') : '',
            'port' => getenv('DB_PORT') ? getenv('DB_PORT') : '3306',
            'database' => getenv('DB_DATABASE') ? getenv('DB_DATABASE') : 'cake-permission',
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
            'cacheMetadata' => false,
        ]);

        copy(__DIR__ . '/casbin.php', dirname(__DIR__) . '/vendor/cakephp/cakephp/config/casbin.php');
        copy(__DIR__ . '/casbin-model.conf', dirname(__DIR__) . '/vendor/cakephp/cakephp/config/casbin-model.conf');
    }

    protected function getTable()
    {
        return TableRegistry::getTableLocator()->get('CasbinRule');
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

    public function testSavePolicy()
    {
        $e = $this->getEnforcer();
        $this->assertFalse($e->enforce('alice', 'data4', 'read'));

        $model = $e->getModel();
        $model->clearPolicy();
        $model->addPolicy('p', 'p', ['alice', 'data4', 'read']);

        $adapter = $e->getAdapter();
        $adapter->savePolicy($model);
        $this->assertTrue($e->enforce('alice', 'data4', 'read'));
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

    public function testUpdatePolicies()
    {
        $e = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $e->getPolicy());

        $oldPolicies = [
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write']
        ];
        $newPolicies = [
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read']
        ];

        $e->updatePolicies($oldPolicies, $newPolicies);

        $this->assertEquals([
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $e->getPolicy());
    }

    public function arrayEqualsWithoutOrder(array $expected, array $actual)
    {
        if (method_exists($this, 'assertEqualsCanonicalizing')) {
            $this->assertEqualsCanonicalizing($expected, $actual);
        } else {
            array_multisort($expected);
            array_multisort($actual);
            $this->assertEquals($expected, $actual);
        }
    }

    public function testUpdateFilteredPolicies()
    {
        $e = $this->getEnforcer();
        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
        ], $e->getPolicy());

        $e->updateFilteredPolicies([["alice", "data1", "write"]], 0, "alice", "data1", "read");
        $e->updateFilteredPolicies([["bob", "data2", "read"]], 0, "bob", "data2", "write");

        $policies = [
            ['alice', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, $e->getPolicy());

        // test use updateFilteredPolicies to update all policies of a user
        $e = $this->getEnforcer();
        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];

        $e->addPolicies($policies);

        $this->arrayEqualsWithoutOrder([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], $e->getPolicy());

        $e->updateFilteredPolicies([['alice', 'data1', 'write'], ['alice', 'data2', 'read']], 0, 'alice');
        $e->updateFilteredPolicies([['bob', 'data1', 'write'], ["bob", "data2", "read"]], 0, 'bob');

        $policies = [
            ['alice', 'data1', 'write'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, $e->getPolicy());

        // test if $fieldValues contains empty string
        $e = $this->getEnforcer();
        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];
        $e->addPolicies($policies);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], $e->getPolicy());

        $e->updateFilteredPolicies([['alice', 'data1', 'write'], ['alice', 'data2', 'read']], 0, 'alice', '', '');
        $e->updateFilteredPolicies([['bob', 'data1', 'write'], ["bob", "data2", "read"]], 0, 'bob', '', '');

        $policies = [
            ['alice', 'data1', 'write'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'write'],
            ['bob', 'data2', 'read'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write']
        ];

        $this->arrayEqualsWithoutOrder($policies, $e->getPolicy());

        // test if $fieldIndex is not zero
        $e = $this->getEnforcer();
        $policies = [
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ];
        $e->addPolicies($policies);

        $this->assertEquals([
            ['alice', 'data1', 'read'],
            ['bob', 'data2', 'write'],
            ['data2_admin', 'data2', 'read'],
            ['data2_admin', 'data2', 'write'],
            ['alice', 'data2', 'write'],
            ['bob', 'data1', 'read']
        ], $e->getPolicy());

        $e->updateFilteredPolicies([['alice', 'data1', 'edit'], ['bob', 'data1', 'edit']], 2, 'read');
        $e->updateFilteredPolicies([['alice', 'data2', 'read'], ["bob", "data2", "read"]], 2, 'write');

        $policies = [
            ['alice', 'data1', 'edit'],
            ['alice', 'data2', 'read'],
            ['bob', 'data1', 'edit'],
            ['bob', 'data2', 'read'],
        ];

        $this->arrayEqualsWithoutOrder($policies, $e->getPolicy());
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
