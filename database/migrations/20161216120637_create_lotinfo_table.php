<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateLotinfoTable extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (! $this->hasTable('lotinfo')) {
            $table = $this->table('lotinfo', ['collation' => env('DB_COLLATION')]);
            $table->addColumn('date', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'signed' => false])
                ->addColumn('sum', 'integer', ['signed' => false, 'default' => 0])
                ->addColumn('newnum', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'signed' => false, 'default' => 0])
                ->addColumn('oldnum', 'integer', ['limit' => MysqlAdapter::INT_SMALL, 'signed' => false, 'default' => 0])
                ->addColumn('winners', 'string', ['null' => true])
                ->create();
        }
    }
}
