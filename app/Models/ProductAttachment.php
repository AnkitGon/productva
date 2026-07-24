<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ProductAttachment extends Model
{
    use HasFactory, SoftDeletes;

    public const TYPES = [
        'image',
        'datasheet',
        'safety_sheet',
        'technical_drawing',
        'certificate',
        'manual',
        'other',
    ];

    public const TYPE_LABELS = [
        'image' => 'Image',
        'datasheet' => 'Datasheet',
        'safety_sheet' => 'Safety Sheet',
        'technical_drawing' => 'Technical Drawing',
        'certificate' => 'Certificate',
        'manual' => 'Manual',
        'other' => 'Other',
    ];

    protected $fillable = [
        'product_id',
        'organization_id',
        'original_name',
        'file_path',
        'mime_type',
        'file_size',
        'type',
        'sort_order',
        'created_by',
    ];

    /**
     * @var array<string, string>
     */
    protected $appends = ['file_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_size' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getFileUrlAttribute(): string
    {
        return '/storage/'.ltrim($this->file_path, '/');
    }

    public function isImage(): bool
    {
        return str_starts_with((string) $this->mime_type, 'image/');
    }

    public function formattedSize(): string
    {
        $bytes = $this->file_size;
        if ($bytes < 1024) {
            return "{$bytes} B";
        }
        if ($bytes < 1048576) {
            return round($bytes / 1024, 1).' KB';
        }

        return round($bytes / 1048576, 1).' MB';
    }
}
