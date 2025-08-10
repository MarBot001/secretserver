<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\behaviors\TimestampBehavior;

class Secret extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%secret}}';
    }

    public function rules()
    {
        return [
            [['secret_text', 'remaining_views'], 'required'],
            [['secret_text'], 'string'],
            [['remaining_views'], 'integer', 'min' => 1],
            [['created_at', 'expires_at'], 'safe'],
            [['hash'], 'string', 'max' => 64],
        ];
    }

    public function fields()
    {
        return [
            'hash' => 'hash',
            'secretText' => 'secret_text',
            'createdAt' => function () {
                return $this->asIso($this->created_at);
            },
            'expiresAt' => function () {
                return $this->expires_at ? $this->asIso($this->expires_at) : null;
            },
            'remainingViews' => 'remaining_views',
        ];
    }

    private function asIso($dt)
    {
        if (!$dt) return null;
        $ts = is_numeric($dt) ? (int)$dt : strtotime($dt);
        return gmdate('c', $ts);
    }

    public static function createFromInput(string $secretText, int $expireAfterViews, int $expireAfterMinutes): self
    {
        $m = new self();
        $m->hash = self::generateHash();
        $m->secret_text = $secretText;
        $m->remaining_views = $expireAfterViews;
        $m->created_at = gmdate('Y-m-d H:i:s');

        if ($expireAfterMinutes > 0) {
            $m->expires_at = gmdate('Y-m-d H:i:s', time() + ($expireAfterMinutes * 60));
        } else {
            $m->expires_at = null;
        }
        return $m;
    }

    public static function generateHash(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function isExpiredByTime(): bool
    {
        if ($this->expires_at === null) return false;
        return strtotime($this->expires_at) <= time();
    }

    public function canBeViewed(): bool
    {
        return $this->remaining_views > 0 && !$this->isExpiredByTime();
    }

    public function consumeOneView(): bool
    {
        if (!$this->canBeViewed()) {
            return false;
        }

        $db = static::getDb();
        $tx = $db->beginTransaction();
        try {
            $fresh = static::findOne(['id' => $this->id]);
            if (!$fresh || $fresh->isExpiredByTime() || $fresh->remaining_views < 1) {
                $tx->rollBack();
                return false;
            }
            $affected = static::updateAllCounters(
                ['remaining_views' => -1],
                ['and',
                    ['id' => $this->id],
                    ['>', 'remaining_views', 0]
                ]
            );
            if ($affected !== 1) {
                $tx->rollBack();
                return false;
            }
            $tx->commit();
            $this->remaining_views -= 1;
            return true;
        } catch (\Throwable $e) {
            $tx->rollBack();
            Yii::error($e->getMessage(), __METHOD__);
            return false;
        }
    }
}
