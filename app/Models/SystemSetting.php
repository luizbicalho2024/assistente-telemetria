<?php

namespace App\Models;

class SystemSetting extends MongoModel
{
    protected $table = 'system_settings';

    public static function defaultBranding(): array
    {
        return [
            '_id' => 'global_branding',
            'system_name' => 'Assistente Telemetria',
            'system_subtitle' => 'Inteligência comercial, financeira e operacional',
            'primary_color' => '#165DFF',
            'secondary_color' => '#0B1F33',
            'accent_color' => '#00A884',
            'background_color' => '#F5F7FA',
            'surface_color' => '#FFFFFF',
            'text_color' => '#172B4D',
            'muted_color' => '#667085',
            'sidebar_background_color' => '#0B1F33',
            'sidebar_text_color' => '#F8FAFC',
            'sidebar_muted_color' => '#CBD5E1',
            'sidebar_hover_color' => '#243B53',
            'sidebar_active_color' => '#165DFF',
            'sidebar_active_text_color' => '#FFFFFF',
            'footer_text' => 'Uso interno',
            'logo_base64' => null,
            'logo_mime' => null,
            'sidebar_logo_base64' => null,
            'sidebar_logo_mime' => null,
        ];
    }

    public static function branding(): array
    {
        $setting = static::query()->where('_id', 'global_branding')->first();
        return array_merge(static::defaultBranding(), $setting?->toArray() ?? []);
    }
}
