<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\User;

class HealthController extends Controller
{
    /**
     * Basic health check endpoint
     */
    public function check()
    {
        return response()->json([
            'status' => 'ok',
            'message' => 'Laravel CRM Backend is running',
            'timestamp' => now()->toISOString(),
            'version' => app()->version(),
            'environment' => app()->environment(),
        ]);
    }

    /**
     * Database health check
     */
    public function database()
    {
        try {
            $dbStatus = DB::connection()->getPdo() ? 'connected' : 'disconnected';
            $userCount = User::count();
            
            return response()->json([
                'status' => 'ok',
                'database' => $dbStatus,
                'users_count' => $userCount,
                'timestamp' => now()->toISOString(),
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'database' => 'error',
                'error' => $e->getMessage(),
                'timestamp' => now()->toISOString(),
            ], 500);
        }
    }

    /**
     * System information
     */
    public function info()
    {
        return response()->json([
            'app' => [
                'name' => config('app.name'),
                'version' => app()->version(),
                'environment' => app()->environment(),
                'debug' => config('app.debug'),
                'url' => config('app.url'),
                'timezone' => config('app.timezone'),
                'locale' => config('app.locale'),
            ],
            'php' => [
                'version' => phpversion(),
                'memory_limit' => ini_get('memory_limit'),
                'max_execution_time' => ini_get('max_execution_time'),
            ],
            'laravel' => [
                'version' => app()->version(),
            ],
            'database' => [
                'driver' => config('database.default'),
                'connection' => config('database.connections.' . config('database.default')),
            ],
        ]);
    }
}