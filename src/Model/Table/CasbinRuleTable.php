<?php

namespace Cake\Permission\Model\Table;

use Cake\ORM\Table;
use Cake\Permission\Model\Entity\CasbinRule;

class CasbinRuleTable extends Table
{
    public function initialize(array $config): void
    {
        $this->setEntityClass(CasbinRule::class);
    }
}
