<?php
// app/Models/Setting.php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value'];

    public static function bool(string $key, bool $default = false): bool
    {
        $row = static::where('key', $key)->first();
        if (!$row) return $default;
        return filter_var($row->value, FILTER_VALIDATE_BOOLEAN);
    }

    public static function setBool(string $key, bool $value): void
    {
        static::updateOrCreate(
            ['key' => $key],
            ['value' => $value ? '1' : '0']
        );
    }

    public static function paymentRequired(): bool
    {
        return static::bool('payment_required', true);
    }
}