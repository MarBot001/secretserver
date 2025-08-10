<?php

use yii\db\Migration;

/**
 * Handles the creation of table `{{%secret}}`.
 */
class m250810_083331_create_secret_table extends Migration
{
    /**
     * {@inheritdoc}
     */
    public function safeUp()
    {

        $tableOptions = null;
        if ($this->db->driverName === 'mysql') {
            $tableOptions = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE=InnoDB';
        }

        $this->createTable('{{%secret}}', [
            'id' => $this->primaryKey(),
            'hash' => $this->string(64)->notNull()->unique(),
            'secret_text' => $this->text()->notNull(),
            'created_at' => $this->dateTime()->notNull(),
            'expires_at' => $this->dateTime()->null(),
            'remaining_views' => $this->integer()->notNull()->defaultValue(1),
        ], $tableOptions);

        $this->createIndex('idx_secret_hash', '{{%secret}}', 'hash', true);
    }

    public function safeDown()
    {
        $this->dropTable('{{%secret}}');
    }
}
