<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateCounter24Table extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        if (! $this->hasTable('counter24')) {
            $table = $this->table('counter24', ['collation' => env('DB_COLLATION')]);
            $table->addColumn('hour', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM, 'signed' => false])
                ->addColumn('hosts', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM, 'signed' => false])
                ->addColumn('hits', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM, 'signed' => false])
                ->addIndex('hour', ['unique' => true])
                ->create();
        }
    }
}
