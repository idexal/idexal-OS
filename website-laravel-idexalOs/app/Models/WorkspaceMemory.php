<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkspaceMemory extends Model
{
    use HasUlids;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'organization_id',
        'workspace_id',
        'content',
        'checksum',
    ];

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }
}
