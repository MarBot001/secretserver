<?php

use yii\db\Migration;

class m250810_205511_upgrade_secret_table_to_encrypted extends Migration
{
    /**
     * {@inheritdoc}
     */
 public function safeUp()
    {
        $table = $this->db->schema->getTableSchema('{{%secret}}', true);
        if ($table === null) {
            throw new \RuntimeException('Table {{%secret}} not found. Create it first, then run this migration.');
        }

        if (isset($table->columns['secret_text'])) {
            $this->dropColumn('{{%secret}}', 'secret_text');
        }

        if (!isset($table->columns['ciphertext'])) {
            $this->addColumn('{{%secret}}', 'ciphertext', $this->getDb()->getSchema()->createColumnSchemaBuilder('LONGBLOB')->notNull());
        }
        if (!isset($table->columns['iv'])) {
            $this->addColumn('{{%secret}}', 'iv', $this->binary(12)->notNull());
        }
        if (!isset($table->columns['tag'])) {
            $this->addColumn('{{%secret}}', 'tag', $this->binary(16)->notNull());
        }
        if (!isset($table->columns['alg'])) {
            $this->addColumn('{{%secret}}', 'alg', $this->string(16)->notNull()->defaultValue('AES-256-GCM'));
        }
    }

    public function safeDown()
    {
        $table = $this->db->schema->getTableSchema('{{%secret}}', true);
        if ($table === null) {
            return;
        }

        if (!isset($table->columns['secret_text'])) {
            $this->addColumn('{{%secret}}', 'secret_text', $this->text()->notNull());
        }

        foreach (['alg', 'tag', 'iv', 'ciphertext'] as $col) {
            if (isset($table->columns[$col])) {
                $this->dropColumn('{{%secret}}', $col);
            }
        }
    }
}