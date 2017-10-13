<?php

use Phinx\Migration\AbstractMigration;

class CreateQueueTable extends AbstractMigration
{
    /**
     * Change Method.
     */
    public function change()
    {
        $table = $this->table('queue', ['collation' => env('DB_COLLATION')]);
        $table
            ->addColumn('user_id', 'integer')
            ->addColumn('type', 'string', ['limit' => 30])
            ->addColumn('subject', 'string', ['limit' => 100])
            ->addColumn('text', 'text', ['null' => true])
            ->addColumn('sent', 'boolean', ['default' => false])
            ->addColumn('created_at', 'integer', ['null' => true])
            ->addColumn('sent_at', 'integer', ['null' => true])
            ->create();
    }
}
