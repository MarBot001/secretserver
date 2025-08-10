<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * @property int $id
 * @property string $hash
 * @property string $created_at
 * @property string|null $expires_at
 * @property int $remaining_views
 * @property string $alg
 * @property resource|string $ciphertext
 * @property string $iv
 * @property string $tag
 */
class Secret extends ActiveRecord
{
    public static function tableName()
    {
        return '{{%secret}}';
    }

    public function rules()
    {
        return [
            [['hash'], 'string', 'max' => 64],
            [['created_at'], 'required'],
            [['created_at', 'expires_at'], 'safe'],
            [['remaining_views'], 'integer', 'min' => 1],
            [['alg'], 'string', 'max' => 16],
            [['ciphertext', 'iv', 'tag', 'alg', 'remaining_views'], 'required'],
        ];
    }

    public function fields()
    {
        return [
            'hash' => 'hash',
            'secretText' => function () {
                return $this->decrypt();
            },
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
        $m->remaining_views = $expireAfterViews;
        $m->created_at = gmdate('Y-m-d H:i:s');

        if ($expireAfterMinutes > 0) {
            $m->expires_at = gmdate('Y-m-d H:i:s', time() + ($expireAfterMinutes * 60));
        } else {
            $m->expires_at = null;
        }

        [$ciphertext, $iv, $tag] = self::encrypt($secretText);
        $m->ciphertext = $ciphertext;
        $m->iv = $iv;
        $m->tag = $tag;
        $m->alg = 'AES-256-GCM';

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
                ['and', ['id' => $this->id], ['>', 'remaining_views', 0]]
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

    private static function getKey(): string
    {
        $b64 = Yii::$app->params['secretKey'] ?? null;
        if (!$b64) {
            throw new \RuntimeException('Missing secretKey in params (expected base64-encoded 32 bytes).');
        }
        $key = base64_decode($b64, true);
        if ($key === false || strlen($key) !== 32) {
            throw new \RuntimeException('Invalid SECRET_KEY_BASE64: must decode to 32 bytes.');
        }
        return $key;
    }

    private static function encrypt(string $plain): array
    {
        $key = self::getKey();
        $iv = random_bytes(12);
        $tag = '';
        $cipher = 'aes-256-gcm';

        $ciphertext = openssl_encrypt($plain, $cipher, $key, OPENSSL_RAW_DATA, $iv, $tag);
        if ($ciphertext === false) {
            throw new \RuntimeException('Encryption failed');
        }

        return [$ciphertext, $iv, $tag];
    }

    private function decrypt(): ?string
    {
        if (!$this->ciphertext || !$this->iv || !$this->tag) {
            return null;
        }
        $key = self::getKey();
        $plain = openssl_decrypt($this->ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $this->iv, $this->tag);
        return $plain === false ? null : $plain;
    }
}
