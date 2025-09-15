<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WebhookExecutionLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'execution_id',
        'webhook_type',
        'request_method',
        'request_url',
        'request_headers',
        'request_body',
        'ip_address',
        'user_agent',
        'status',
        'events_count',
        'events_data',
        'execution_steps',
        'error_message',
        'error_details',
        'started_at',
        'completed_at',
        'duration_ms',
        'results'
    ];

    protected $casts = [
        'request_headers' => 'array',
        'events_data' => 'array',
        'execution_steps' => 'array',
        'error_details' => 'array',
        'results' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime'
    ];

    public function addExecutionStep($step_name, $details = null, $status = 'completed')
    {
        $steps = $this->execution_steps ?? [];

        // Ensure UTF-8 safe encoding for Chinese characters
        $safeDetails = null;
        if ($details !== null) {
            // Convert to UTF-8 safe format
            $jsonString = json_encode($details, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
            $safeDetails = json_decode($jsonString, true);
        }

        $steps[] = [
            'step' => $step_name,
            'status' => $status,
            'timestamp' => now()->toISOString(),
            'details' => $safeDetails
        ];

        // Ensure UTF-8 safe encoding for the entire steps array
        $jsonString = json_encode($steps, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_IGNORE);
        $safeSteps = json_decode($jsonString, true);

        $this->update(['execution_steps' => $safeSteps]);
    }

    public function markCompleted($results = null)
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
            'duration_ms' => $this->started_at ? now()->diffInMilliseconds($this->started_at) : null,
            'results' => $results
        ]);
    }

    public function markFailed($error_message, $error_details = null)
    {
        $this->update([
            'status' => 'failed',
            'completed_at' => now(),
            'duration_ms' => $this->started_at ? now()->diffInMilliseconds($this->started_at) : null,
            'error_message' => $error_message,
            'error_details' => $error_details
        ]);
    }

    public function scopeRecent($query, $days = 7)
    {
        return $query->where('started_at', '>=', now()->subDays($days));
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByWebhookType($query, $type)
    {
        return $query->where('webhook_type', $type);
    }
}