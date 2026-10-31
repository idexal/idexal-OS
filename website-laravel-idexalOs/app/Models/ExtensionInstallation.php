<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A marketplace extension installed for a user by the admin or by the member
 * themselves. The desktop reads these rows through `/v1/me/library` so the
 * Library tabs (Connectors, Skills, Plugins) reflect what was installed
 * without a relaunch.
 *
 * @property int $id
 * @property string $user_id
 * @property string|null $organization_id
 * @property string $extension_name
 * @property string $extension_type
 * @property string $version
 * @property string|null $source
 * @property Carbon|null $installed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ExtensionInstallation extends Model
{
    public const TYPE_CONNECTOR = 'connector';

    public const TYPE_SKILL = 'skill';

    public const TYPE_PLUGIN = 'plugin';

    public const TYPES = [self::TYPE_CONNECTOR, self::TYPE_SKILL, self::TYPE_PLUGIN];

    protected $table = 'extension_installations';

    protected $fillable = [
        'user_id',
        'organization_id',
        'extension_name',
        'extension_type',
        'version',
        'source',
        'installed_at',
    ];

    protected $casts = [
        'installed_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
