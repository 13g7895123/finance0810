<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Point 40: Website Controller for WordPress site management
 */
class WebsiteController extends Controller
{
    /**
     * Display a listing of websites
     */
    public function index(Request $request)
    {
        // Point 75: Simplified version to avoid relation loading issues
        try {
            // First check if table exists
            if (!Schema::hasTable('websites')) {
                throw new \Exception('Websites table does not exist');
            }

            $query = Website::query();

            // Safe filtering - only apply filters if values are not empty
            $status = $request->get('status');
            if (!empty($status)) {
                $query->where('status', $status);
            }

            $type = $request->get('type');
            if (!empty($type)) {
                $query->where('type', $type);
            }

            $search = $request->get('search');
            if (!empty($search)) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                      ->orWhere('domain', 'like', "%{$search}%");
                });
            }

            // Sort options
            $sortField = $request->get('sort', 'created_at');
            $sortDirection = $request->get('direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            $perPage = $request->get('per_page', 15);

            // Don't load relations to avoid potential issues
            $websites = $query->paginate($perPage);

            return response()->json($websites);

        } catch (\Exception $e) {
            Log::error('Point 81 - Website index error', [
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile(),
                'trace' => $e->getTraceAsString()
            ]);

            // Re-throw the exception to let global exception handler add CORS headers
            throw $e;
        }
    }

    /**
     * Store a newly created website
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'domain' => 'required|string|max:255|unique:websites,domain',
            'url' => 'required|url|max:500',
            'status' => 'in:active,inactive,maintenance',
            'type' => 'in:wordpress,other',
            'webhook_enabled' => 'boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
            'form_settings' => 'nullable|array',
            'tracking_settings' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            // Extract domain from URL if not provided correctly
            $domain = $request->domain;
            if (filter_var($domain, FILTER_VALIDATE_URL)) {
                $domain = Website::extractDomain($domain);
            }

            $website = Website::create([
                'name' => $request->name,
                'domain' => $domain,
                'url' => $request->url,
                'status' => $request->get('status', Website::STATUS_ACTIVE),
                'type' => $request->get('type', Website::TYPE_WORDPRESS),
                'webhook_enabled' => $request->get('webhook_enabled', true),
                'webhook_url' => $request->webhook_url,
                'webhook_secret' => $request->webhook_secret,
                'form_settings' => $request->form_settings,
                'tracking_settings' => $request->tracking_settings,
                'notes' => $request->notes,
            ]);

            // Update initial statistics
            $website->updateStatistics();

            Log::info('Point 40 - Website created', [
                'website_id' => $website->id,
                'domain' => $website->domain,
                'name' => $website->name,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'message' => '網站建立成功',
                'website' => $website->load(['createdBy', 'updatedBy'])
            ], 201);

        } catch (\Exception $e) {
            Log::error('Point 40 - Website creation failed', [
                'error' => $e->getMessage(),
                'domain' => $request->domain,
                'name' => $request->name,
            ]);

            // Re-throw the exception to let global exception handler add CORS headers
            throw $e;
        }
    }

    /**
     * Display the specified website
     */
    public function show(Website $website)
    {
        $website->load(['createdBy', 'updatedBy']);
        $website->statistics = $website->getStatistics();
        $website->is_healthy = $website->isHealthy();

        return response()->json($website);
    }

    /**
     * Update the specified website
     */
    public function update(Request $request, Website $website)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:100',
            'domain' => 'required|string|max:255|unique:websites,domain,' . $website->id,
            'url' => 'required|url|max:500',
            'status' => 'in:active,inactive,maintenance',
            'type' => 'in:wordpress,other',
            'webhook_enabled' => 'boolean',
            'webhook_url' => 'nullable|url|max:500',
            'webhook_secret' => 'nullable|string|max:255',
            'form_settings' => 'nullable|array',
            'tracking_settings' => 'nullable|array',
            'notes' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        try {
            $oldData = $website->toArray();

            // Extract domain from URL if not provided correctly
            $domain = $request->domain;
            if (filter_var($domain, FILTER_VALIDATE_URL)) {
                $domain = Website::extractDomain($domain);
            }

            $website->update([
                'name' => $request->name,
                'domain' => $domain,
                'url' => $request->url,
                'status' => $request->status,
                'type' => $request->type,
                'webhook_enabled' => $request->webhook_enabled,
                'webhook_url' => $request->webhook_url,
                'webhook_secret' => $request->webhook_secret,
                'form_settings' => $request->form_settings,
                'tracking_settings' => $request->tracking_settings,
                'notes' => $request->notes,
            ]);

            // Update statistics
            $website->updateStatistics();

            Log::info('Point 40 - Website updated', [
                'website_id' => $website->id,
                'domain' => $website->domain,
                'name' => $website->name,
                'updated_by' => auth()->id(),
                'changes' => array_diff_assoc($website->toArray(), $oldData),
            ]);

            return response()->json([
                'message' => '網站更新成功',
                'website' => $website->load(['createdBy', 'updatedBy'])
            ]);

        } catch (\Exception $e) {
            Log::error('Point 40 - Website update failed', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw the exception to let global exception handler add CORS headers
            throw $e;
        }
    }

    /**
     * Remove the specified website
     */
    public function destroy(Website $website)
    {
        try {
            Log::info('Point 40 - Website deleted', [
                'website_id' => $website->id,
                'domain' => $website->domain,
                'name' => $website->name,
                'deleted_by' => auth()->id(),
            ]);

            $website->delete();

            return response()->json([
                'message' => '網站刪除成功'
            ]);

        } catch (\Exception $e) {
            Log::error('Point 40 - Website deletion failed', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw the exception to let global exception handler add CORS headers
            throw $e;
        }
    }

    /**
     * Update website statistics
     */
    public function updateStatistics(Website $website)
    {
        try {
            $website->updateStatistics();

            return response()->json([
                'message' => '統計資料更新成功',
                'statistics' => $website->getStatistics()
            ]);

        } catch (\Exception $e) {
            Log::error('Point 40 - Statistics update failed', [
                'website_id' => $website->id,
                'error' => $e->getMessage(),
            ]);

            // Re-throw the exception to let global exception handler add CORS headers
            throw $e;
        }
    }

    /**
     * Get website statistics summary
     */
    public function statistics()
    {
        try {
            $totalWebsites = Website::count();
            $activeWebsites = Website::active()->count();
            $wordpressWebsites = Website::wordPress()->count();
            $webhookEnabled = Website::webhookEnabled()->count();
            
            $healthyWebsites = Website::active()->get()->filter(function ($website) {
                return $website->isHealthy();
            })->count();

            $topPerformers = Website::active()
                ->orderBy('lead_count', 'desc')
                ->limit(5)
                ->get(['id', 'name', 'domain', 'lead_count', 'conversion_rate']);

            return response()->json([
                'summary' => [
                    'total_websites' => $totalWebsites,
                    'active_websites' => $activeWebsites,
                    'wordpress_websites' => $wordpressWebsites,
                    'webhook_enabled' => $webhookEnabled,
                    'healthy_websites' => $healthyWebsites,
                ],
                'top_performers' => $topPerformers,
            ]);

        } catch (\Exception $e) {
            Log::error('Point 40 - Statistics retrieval failed', [
                'error' => $e->getMessage(),
            ]);

            // Re-throw the exception to let global exception handler add CORS headers
            throw $e;
        }
    }

    /**
     * Get websites for dropdown/select options
     */
    public function options()
    {
        $websites = Website::active()
            ->orderBy('name')
            ->get(['id', 'name', 'domain', 'status']);

        return response()->json($websites);
    }
}