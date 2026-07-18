<?php

namespace App\Models;

use Database\Factories\MailTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MailTemplate extends Model
{
    /** @use HasFactory<MailTemplateFactory> */
    use HasFactory;

    protected $fillable = ['mail_type', 'subject', 'intro_text', 'footer_note'];

    public static function forType(string $type): ?self
    {
        return static::where('mail_type', $type)->first();
    }
}
