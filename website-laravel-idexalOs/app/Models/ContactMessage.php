<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class ContactMessage extends Model
{
    use HasUlids;

    public const STATUS_NEW = 'new';

    public const STATUS_READ = 'read';

    public const STATUS_REPLIED = 'replied';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'subject',
        'message',
        'status',
        'ip',
    ];
}
