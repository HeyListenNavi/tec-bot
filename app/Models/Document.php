<?php

namespace App\Models;

use App\Services\PdfTextExtractorService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Document extends Model
{
    use HasFactory;

    protected $fillable = [
        'department_id',
        'user_id',
        'title',
        'description',
        'file_path',
        'extracted_text',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saving(function (Document $document) {
            if (!$document->user_id && auth()->check()) {
                $document->user_id = auth()->id();
            }

            if (!$document->department_id && auth()->check() && auth()->user()->department_id) {
                $document->department_id = auth()->user()->department_id;
            }

            if ($document->file_path && ($document->isDirty('file_path') || empty($document->extracted_text))) {
                $fullPath = Storage::disk('public')->path($document->file_path);
                if (is_file($fullPath)) {
                    $extracted = app(PdfTextExtractorService::class)->extractText($fullPath);
                    if (!empty($extracted)) {
                        $document->extracted_text = $extracted;
                    }
                }
            }
        });
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
