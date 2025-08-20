<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\IncrementalSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SyncController extends Controller
{
    private $syncService;
    
    public function __construct(IncrementalSyncService $syncService)
    {
        $this->syncService = $syncService;
    }
    
    /**
     * 獲取增量更新
     */
    public function getUpdates(Request $request, string $entityType): JsonResponse
    {
        $request->validate([
            'client_version' => 'required|integer|min:0',
            'limit' => 'integer|min:1|max:1000'
        ]);
        
        try {
            $clientVersion = $request->get('client_version');
            $limit = $request->get('limit', 100);
            
            $result = $this->syncService->getIncrementalUpdate(
                $entityType,
                $clientVersion,
                $limit
            );
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 校驗數據完整性
     */
    public function validateIntegrity(Request $request, string $entityType): JsonResponse
    {
        $request->validate([
            'data' => 'required|array',
            'data.*.id' => 'required|integer',
            'data.*.version' => 'required|integer'
        ]);
        
        try {
            $clientData = $request->get('data');
            
            $errors = $this->syncService->validateDataIntegrity(
                $entityType,
                $clientData
            );
            
            return response()->json([
                'success' => true,
                'errors' => $errors,
                'is_valid' => empty($errors)
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Validation failed: ' . $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * 獲取同步統計信息
     */
    public function getStats(Request $request, string $entityType): JsonResponse
    {
        try {
            $stats = $this->syncService->getSyncStats($entityType);
            
            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'success' => false,
                'error' => $e->getMessage()
            ], 400);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to get stats: ' . $e->getMessage()
            ], 500);
        }
    }
}