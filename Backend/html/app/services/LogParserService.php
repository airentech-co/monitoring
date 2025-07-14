<?php

class LogParserService {
    
    /**
     * Parse browser history log and return structured data
     */
    public function parseBrowserHistory($content) {
        if (empty($content)) {
            return [];
        }
        
        $lines = explode("\n", trim($content));
        $parsedData = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Parse format: Date: 2025-07-12 17:31:46 | Browser: Chrome | URL: https://... | Title: ...
            if (preg_match('/Date: (.+?) \| Browser: (.+?) \| URL: (.+?) \| Title: (.+?) \| Last Visit: (.+)/', $line, $matches)) {
                $parsedData[] = [
                    'date' => trim($matches[1]),
                    'browser' => trim($matches[2]),
                    'url' => trim($matches[3]),
                    'title' => trim($matches[4])
                ];
            }
        }
        
        return $parsedData;
    }
    
    /**
     * Parse key logs and return structured data
     */
    public function parseKeyLogs($content) {
        if (empty($content)) {
            return [];
        }
        
        $lines = explode("\n", trim($content));
        $parsedData = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Parse format: Date: 2025-07-12 17:48:59 | Application: Explorer.EXE (build_cmake - File Explorer) | Key: K
            if (preg_match('/Date: (.+?) \| Application: (.+?) \| Key: (.+)/', $line, $matches)) {
                $parsedData[] = [
                    'date' => trim($matches[1]),
                    'application' => trim($matches[2]),
                    'key' => trim($matches[3])
                ];
            }
        }
        
        return $parsedData;
    }
    
    /**
     * Parse USB logs and return structured data
     */
    public function parseUsbLogs($content) {
        if (empty($content)) {
            return [];
        }
        
        $lines = explode("\n", trim($content));
        $parsedData = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Parse format: Date: 2025-07-12 17:57:01 | Device: USB Composite Device | Action: Connected
            if (preg_match('/Date: (.+?) \| Device: (.+?) \| Action: (.+)/', $line, $matches)) {
                $parsedData[] = [
                    'date' => trim($matches[1]),
                    'device' => trim($matches[2]),
                    'action' => trim($matches[3])
                ];
            }
        }
        
        return $parsedData;
    }
    
    /**
     * Parse generic log content and return structured data
     */
    public function parseGenericLog($content, $logType = 'generic') {
        if (empty($content)) {
            return [];
        }
        
        $lines = explode("\n", trim($content));
        $parsedData = [];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            // Try to parse common patterns
            if (preg_match('/Date: (.+?) \| (.+)/', $line, $matches)) {
                $date = trim($matches[1]);
                $rest = trim($matches[2]);
                
                // Try to parse pipe-separated fields
                $fields = explode(' | ', $rest);
                $row = ['date' => $date];
                
                foreach ($fields as $field) {
                    if (strpos($field, ': ') !== false) {
                        list($key, $value) = explode(': ', $field, 2);
                        $row[trim($key)] = trim($value);
                    } else {
                        $row['data'] = trim($field);
                    }
                }
                
                $parsedData[] = $row;
            } else {
                // If no date pattern, treat as raw data
                $parsedData[] = ['raw_data' => trim($line)];
            }
        }
        
        return $parsedData;
    }
    
    /**
     * Get table headers based on parsed data
     */
    public function getTableHeaders($parsedData, $logType = 'generic') {
        if (empty($parsedData)) {
            return [];
        }
        
        $firstRow = $parsedData[0];
        $headers = array_keys($firstRow);
        
        // Map headers to display names
        $headerMap = [
            'date' => 'Date & Time',
            'browser' => 'Browser',
            'url' => 'URL',
            'title' => 'Page Title',
            'application' => 'Application',
            'key' => 'Key Pressed',
            'device' => 'Device',
            'action' => 'Action',
            'raw_data' => 'Log Entry',
            'data' => 'Data'
        ];
        
        $displayHeaders = [];
        foreach ($headers as $header) {
            $displayHeaders[] = isset($headerMap[$header]) ? $headerMap[$header] : ucfirst(str_replace('_', ' ', $header));
        }
        
        return $displayHeaders;
    }
    
    /**
     * Format URL for display (truncate if too long)
     */
    public function formatUrl($url, $maxLength = 80) {
        if (strlen($url) <= $maxLength) {
            return htmlspecialchars($url);
        }
        
        $truncated = substr($url, 0, $maxLength - 3) . '...';
        return '<span title="' . htmlspecialchars($url) . '">' . htmlspecialchars($truncated) . '</span>';
    }
    
    /**
     * Format title for display (truncate if too long)
     */
    public function formatTitle($title, $maxLength = 60) {
        if (strlen($title) <= $maxLength) {
            return htmlspecialchars($title);
        }
        
        $truncated = substr($title, 0, $maxLength - 3) . '...';
        return '<span title="' . htmlspecialchars($title) . '">' . htmlspecialchars($truncated) . '</span>';
    }
    
    /**
     * Get CSS classes for different log types
     */
    public function getLogTypeClasses($logType) {
        $classes = [
            'browser_history' => 'table-primary',
            'key_logs' => 'table-warning',
            'usb_logs' => 'table-info',
            'generic' => 'table-secondary'
        ];
        
        return isset($classes[$logType]) ? $classes[$logType] : 'table-secondary';
    }
    
    /**
     * Get icon for different log types
     */
    public function getLogTypeIcon($logType) {
        $icons = [
            'browser_history' => 'fas fa-globe',
            'key_logs' => 'fas fa-keyboard',
            'usb_logs' => 'fas fa-usb',
            'generic' => 'fas fa-file-alt'
        ];
        
        return isset($icons[$logType]) ? $icons[$logType] : 'fas fa-file-alt';
    }
} 