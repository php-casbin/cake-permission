<?php

namespace CasbinAdapter\Cake\Model\Table;

use Cake\ORM\Table;
use CasbinAdapter\Cake\Model\Entity\CasbinRule;

class CasbinRuleTable extends Table
{
    public function initialize(array $config)
    {
        $this->setEntityClass(CasbinRule::class);
    }
}
