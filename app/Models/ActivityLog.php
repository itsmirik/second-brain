<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $direction
 * @property int|null $telegram_update_id
 * @property string|null $chat_id
 * @property string|null $message_type
 * @property string|null $payload_summary
 */
class ActivityLog extends Model
{
    protected $fillable = [
        'direction',
        'telegram_update_id',
        'chat_id',
        'message_type',
        'payload_summary',
    ];

    protected function casts(): array
    {
        return [
            'telegram_update_id' => 'integer',
        ];
    }
}
