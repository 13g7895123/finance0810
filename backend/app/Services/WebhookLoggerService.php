<?php

namespace App\Services;

use App\Models\WebhookExecutionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class WebhookLoggerService
{
    private WebhookExecutionLog $executionLog;
    private string $executionId;

    public function startExecution(Request $request, string $webhookType = 'line'): self
    {
        $this->executionId = Str::uuid()->toString();
        
        try {
            $this->executionLog = WebhookExecutionLog::create([
                'execution_id' => $this->executionId,
                'webhook_type' => $webhookType,
                'request_method' => $request->method(),
                'request_url' => $request->fullUrl(),
                'request_headers' => $request->headers->all(),
                'request_body' => $request->getContent(),
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'status' => 'started',
                'started_at' => now()
            ]);

            $this->logStep('webhook_started', [
                'execution_id' => $this->executionId,
                'webhook_type' => $webhookType,
                'ip' => $request->ip()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to create webhook execution log (table may not exist)', [
                'execution_id' => $this->executionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'suggestion' => 'Run the migration: php artisan migrate or execute webhook_execution_logs_table.sql'
            ]);
        }

        return $this;
    }

    public function logStep(string $stepName, $details = null, string $status = 'completed'): self
    {
        try {
            if (!isset($this->executionLog)) {
                Log::warning('Attempted to log step without starting execution or executionLog not available', [
                    'step' => $stepName,
                    'details' => $details,
                    'execution_id' => $this->executionId ?? 'unknown'
                ]);
                return $this;
            }

            $this->executionLog->addExecutionStep($stepName, $details, $status);
        } catch (\Exception $e) {
            Log::warning('Failed to add execution step to database', [
                'step' => $stepName,
                'execution_id' => $this->executionId ?? 'unknown',
                'error' => $e->getMessage()
            ]);
        }

        // Always log to Laravel log for backup
        Log::info("Webhook Step: {$stepName}", [
            'execution_id' => $this->executionId ?? 'unknown',
            'status' => $status,
            'details' => $details
        ]);

        return $this;
    }

    public function setEvents(array $events): self
    {
        if (!isset($this->executionLog)) {
            return $this;
        }

        $this->executionLog->update([
            'events_count' => count($events),
            'events_data' => $events,
            'status' => 'processing'
        ]);

        $this->logStep('events_parsed', [
            'events_count' => count($events),
            'event_types' => array_map(fn($e) => $e['type'] ?? 'unknown', $events)
        ]);

        return $this;
    }

    public function logSignatureVerification(bool $success, $details = null): self
    {
        $this->logStep('signature_verification', [
            'success' => $success,
            'details' => $details
        ], $success ? 'completed' : 'failed');

        return $this;
    }

    public function logEventProcessing(int $eventIndex, array $event, $result = null): self
    {
        $this->logStep("process_event_{$eventIndex}", [
            'event_type' => $event['type'] ?? 'unknown',
            'event_data' => $event,
            'result' => $result
        ]);

        return $this;
    }

    public function logCustomerOperation(string $operation, $customerId = null, $details = null): self
    {
        $this->logStep("customer_{$operation}", [
            'customer_id' => $customerId,
            'details' => $details
        ]);

        return $this;
    }

    public function logConversationOperation(string $operation, $conversationId = null, $details = null): self
    {
        $this->logStep("conversation_{$operation}", [
            'conversation_id' => $conversationId,
            'details' => $details
        ]);

        return $this;
    }

    public function logFirebaseSync(bool $success, $conversationId = null, $error = null): self
    {
        $this->logStep('firebase_sync', [
            'success' => $success,
            'conversation_id' => $conversationId,
            'error' => $error
        ], $success ? 'completed' : 'failed');

        return $this;
    }

    public function logDatabaseTransaction(string $operation, bool $success, $details = null): self
    {
        $this->logStep("database_{$operation}", [
            'success' => $success,
            'details' => $details
        ], $success ? 'completed' : 'failed');

        return $this;
    }

    public function completeExecution($results = null): WebhookExecutionLog
    {
        if (!isset($this->executionLog)) {
            Log::error('Attempted to complete execution without starting it');
            return new WebhookExecutionLog();
        }

        try {
            $this->executionLog->markCompleted($results);
        } catch (\Exception $e) {
            Log::error('Failed to mark execution as completed in database', [
                'execution_id' => $this->executionId,
                'error' => $e->getMessage()
            ]);
        }

        $this->logStep('webhook_completed', [
            'results' => $results,
            'duration_ms' => $this->executionLog->duration_ms ?? null
        ]);

        return $this->executionLog;
    }

    public function failExecution(string $errorMessage, $errorDetails = null): WebhookExecutionLog
    {
        if (!isset($this->executionLog)) {
            Log::error('Attempted to fail execution without starting it', [
                'error' => $errorMessage
            ]);
            return new WebhookExecutionLog();
        }

        try {
            $this->executionLog->markFailed($errorMessage, $errorDetails);
        } catch (\Exception $e) {
            Log::error('Failed to mark execution as failed in database', [
                'execution_id' => $this->executionId,
                'original_error' => $errorMessage,
                'database_error' => $e->getMessage()
            ]);
        }

        $this->logStep('webhook_failed', [
            'error_message' => $errorMessage,
            'error_details' => $errorDetails
        ], 'failed');

        return $this->executionLog;
    }

    public function getExecutionId(): string
    {
        return $this->executionId ?? '';
    }

    public function getExecutionLog(): ?WebhookExecutionLog
    {
        return $this->executionLog ?? null;
    }
}