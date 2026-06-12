<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    protected $fillable = ['mail_type', 'subject', 'intro_text', 'footer_note'];

    public static function forType(string $type): ?self
    {
        return static::where('mail_type', $type)->first();
    }
}
