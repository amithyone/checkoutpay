<?php

namespace App\Services;

class SenderNameExtractor
{
    /**
     * Extract sender name from text using structured approach (like amount extraction)
     * Priority order (same logic as amount extraction for reliability):
     * 1. Description field line with "FROM NAME" pattern (MOST RELIABLE - structured position)
     * 2. "FROM NAME TO" pattern anywhere in text
     * 3. Description field with "CODE-NAME TRF FOR" pattern
     * 4. Remarks/Narration fields
     * 5. Generic name patterns
     */
    public function extractFromText(string $text, string $subject = ''): ?string
    {
        $text = $this->normalizeText($text);
        $fullText = $this->normalizeText($subject . ' ' . $text);
        $senderName = null;
        
        // PRIORITY 1: Extract from description field line (MOST RELIABLE - structured position like amount)
        // Format: "Description : 43digits FROM FULL NAME -" or "Description : 43digits FROM FULL NAME TO"
        // Strategy: Find the description line first, then extract name after "FROM" (like we extract amount from position 11-16)
        $descriptionLine = null;
        if (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $text, $descLineMatches)) {
            $descriptionLine = trim($descLineMatches[1]);
            
            // Extract name from this description line (structured approach)
            // Pattern 1a: TRANSFER FROM: NAME (with colon) - e.g., "TRANSFER FROM: JOHN = AGBO"
            if (preg_match('/TRANSFER\s+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:\s+TO|\s*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1b: TRANSFER FROM NAME (without colon, before OPAY) - e.g., "TRANSFER FROM JIMMY = ALEX PAM-OPAY"
            elseif (preg_match('/TRANSFER\s+FROM[\s]+([A-Z][A-Z\s=]{2,}?)(?:-OPAY|[\s\-]+OPAY|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c: UNION TRANSFER = FROM NAME - e.g., "UNION TRANSFER = FROM UTEBOR PAUL C"
            elseif (preg_match('/UNION\s+TRANSFER\s*=\s*FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = rtrim($potentialName, '- ');
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1d: digits FROM = name (with equals sign) - e.g., "digits FROM = SOLOMON INNOCENT AMITHY"
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]*=[\s]*([A-Z][A-Z\s]{2,}?)(?:[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1e: digits FROM name (with optional dash, TO, or end) - original pattern
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s=]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                // Remove trailing dash if present
                $potentialName = rtrim($potentialName, '- ');
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1f: digits FROM: name (with colon) - e.g., "digits FROM: DESTINY = IWAJOMO"
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1g: digits FROM name (end of line)
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s=]{2,}?)$/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
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
        
        // PRIORITY 5: Extract from Remarks/Narration field - UBA format (PRIORITY - check first)
        // Format: "Remarks : 4-UBA-SOLO MON FEMI GARBA" - name is after "-UBA-"
        if (!$senderName && preg_match('/(?:remark|remarks|narration|narrative)[\s]*:[\s]*[^\n\r]*?[\-]UBA[\-]([A-Z][A-Z\s]+?)(?:\s*$|\s+Time|\s+Transaction|\s+Amount|\s+Value)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // PRIORITY 5a: Extract from Remarks/Narration field - handle dash-separated format
        // Format: "Remarks : D-FAIRMONE Y-JOHN AGBO" or "Remarks : NAME"
        elseif (!$senderName && preg_match('/(?:remark|remarks|narration|narrative)[\s]*:[\s]*[^\n\r]*?[\-]([A-Z][A-Z\s]{2,}?)(?:\s|$|Time|Transaction)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes and service names
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM|D-FAIRMONE\s+Y)\s*/i', '', $potentialName);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if ($this->isValidName($potentialName)) {
                $senderName = strtolower($potentialName);
            }
        }
        // Pattern 5b: Remarks field without dash (original pattern)
        elseif (!$senderName && preg_match('/(?:remark|remarks|narration|narrative)[\s]*:[\s]*([A-Z][A-Z\s]{2,}?)(?:\s|$|[\s\-])/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $potentialName);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
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
     * Extract sender name from HTML using structured approach (like amount extraction)
     */
    public function extractFromHtml(string $html): ?string
    {
        $html = $this->normalizeText($html);
        $senderName = null;
        
        // PRIORITY 1: HTML table - Description field with "FROM NAME" (structured like amount extraction)
        // Format: <td>Description</td><td>:</td><td>43digits FROM NAME TO</td>
        // Strategy: Extract the description cell content, then parse it (like we do with amount)
        if (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>([^<]+)<\/td>/i', $html, $cellMatches)) {
            $descriptionCellContent = strip_tags($cellMatches[1]);
            $descriptionCellContent = preg_replace('/\s+/', ' ', trim($descriptionCellContent));
            
            // Extract name from this structured cell (like amount extraction)
            // Pattern: digits FROM name (with optional dash, TO, or end)
            if (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionCellContent, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = rtrim($potentialName, '- ');
                if ($this->isValidName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
        }
        
        // PRIORITY 2: HTML table - Description field with "FROM NAME TO"
        if (!$senderName && preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?FROM[\s]+([A-Z][A-Z\s]{2,}?)[\s]+TO/i', $html, $matches)) {
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
     * Extract name from description field line (structured approach like amount)
     * This is the MOST RELIABLE method - extracts from the exact same source as amount
     */
    public function extractFromDescriptionField(string $descriptionLine): ?string
    {
        if (empty($descriptionLine)) {
            return null;
        }
        
        $descriptionLine = $this->normalizeText($descriptionLine);
        
        // Pattern 1: digits FROM name (with optional dash, TO, or end) - MOST COMMON
        // Format: "43digits FROM SOLOMON INNOCENT -" or "43digits FROM SOLOMON INNOCENT TO"
        if (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            $potentialName = rtrim($potentialName, '- ');
            if ($this->isValidName($potentialName)) {
                return strtolower($potentialName);
            }
        }
        
        // Pattern 2: digits FROM name (end of line)
        if (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s]{2,}?)$/i', $descriptionLine, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            if ($this->isValidName($potentialName)) {
                return strtolower($potentialName);
            }
        }
        
        // Pattern 3: CODE-NAME TRF FOR format
        if (preg_match('/[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $descriptionLine, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            if ($this->isValidName($potentialName)) {
                return strtolower($potentialName);
            }
        }
        
        return null;
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
        // Handle = characters in names (convert "JOHN = AGBO" to "JOHN AGBO")
        $name = preg_replace('/\s*=\s*/', ' ', $name);
        
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
}
