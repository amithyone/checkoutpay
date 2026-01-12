<?php

namespace App\Services;

class SenderNameExtractor
{
    /**
     * Extract sender name from text using multiple patterns (IMPROVED VERSION)
     * Priority order:
     * 1. Description field with "FROM NAME" pattern (most reliable)
     * 2. "FROM NAME TO" pattern anywhere in text
     * 3. Description field with "CODE-NAME TRF FOR" pattern
     * 4. Remarks/Narration fields
     * 5. Generic name patterns
     */
    public function extractFromText(string $text, string $subject = ''): ?string
    {
        // Use AdvancedNameExtractor for comprehensive extraction
        $advancedExtractor = new AdvancedNameExtractor();
        return $advancedExtractor->extract($text, '', $subject);
    }
    
    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use AdvancedNameExtractor instead
     */
    public function extractFromTextLegacy(string $text, string $subject = ''): ?string
    {
        // CRITICAL: Decode quoted-printable encoding FIRST (e.g., =20 becomes space)
        // This is essential because text may have quoted-printable encoding from email parsing
        $text = $this->decodeQuotedPrintable($text);
        
        $text = $this->normalizeText($text);
        $fullText = $this->normalizeText($subject . ' ' . $text);
        $senderName = null;
        
        // PRIORITY 1: Description field with "FROM NAME" pattern (MOST RELIABLE)
        // Format: "Description : 43digits FROM SOLOMON INNOCENT TO SQUA"
        // Or: "Description : 43digits FROM SOLOMON INNOCENT -"
        // Or: "Description : 43digits FROM SOLOMON INNOCENT"
        // Pattern: description : numbers FROM name (with optional dash, TO, or end)
        // CRITICAL: Handle quoted-printable encoding (=20 for space, = for =)
        // Pattern 1a: TRANSFER FROM = NAME (with equals sign)
        if (preg_match('/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s*-\s*TRANSFER\s+FROM\s*=\s*([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Clean up quoted-printable artifacts (=20 becomes space, = becomes space if standalone)
            $potentialName = preg_replace('/=20/', ' ', $potentialName);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            $potentialName = rtrim($potentialName, '- ');
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Pattern 1b: TRANSFER FROM NAME (without equals sign)
        if (!$senderName && preg_match('/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Clean up quoted-printable artifacts
            $potentialName = preg_replace('/=20/', ' ', $potentialName);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            $potentialName = rtrim($potentialName, '- ');
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Also try simpler "FROM NAME" format (without TRANSFER) - handle quoted-printable =20
        if (!$senderName && preg_match('/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s+TRANSFER\s+FROM\s*=\s*([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Clean up quoted-printable artifacts (=20 becomes space, = becomes space if standalone)
            $potentialName = preg_replace('/=20/', ' ', $potentialName);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            $potentialName = rtrim($potentialName, '- ');
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Try "FROM NAME" format with =20 spaces
        if (!$senderName && preg_match('/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s+FROM\s+([A-Z][A-Z\s=]+?)(?:\s+TO|\s+OPAY|-|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Clean up quoted-printable artifacts
            $potentialName = preg_replace('/=20/', ' ', $potentialName);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            $potentialName = rtrim($potentialName, '- ');
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Try Remarks field as fallback (some emails have name only in Remarks)
        // Handle "NT AMITHY SOLOMON" format (with prefix)
        if (!$senderName && preg_match('/remarks[\s]*:[\s]*(?:NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)[\s]+([A-Z][A-Z\s]{2,}?)(?:\s|\.|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            $potentialName = rtrim($potentialName, '. ');
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Try Remarks without prefix
        if (!$senderName && preg_match('/remarks[\s]*:[\s]*([A-Z][A-Z\s]{2,}?)(?:\s|\.|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            $potentialName = rtrim($potentialName, '. ');
            // Remove common prefixes if present
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $potentialName);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Try extracting from HTML text_body (email 600 has HTML in text_body)
        if (!$senderName && preg_match('/TRANSFER\s+FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Clean up quoted-printable and HTML artifacts
            $potentialName = preg_replace('/=20/', ' ', $potentialName);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            $potentialName = strip_tags($potentialName); // Remove any HTML tags
            $potentialName = rtrim($potentialName, '- ');
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 2: "FROM NAME TO" pattern anywhere in text (flexible spacing)
        // Format: "FROM SOLOMON INNOCENT AMITHY TO SQUA" or "FROM NAME TO"
        if (!$senderName && preg_match('/FROM[\s]+([A-Z][A-Z\s]{2,}?)[\s]+TO[\s]+[A-Z]/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 3: Description field with "CODE-NAME TRF FOR" pattern
        // Format: "Description : CODE-NAME TRF FOR" or "CODE-NAME TRF FOR"
        if (!$senderName && preg_match('/description[\s]*:[\s]*.*?[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 4: Direct "CODE-NAME TRF FOR" pattern (without description label)
        if (!$senderName && preg_match('/[\d\-]{5,}\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 5: Extract from Remarks/Narration field
        // Format: "Remarks : NAME" or "Narration : NAME"
        if (!$senderName && preg_match('/(?:remark|remarks|narration|narrative)[\s]*:[\s]*([A-Z][A-Z\s]{2,}?)(?:\s|$|[\s\-])/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $potentialName);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 6: Generic "FROM NAME" pattern (without TO)
        // Format: "FROM SOLOMON INNOCENT" (might be at end of line)
        if (!$senderName && preg_match('/FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:\s*$|[\s\-]|[\s]+TO)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 7: Other standard patterns with labels
        if (!$senderName && preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s]*:[\s]*([A-Z][A-Z\s]{2,}?)(?:\s+to|\s+account|\s*$|[\s\-])/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // Clean up and validate
        if ($senderName) {
            $senderName = $this->cleanName($senderName);
            if (!$this->isValidName($senderName)) {
                $senderName = null;
            }
        }
        
        return $senderName;
    }
    
    /**
     * Extract sender name from HTML using multiple patterns (IMPROVED VERSION)
     */
    public function extractFromHtml(string $html): ?string
    {
        // Use AdvancedNameExtractor for comprehensive extraction
        $advancedExtractor = new AdvancedNameExtractor();
        return $advancedExtractor->extract('', $html, '');
    }
    
    /**
     * Legacy method - kept for backward compatibility
     * @deprecated Use AdvancedNameExtractor instead
     */
    public function extractFromHtmlLegacy(string $html): ?string
    {
        $html = $this->normalizeText($html);
        $senderName = null;
        
        // PRIORITY 1: HTML table - Description field with "FROM NAME TO"
        // Format: <td>Description</td><td>:</td><td>43digits FROM SOLOMON TO SQUA</td>
        if (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?FROM[\s]+([A-Z][A-Z\s]{2,}?)[\s]+TO/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 2: HTML table - Description field with "FROM NAME" (no TO)
        if (!$senderName && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:\s+TO|[\s\-]|<\/td>|$)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 3: HTML table - Description field with "CODE-NAME TRF FOR"
        if (!$senderName && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>.*?[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 4: HTML table - Remarks field (just the name)
        if (!$senderName && preg_match('/(?s)<td[^>]*>[\s]*(?:remark|remarks|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>[\s]*([A-Z][A-Z\s]{2,}?)[\s]*<\/td>/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $potentialName);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 5: HTML table - Description in same cell with "FROM"
        if (!$senderName && preg_match('/<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]+FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:\s+TO|[\s\-]|<\/td>|$)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 6: Plain text "FROM NAME TO" in HTML
        if (!$senderName && preg_match('/FROM[\s]+([A-Z][A-Z\s]{2,}?)[\s]+TO[\s]+[A-Z]/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 7: HTML table with "From" or "Sender" label
        if (!$senderName && preg_match('/<td[^>]*>[\s]*(?:from|sender|payer|depositor|account\s*name|name)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*([A-Z][A-Z\s]{2,}?)[\s]*<\/td>/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // PRIORITY 8: Generic patterns in HTML
        if (!$senderName && preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]{2,}?)(?:\s+to|\s+account|\s*:|<\/)/i', $html, $matches)) {
            $potentialName = trim($matches[1]);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        
        // Clean up and validate
        if ($senderName) {
            $senderName = $this->cleanName($senderName);
            if (!$this->isValidName($senderName)) {
                $senderName = null;
            }
        }
        
        return $senderName;
    }
    
    /**
     * Normalize text for better pattern matching
     */
    protected function normalizeText(string $text): string
    {
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Normalize whitespace (but preserve structure)
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Remove null bytes and control characters (except newlines)
        $text = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $text);
        
        return trim($text);
    }
    
    /**
     * Clean extracted name
     */
    protected function cleanName(string $name): string
    {
        // Normalize whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        $name = trim($name);
        
        // Remove trailing punctuation
        $name = rtrim($name, '.,;:!?-');
        
        return $name;
    }
    
    /**
     * Validate if extracted text looks like a real name
     */
    protected function isValidName(?string $name): bool
    {
        if (empty($name)) {
            return false;
        }
        
        $name = trim($name);
        
        // Must be at least 3 characters
        if (strlen($name) < 3) {
            return false;
        }
        
        // FILTER OUT EMAIL ADDRESSES - name cannot be an email
        if (preg_match('/@/', $name) || filter_var($name, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Filter out email patterns
        if (preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $name)) {
            return false;
        }
        
        // Filter out email-like patterns (e.g., "gens@gtbank.com")
        if (preg_match('/@[a-z0-9.-]+/i', $name)) {
            return false;
        }
        
        // Filter out URLs
        if (preg_match('/https?:\/\//i', $name)) {
            return false;
        }
        
        // Filter out phone numbers (all digits)
        if (preg_match('/^\d+$/', $name)) {
            return false;
        }
        
        // Filter out account numbers (10+ digits)
        if (preg_match('/^\d{10,}$/', $name)) {
            return false;
        }
        
        // Filter out single letters or initials only (like "A" or "A B")
        if (preg_match('/^[A-Z]\s*$|^[A-Z]\s+[A-Z]\s*$/i', $name)) {
            return false;
        }
        
        // Should contain at least one letter
        if (!preg_match('/[A-Za-z]/', $name)) {
            return false;
        }
        
        // Should not be all uppercase single word (likely a code)
        if (preg_match('/^[A-Z]{2,10}$/', $name) && !preg_match('/\s/', $name)) {
            // Allow if it's a common name abbreviation
            $commonAbbrevs = ['NT', 'MR', 'MRS', 'MS', 'DR', 'PROF', 'ENG', 'CHIEF', 'ALHAJI', 'ALHAJA'];
            if (!in_array(strtoupper($name), $commonAbbrevs)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Decode quoted-printable encoding (e.g., =20 becomes space, =3D becomes =)
     */
    protected function decodeQuotedPrintable(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode quoted-printable format: =XX where XX is hex
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Handle soft line breaks (trailing = at end of line)
        $text = preg_replace('/=\r?\n/', '', $text);
        $text = preg_replace('/=\s*\n/', "\n", $text);
        
        return $text;
    }
}
