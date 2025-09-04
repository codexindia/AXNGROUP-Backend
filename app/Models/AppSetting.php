<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AppSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'key',
        'value',
        'type',
        'description',
        'is_active'
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the parsed value based on type
     */
    public function getParsedValueAttribute()
    {
        if (!$this->is_active || $this->value === null) {
            return null;
        }

        return match ($this->type) {
            'integer' => (int) $this->value,
            'boolean' => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($this->value, true),
            default => $this->value,
        };
    }

    /**
     * Set value and automatically detect type if not specified
     */
    public function setValue($value, $type = null)
    {
        if ($type === null) {
            $type = $this->detectType($value);
        }

        $this->type = $type;
        
        if ($type === 'json') {
            $this->value = json_encode($value);
        } else {
            $this->value = (string) $value;
        }
    }

    /**
     * Auto-detect value type
     */
    private function detectType($value)
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        
        if (is_int($value)) {
            return 'integer';
        }
        
        if (is_array($value) || is_object($value)) {
            return 'json';
        }
        
        return 'string';
    }

    /**
     * Get setting by key
     */
    public static function getSetting($key, $default = null)
    {
        $setting = self::where('key', $key)->where('is_active', true)->first();
        
        return $setting ? $setting->parsed_value : $default;
    }

    /**
     * Set setting by key
     */
    public static function setSetting($key, $value, $description = null, $type = null)
    {
        $setting = self::firstOrNew(['key' => $key]);
        
        $setting->setValue($value, $type);
        
        if ($description !== null) {
            $setting->description = $description;
        }
        
        $setting->is_active = true;
        $setting->save();
        
        return $setting;
    }
}
