<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Request;

class ActivityLogService
{
    /**
     * Log activity
     */
    public function log(
        ?int $userId,
        string $action,
        ?string $modelType = null,
        ?int $modelId = null,
        string $description = '',
        ?array $oldValues = null,
        ?array $newValues = null,
        ?array $metadata = null
    ): ActivityLog {
        return ActivityLog::create([
            'user_id' => $userId,
            'action' => $action,
            'model_type' => $modelType,
            'model_id' => $modelId,
            'description' => $description,
            'ip_address' => Request::ip(),
            'user_agent' => Request::userAgent(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'metadata' => $metadata,
        ]);
    }

    /**
     * Log login
     */
    public function logLogin(int $userId): void
    {
        $this->log($userId, 'login', null, null, "User logged in");
    }

    /**
     * Log logout
     */
    public function logLogout(int $userId): void
    {
        $this->log($userId, 'logout', null, null, "User logged out");
    }

    /**
     * Log model creation
     */
    public function logCreate(int $userId, string $modelType, int $modelId, string $description): void
    {
        $this->log($userId, 'create', $modelType, $modelId, $description);
    }

    /**
     * Log model update
     */
    public function logUpdate(int $userId, string $modelType, int $modelId, string $description, array $oldValues, array $newValues): void
    {
        $this->log($userId, 'update', $modelType, $modelId, $description, $oldValues, $newValues);
    }

    /**
     * Log model deletion
     */
    public function logDelete(int $userId, string $modelType, int $modelId, string $description): void
    {
        $this->log($userId, 'delete', $modelType, $modelId, $description);
    }
}


