<?php

namespace App\Services;

class SenderNameExtractor
{
    /**
     * Extract sender name from text using multiple patterns
     */
    public function extractFromText(string $text, string $subject = ''): ?string
    {
        $textLower = strtolower($text);
        $fullText = $subject . ' ' . $textLower;
        $senderName = null;
        
        // Pattern 1: "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        if (preg_match('/from\s+([A-Z][A-Z\s]+?)(?:\s+to|$)/i', $text, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 1b: "TRANSFER FROM NAME" format in Description
        elseif (preg_match('/description[\s]*:[\s]*.*?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z][A-Z\s]+?)(?:-|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 2: GTBank description with "CODE-NAME TRF FOR" format
        elseif (preg_match('/description[\s:]+.*?([\d\-]+\s*-\s*)([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[2] ?? '');
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 2b: Direct format in text "CODE-NAME TRF FOR"
        elseif (preg_match('/[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: Extract from Remarks field (just the name, no FROM)
        elseif (preg_match('/(?:remark|remarks)[\s:]+([A-Z][A-Z\s]{2,}?)(?:\s|$)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA)\s+/i', '', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 4: Other standard patterns
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i', $fullText, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        
        // Clean up sender name
        if ($senderName) {
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            
            // FILTER OUT EMAIL ADDRESSES - sender name cannot be an email
            if (preg_match('/@/', $senderName) || filter_var($senderName, FILTER_VALIDATE_EMAIL)) {
                $senderName = null;
            }
            
            // Filter out common email patterns
            if (preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $senderName)) {
                $senderName = null;
            }
            
            // Filter out email-like patterns (e.g., "gens@gtbank.com")
            if (preg_match('/@[a-z0-9.-]+/i', $senderName)) {
                $senderName = null;
            }
            
            if (strlen($senderName) < 3) {
                $senderName = null;
            }
        }
        
        return $senderName;
    }
    
    /**
     * Extract sender name from HTML using multiple patterns
     */
    public function extractFromHtml(string $html): ?string
    {
        $senderName = null;
        
        // Pattern 1: GTBank HTML table - Description field contains "FROM NAME TO"
        if (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s]*from\s+([A-Z][A-Z\s]+?)(?:\s+to|$)/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 1b: "TRANSFER FROM NAME" format in Description HTML
        elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>.*?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z][A-Z\s]+?)(?:-|<\/td>|$)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 2: GTBank HTML table - Description field contains "CODE-NAME TRF FOR"
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>.*?([\d\-]+\s*-\s*)([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
            $potentialName = trim($matches[2] ?? '');
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: Extract from Remarks field in HTML (just the name, no FROM)
        elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:remark|remarks)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>[\s]*([A-Z][A-Z\s]{2,}?)[\s]*<\/td>/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA)\s+/i', '', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 4: GTBank HTML table - Description in same cell with "FROM"
        elseif (preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]+from\s+([A-Z][A-Z\s]+?)(?:\s+to|$)/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 5: GTBank text format "FROM SOLOMON INNOCENT AMITHY TO SQUA"
        elseif (preg_match('/from\s+([A-Z][A-Z\s]+?)(?:\s+to|$)/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 6: HTML table with "From" or "Sender" label
        elseif (preg_match('/<td[^>]*>[\s]*(?:from|sender|payer|depositor|account\s*name|name)[\s:]*<\/td>\s*<td[^>]*>[\s]*([A-Z][A-Z\s]+?)[\s]*<\/td>/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        // Pattern 7: Standard patterns in HTML
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|<\/)/i', $html, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        
        // Clean up sender name
        if ($senderName) {
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            
            // FILTER OUT EMAIL ADDRESSES - sender name cannot be an email
            if (preg_match('/@/', $senderName) || filter_var($senderName, FILTER_VALIDATE_EMAIL)) {
                $senderName = null;
            }
            
            // Filter out common email patterns
            if (preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $senderName)) {
                $senderName = null;
            }
            
            // Filter out email-like patterns (e.g., "gens@gtbank.com")
            if (preg_match('/@[a-z0-9.-]+/i', $senderName)) {
                $senderName = null;
            }
            
            if (strlen($senderName) < 3) {
                $senderName = null;
            }
        }
        
        return $senderName;
    }
}
