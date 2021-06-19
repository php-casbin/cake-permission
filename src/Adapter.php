<?php

namespace CasbinAdapter\Cake;

use Casbin\Exceptions\CasbinException;
use Casbin\Persist\Adapter as AdapterContract;
use Casbin\Persist\BatchAdapter as BatchAdapterContract;
use Casbin\Persist\AdapterHelper;
use Cake\ORM\TableRegistry;
use CasbinAdapter\Cake\Model\Table\CasbinRuleTable;

/**
 * Adapter.
 *
 * @author techlee@qq.com
 */
class Adapter implements AdapterContract, BatchAdapterContract
{
    use AdapterHelper;

    protected $table;

    public function __construct()
    {
        $this->table = $this->getTable();
    }

    public function savePolicyLine($ptype, array $rule)
    {
        $col['`ptype`'] = $ptype;
        foreach ($rule as $key => $value) {
            $col['`v'.strval($key).'`'] = $value;
        }

        $entity = $this->table->newEntity($col);
        $this->table->save($entity);
    }

    public function loadPolicy($model): void
    {
        $rows = $this->table->find();

        foreach ($rows as $row) {
            $array = $row->toArray();
            $array = array_filter($array, function ($value) {
                return !is_null($value) && $value !== '';
            });
            $line = implode(', ', array_slice(array_values($array), 1));
            $this->loadPolicyLine(trim($line), $model);
        }
    }

    public function savePolicy($model): void
    {
        foreach ($model->model['p'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }

        foreach ($model->model['g'] as $ptype => $ast) {
            foreach ($ast->policy as $rule) {
                $this->savePolicyLine($ptype, $rule);
            }
        }
    }

    public function addPolicy($sec, $ptype, $rule): void
    {
        $this->savePolicyLine($ptype, $rule);
    }

    public function removePolicy($sec, $ptype, $rule): void
    {
        $entity = $this->table->newEntity();

        foreach ($rule as $key => $value) {
            $entity->set('v'.strval($key), $value);
        }

        $this->table->delete($entity);
    }

    public function removeFilteredPolicy($sec, $ptype, $fieldIndex, ...$fieldValues) :void
    {
        throw new CasbinException('not implemented');
    }

    /**
     * Adds a policy rules to the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[][] $rules
     */
    public function addPolicies(string $sec, string $ptype, array $rules): void
    {
        $cols = [];
        $i = 0;

        foreach ($rules as $rule) {
            $temp['`ptype`'] = $ptype;
            foreach ($rule as $key => $value) {
                $temp['`v'. strval($key) . '`'] = $value;
            }
            $cols[$i++] = $temp ?? [];
            $temp = [];
        }
        $entities = $this->table->newEntity($cols);
        $this->table->saveMany($entities);
    }

    /**
     * Removes policy rules from the storage.
     * This is part of the Auto-Save feature.
     *
     * @param string $sec
     * @param string $ptype
     * @param string[][] $rules
     */
    public function removePolicies(string $sec, string $ptype, array $rules): void
    {
        $this->table->getConnection()->transactional(function () use ($ptype, $rules) {
            foreach ($rules as $rule) {
                $entity = $this->table->find();

                foreach ($rule as $key => $value) {
                    $entity->where(['v' . strval($key) => $value]);
                }
                $entity = $entity->first();
                $this->table->delete($entity, ['atomic' => false]);
            }
        });
    }

    protected function getTable()
    {
        return  TableRegistry::getTableLocator()->get('CasbinRule', [
            'className' => CasbinRuleTable::class,
        ]);
    }
}
