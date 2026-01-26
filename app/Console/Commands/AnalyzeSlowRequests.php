<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class AnalyzeSlowRequests extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'performance:analyze-slow 
                            {--hours=24 : Analyze logs from last N hours}
                            {--min-duration=500 : Minimum duration in ms to consider slow}
                            {--top=20 : Show top N slowest requests}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Analyze slow requests from logs and identify bottlenecks';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $hours = (int) $this->option('hours');
        $minDuration = (int) $this->option('min-duration');
        $top = (int) $this->option('top');

        $logPath = storage_path('logs/laravel.log');
        
        if (!File::exists($logPath)) {
            $this->error('Log file not found: ' . $logPath);
            return 1;
        }

        $this->info("Analyzing slow requests from last {$hours} hours...");
        $this->info("Minimum duration: {$minDuration}ms");
        $this->newLine();

        // Read log file
        $logContent = File::get($logPath);
        $lines = explode("\n", $logContent);
        
        $slowRequests = [];
        $currentEntry = null;
        $cutoffTime = now()->subHours($hours);

        foreach ($lines as $line) {
            // Check if line contains slow request log
            if (str_contains($line, 'slow_request') || 
                str_contains($line, 'Slow request detected') ||
                str_contains($line, 'Very slow request detected') ||
                str_contains($line, 'Request performance metrics')) {
                
                // Extract JSON data from log line
                if (preg_match('/\{.*\}/', $line, $matches)) {
                    $data = json_decode($matches[0], true);
                    
                    if ($data && isset($data['duration_ms']) && $data['duration_ms'] >= $minDuration) {
                        // Check if log entry is within time window
                        $logTime = isset($data['timestamp']) 
                            ? \Carbon\Carbon::parse($data['timestamp'])
                            : null;
                        
                        if (!$logTime || $logTime->gte($cutoffTime)) {
                            $slowRequests[] = $data;
                        }
                    }
                }
            }
        }

        if (empty($slowRequests)) {
            $this->info("No slow requests found (>= {$minDuration}ms) in the last {$hours} hours.");
            return 0;
        }

        // Sort by duration (descending)
        usort($slowRequests, function($a, $b) {
            return ($b['duration_ms'] ?? 0) <=> ($a['duration_ms'] ?? 0);
        });

        // Show top N
        $topRequests = array_slice($slowRequests, 0, $top);

        $this->info("Found " . count($slowRequests) . " slow requests. Showing top {$top}:");
        $this->newLine();

        $tableData = [];
        foreach ($topRequests as $request) {
            $tableData[] = [
                'Duration (ms)' => round($request['duration_ms'] ?? 0, 2),
                'Queries' => $request['query_count'] ?? 0,
                'Query Time (ms)' => round($request['total_query_time_ms'] ?? 0, 2),
                'Memory (MB)' => round($request['memory_mb'] ?? 0, 2),
                'Method' => $request['method'] ?? 'N/A',
                'URL' => $this->truncateUrl($request['url'] ?? 'N/A', 50),
            ];
        }

        $this->table([
            'Duration (ms)',
            'Queries',
            'Query Time (ms)',
            'Memory (MB)',
            'Method',
            'URL',
        ], $tableData);

        // Show slow queries summary
        $this->newLine();
        $this->info("Slow Query Analysis:");
        
        $slowQueriesCount = 0;
        $slowQueriesBySql = [];
        
        foreach ($slowRequests as $request) {
            if (isset($request['slow_queries']) && is_array($request['slow_queries'])) {
                foreach ($request['slow_queries'] as $query) {
                    $slowQueriesCount++;
                    $sql = $query['sql'] ?? '';
                    // Normalize SQL (remove bindings for grouping)
                    $normalizedSql = preg_replace('/\?/', '?', $sql);
                    $normalizedSql = preg_replace('/\s+/', ' ', $normalizedSql);
                    
                    if (!isset($slowQueriesBySql[$normalizedSql])) {
                        $slowQueriesBySql[$normalizedSql] = [
                            'count' => 0,
                            'total_time' => 0,
                            'max_time' => 0,
                            'sql' => $sql,
                        ];
                    }
                    
                    $queryTime = (float) str_replace('ms', '', $query['time'] ?? 0);
                    $slowQueriesBySql[$normalizedSql]['count']++;
                    $slowQueriesBySql[$normalizedSql]['total_time'] += $queryTime;
                    $slowQueriesBySql[$normalizedSql]['max_time'] = max(
                        $slowQueriesBySql[$normalizedSql]['max_time'],
                        $queryTime
                    );
                }
            }
        }

        if ($slowQueriesCount > 0) {
            $this->info("Total slow queries found: {$slowQueriesCount}");
            
            // Sort by total time
            uasort($slowQueriesBySql, function($a, $b) {
                return $b['total_time'] <=> $a['total_time'];
            });

            $this->newLine();
            $this->info("Top Slow Queries:");
            
            $queryTable = [];
            $topQueries = array_slice($slowQueriesBySql, 0, 10, true);
            foreach ($topQueries as $sql => $stats) {
                $queryTable[] = [
                    'Count' => $stats['count'],
                    'Total Time (ms)' => round($stats['total_time'], 2),
                    'Max Time (ms)' => round($stats['max_time'], 2),
                    'Avg Time (ms)' => round($stats['total_time'] / $stats['count'], 2),
                    'SQL' => $this->truncateSql($stats['sql'], 60),
                ];
            }

            $this->table([
                'Count',
                'Total Time (ms)',
                'Max Time (ms)',
                'Avg Time (ms)',
                'SQL',
            ], $queryTable);
        } else {
            $this->info("No slow queries found (queries > 100ms).");
        }

        // Summary statistics
        $this->newLine();
        $this->info("Summary Statistics:");
        
        $avgDuration = array_sum(array_column($slowRequests, 'duration_ms')) / count($slowRequests);
        $avgQueries = array_sum(array_column($slowRequests, 'query_count')) / count($slowRequests);
        $maxDuration = max(array_column($slowRequests, 'duration_ms'));
        
        $this->line("Total slow requests: " . count($slowRequests));
        $this->line("Average duration: " . round($avgDuration, 2) . "ms");
        $this->line("Maximum duration: " . round($maxDuration, 2) . "ms");
        $this->line("Average queries per request: " . round($avgQueries, 2));
        $this->line("Total slow queries: " . $slowQueriesCount);

        return 0;
    }

    /**
     * Truncate URL for display
     */
    protected function truncateUrl(string $url, int $length): string
    {
        if (strlen($url) <= $length) {
            return $url;
        }
        
        return substr($url, 0, $length - 3) . '...';
    }

    /**
     * Truncate SQL for display
     */
    protected function truncateSql(string $sql, int $length): string
    {
        $sql = preg_replace('/\s+/', ' ', $sql);
        
        if (strlen($sql) <= $length) {
            return $sql;
        }
        
        return substr($sql, 0, $length - 3) . '...';
    }
}
