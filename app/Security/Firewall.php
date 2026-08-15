<?php
/**
 * Security: Rate Limiter and Web Application Firewall (WAF)
 * 
 * First line of defense to prevent DoS attacks, SQL Injection, and XSS.
 * Placed at the very beginning of application bootstrap.
 */

class Firewall {
    private static $maxRequestsPerMinute = 200;
    private static $blockDuration = 600; // 10 minutes in seconds

    public static function run() {
        self::checkCors();
        self::checkWafRules();
        self::checkRateLimit();
    }

    private static function checkCors() {
        // Define explicitly allowed origins (no wildcard '*')
        $allowedOrigins = [
            'http://localhost',
            'http://localhost:8000',
            'http://127.0.0.1',
            'http://127.0.0.1:8000'
        ];

        // Automatically allow the current server's host
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $currentHost = $_SERVER['HTTP_HOST'] ?? '';
        if ($currentHost) {
            $allowedOrigins[] = $protocol . '://' . $currentHost;
        }

        $origin = $_SERVER['HTTP_ORIGIN'] ?? '';

        // If Origin header is present, check against whitelist
        if ($origin) {
            if (in_array($origin, $allowedOrigins)) {
                header("Access-Control-Allow-Origin: $origin");
                header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
                header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, Cache-Control");
                header("Access-Control-Allow-Credentials: true");
            } else {
                // If it's a preflight request from an unauthorized origin, block it
                if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
                    self::abort(403, "CORS Policy: Origin not allowed.");
                }
            }
        }

        // Always intercept preflight OPTIONS requests to avoid executing app logic
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'OPTIONS') {
            http_response_code(200);
            exit;
        }
    }

    private static function getClientIp() {
        $ip = '';
        if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
        } else {
            $ip = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';
        }
        return trim($ip);
    }

    private static function checkRateLimit() {
        $ip = self::getClientIp();
        if ($ip === 'UNKNOWN') return;

        // Determine if this is a sensitive endpoint that requires stricter limits
        $isSensitive = false;
        $maxLimit = self::$maxRequestsPerMinute;
        
        $scriptPath = $_SERVER['SCRIPT_NAME'] ?? '';
        $sensitiveEndpoints = [
            'login.php',
            'change_password.php',
            'users.php',
            'add_employee.php'
        ];
        
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            foreach ($sensitiveEndpoints as $endpoint) {
                if (strpos($scriptPath, $endpoint) !== false) {
                    $isSensitive = true;
                    $maxLimit = 10; // Strict limit: 10 requests per minute for sensitive POST actions
                    break;
                }
            }
        }

        // Use system temp directory for high-speed, volatile storage (RAM disk on many systems)
        $cacheDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'stela_waf_cache';
        
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0777, true);
        }

        $ipHash = md5($ip);
        $file = $cacheDir . DIRECTORY_SEPARATOR . $ipHash . '.json';
        
        $currentTime = time();
        $currentMinute = floor($currentTime / 60);

        $data = ['minute' => $currentMinute, 'count' => 0, 'blocked_until' => 0];

        if (file_exists($file)) {
            $content = @file_get_contents($file);
            if ($content) {
                $decoded = json_decode($content, true);
                if (is_array($decoded)) {
                    $data = $decoded;
                }
            }
        }

        // Check if currently blocked
        if ($data['blocked_until'] > $currentTime) {
            self::abort(429, "Too Many Requests. Your IP ($ip) has been temporarily blocked for suspicious activity. Please try again later.");
        }

        // Reset counter if minute changed
        if ($data['minute'] != $currentMinute) {
            $data['minute'] = $currentMinute;
            $data['count'] = 0;
        }

        $data['count']++;

        // Block if limit exceeded
        if ($data['count'] > $maxLimit) {
            $data['blocked_until'] = $currentTime + self::$blockDuration;
            @file_put_contents($file, json_encode($data), LOCK_EX);
            $message = $isSensitive 
                ? "Too Many Requests. Security rate limit exceeded for sensitive operation. IP blocked for 10 minutes."
                : "Too Many Requests. Global rate limit exceeded. IP blocked for 10 minutes.";
            self::abort(429, $message);
        }

        // Save state
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    private static function checkWafRules() {
        // Bad Bots Block
        $userAgent = strtolower($_SERVER['HTTP_USER_AGENT'] ?? '');
        $badBots = ['sqlmap', 'nikto', 'dirb', 'nmap', 'curl', 'wget', 'python-requests'];
        foreach ($badBots as $bot) {
            if (strpos($userAgent, $bot) !== false) {
                self::abort(403, "Access Denied: Suspicious User-Agent.");
            }
        }

        // Inspect all incoming data
        $payload = $_GET + $_POST + $_COOKIE;
        array_walk_recursive($payload, function($value) {
            if (is_string($value)) {
                $valueLower = strtolower($value);
                
                // 1. Basic SQL Injection patterns
                $sqlPatterns = [
                    '/union\s+select/i',
                    '/select\s+.*\s+from/i',
                    '/insert\s+into/i',
                    '/update\s+.*\s+set/i',
                    '/delete\s+from/i',
                    '/drop\s+table/i',
                    '/truncate\s+table/i',
                    '/exec\s*\(/i',
                    '/benchmark\s*\(/i',
                    '/sleep\s*\(/i',
                    '/load_file\s*\(/i'
                ];

                foreach ($sqlPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::abort(403, "Access Denied: Malicious SQL payload detected.");
                    }
                }

                // 2. Cross-Site Scripting (XSS)
                $xssPatterns = [
                    '/<script.*?>/i',
                    '/javascript:/i',
                    '/vbscript:/i',
                    '/onload=/i',
                    '/onerror=/i',
                    '/onmouseover=/i'
                ];

                foreach ($xssPatterns as $pattern) {
                    if (preg_match($pattern, $value)) {
                        self::abort(403, "Access Denied: Malicious XSS payload detected.");
                    }
                }

                // 3. Path Traversal
                if (strpos($value, '../') !== false || strpos($value, '..\\') !== false) {
                    self::abort(403, "Access Denied: Path Traversal detected.");
                }
            }
        });
    }

    private static function abort($code, $message) {
        http_response_code($code);
        header('Content-Type: text/plain; charset=utf-8');
        echo $message;
        exit;
    }
}
