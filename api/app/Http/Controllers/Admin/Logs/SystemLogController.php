<?php

namespace App\Http\Controllers\Admin\Logs;

use App\Http\Controllers\Controller;
use App\Models\SystemLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class SystemLogController extends Controller
{
    public function index(Request $request)
    {
        if (! Schema::hasTable('system_logs')) {
            return $this->fromFile($request);
        }

        $query = SystemLog::query()->orderByDesc('logged_at');

        if ($level = $request->string('level')->toString()) {
            $query->where('level', strtolower($level));
        }

        if ($from = $request->date('from')) {
            $query->where('logged_at', '>=', $from->startOfDay());
        }

        if ($to = $request->date('to')) {
            $query->where('logged_at', '<=', $to->endOfDay());
        }

        return response()->json(
            $query->paginate($request->integer('per_page', 25))
        );
    }

    private function fromFile(Request $request)
    {
        $path = storage_path('logs/laravel.log');
        $entries = [];

        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES) ?: [];
            $level = strtolower($request->string('level')->toString());

            foreach (array_reverse(array_slice($lines, -500)) as $index => $line) {
                $parsed = $this->parseLogLine($line);
                if ($level && ($parsed['level'] ?? '') !== $level) {
                    continue;
                }
                $entries[] = array_merge($parsed, ['id' => $index + 1]);
            }
        }

        $page = max(1, $request->integer('page', 1));
        $perPage = $request->integer('per_page', 25);
        $total = count($entries);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        return response()->json([
            'data' => $slice,
            'current_page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'last_page' => max(1, (int) ceil($total / $perPage)),
            'source' => 'file',
            'path' => $path,
        ]);
    }

    private function parseLogLine(string $line): array
    {
        if (preg_match('/^\[([^\]]+)\]\s+(\w+)\.(\w+):\s+(.*)$/', $line, $matches)) {
            return [
                'logged_at' => $matches[1],
                'channel' => $matches[2],
                'level' => strtolower($matches[3]),
                'message' => $matches[4],
                'context' => null,
            ];
        }

        return [
            'logged_at' => null,
            'channel' => 'file',
            'level' => 'info',
            'message' => $line,
            'context' => null,
        ];
    }
}
