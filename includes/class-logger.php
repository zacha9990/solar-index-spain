<?php
defined('ABSPATH') || exit;

class SIS_Logger {

    private string $context;

    public function __construct(string $context = 'SIS') {
        $this->context = $context;
    }

    public function info(string $message): void {
        $this->write('INFO', $message);
    }

    public function success(string $message): void {
        $this->write('OK', $message);
    }

    public function error(string $message): void {
        $this->write('ERROR', $message);
        // Also write to WP debug log
        if (defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
            error_log("[{$this->context}] ERROR: {$message}");
        }
    }

    private function write(string $level, string $message): void {
        $timestamp = date('Y-m-d H:i:s');
        $line      = "[{$timestamp}] [{$level}] [{$this->context}] {$message}";

        // Append to option-based log (last 200 lines)
        $log = get_option('sis_fetch_log', '');
        $lines = array_filter(explode("\n", $log));
        $lines[] = $line;
        $lines = array_slice($lines, -200);
        update_option('sis_fetch_log', implode("\n", $lines), false);

        // Also echo when running in CLI context
        if (php_sapi_name() === 'cli') {
            echo $line . PHP_EOL;
        }
    }
}
