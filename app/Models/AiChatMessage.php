<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiChatMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'conversation_id',
        'role',
        'content',
        'attachment_name',
        'attachment_path',
        'attachment_type',
        'attachment_extension',
        'attachment_size',
        'is_error',
        'source',
        'status_hint',
    ];

    protected $casts = [
        'is_error' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(AiChatConversation::class, 'conversation_id');
    }

    /**
     * Format message for Alpine.js frontend consumption.
     *
     * @return array<string, mixed>
     */
    public function toClientPayload(): array
    {
        $hasAttachment = ! empty($this->attachment_name);
        $previewUrl = null;

        if ($hasAttachment && $this->attachment_type === 'image' && ! empty($this->attachment_path)) {
            $previewUrl = route('dashboard.ai-assistant.attachment', ['message' => $this->id]);
        }

        return [
            'id' => $this->id,
            'role' => $this->role,
            'content' => $this->content,
            'time' => $this->created_at ? $this->created_at->format('g:i A') : '',
            'isError' => (bool) $this->is_error,
            'source' => $this->source,
            'statusHint' => $this->status_hint,
            'attachment' => $hasAttachment ? [
                'name' => $this->attachment_name,
                'size' => $this->attachment_size,
                'type' => $this->attachment_type,
                'extension' => $this->attachment_extension,
                'previewUrl' => $previewUrl,
            ] : null,
        ];
    }
}
