<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\VersionTrackingService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VersionController extends Controller
{
    private $versionService;
    
    public function __construct(VersionTrackingService $versionService)
    {
        $this->versionService = $versionService;
    }
    
    /**
     * 獲取當前版本號
     */
    public function getCurrentVersion(): JsonResponse
    {
        try {
            $version = $this->versionService->getCurrentVersion();
            
            return response()->json([
                'success' => true,
                'version' => $version,
                'timestamp' => now()->toISOString()
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get current version'
            ], 500);
        }
    }
    
    /**
     * 獲取增量變更
     */
    public function getIncrementalChanges(Request $request): JsonResponse
    {
        $request->validate([
            'since_version' => 'required|integer|min:0',
            'entity_type' => 'required|string|in:chat,customer,user',
            'limit' => 'integer|min:1|max:1000'
        ]);
        
        try {
            $sinceVersion = $request->get('since_version');
            $entityType = $request->get('entity_type');
            $limit = $request->get('limit', 100);
            
            $changes = $this->versionService->getChangesSinceVersion(
                $entityType,
                $sinceVersion,
                $limit
            );
            
            $currentVersion = $this->versionService->getCurrentVersion();
            
            return response()->json([
                'success' => true,
                'data' => $changes,
                'current_version' => $currentVersion,
                'since_version' => $sinceVersion,
                'has_more' => count($changes) === $limit
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get incremental changes'
            ], 500);
        }
    }
    
    /**
     * 獲取版本歷史
     */
    public function getVersionHistory(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|in:chat,customer,user',
            'entity_id' => 'required|integer',
            'limit' => 'integer|min:1|max:100'
        ]);
        
        try {
            $entityType = $request->get('entity_type');
            $entityId = $request->get('entity_id');
            $limit = $request->get('limit', 10);
            
            $history = $this->versionService->getVersionHistory(
                $entityType,
                $entityId,
                $limit
            );
            
            return response()->json([
                'success' => true,
                'data' => $history
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get version history'
            ], 500);
        }
    }
    
    /**
     * 檢查版本衝突
     */
    public function checkVersionConflict(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|string|in:chat,customer,user',
            'entity_id' => 'required|integer',
            'expected_version' => 'required|integer'
        ]);
        
        try {
            $entityType = $request->get('entity_type');
            $entityId = $request->get('entity_id');
            $expectedVersion = $request->get('expected_version');
            
            $hasConflict = $this->versionService->checkVersionConflict(
                $entityType,
                $entityId,
                $expectedVersion
            );
            
            return response()->json([
                'success' => true,
                'has_conflict' => $hasConflict,
                'current_version' => $hasConflict ? 
                    $this->versionService->getCurrentVersion() : 
                    $expectedVersion
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to check version conflict'
            ], 500);
        }
    }
    
    /**
     * 獲取版本統計
     */
    public function getVersionStats(): JsonResponse
    {
        try {
            $stats = $this->versionService->getVersionStats();
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get version stats'
            ], 500);
        }
    }
}