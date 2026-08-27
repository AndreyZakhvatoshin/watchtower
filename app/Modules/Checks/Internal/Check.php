<?php

namespace App\Modules\Checks\Internal;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Внутренняя модель модуля. За пределы Checks не выходит — наружу уезжает
 * CheckSnapshot (AD-6).
 *
 * @property int $id
 * @property string $ulid
 * @property string $url
 * @property int $interval_seconds
 * @property int $expected_status
 * @property bool $is_active
 */
class Check extends Model
{
    use SoftDeletes;

    protected $table = 'checks';

    // Белый список, а не $guarded = []: массовое присваивание не должно
    // доставать до id, ulid и interval_applied_at. Первые два назначает
    // система, третий двигается только при смене интервала — открытый для
    // fill() он позволил бы сдвинуть сетку расписания правкой формы.
    // Модуль — образец для пяти остальных, и копироваться будет это.
    protected $fillable = [
        'url',
        'interval_seconds',
        'expected_status',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'interval_seconds' => 'integer',
            'expected_status' => 'integer',
            'is_active' => 'boolean',
            'interval_applied_at' => 'immutable_datetime',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'deleted_at' => 'immutable_datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (self $check): void {
            // ULID генерируется здесь, а не в контроллере: любой путь создания —
            // форма, консольная команда, сидер — обязан получить внешний
            // идентификатор, и забыть об этом невозможно.
            $check->ulid ??= (string) Str::ulid();
        });
    }
}
