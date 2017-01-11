<?php

use Phinx\Migration\AbstractMigration;
use Phinx\Db\Adapter\MysqlAdapter;

class CreateStatusTable extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('status', ['collation' => 'utf8mb4_unicode_ci']);
        $table->addColumn('topoint', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM, 'signed' => false])
            ->addColumn('point', 'integer', ['limit' => MysqlAdapter::INT_MEDIUM, 'signed' => false])
            ->addColumn('name', 'string', ['limit' => 50])
            ->addColumn('color', 'string', ['limit' => 10, 'null' => true])
            ->addIndex('point')
            ->addIndex('topoint')
            ->create();
    }
}