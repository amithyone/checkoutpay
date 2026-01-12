<?php

namespace App\Services;

/**
 * Advanced Name Extractor - Comprehensive system for extracting sender names
 * from bank transaction emails with multiple fallback strategies
 */
class AdvancedNameExtractor
{
    /**
     * Extract sender name using comprehensive multi-strategy approach
     */
    public function extract(string $textBody, string $htmlBody, string $subject = ''): ?string
    {
        // Step 1: Normalize and decode all inputs
        $text = $this->normalizeText($textBody);
        $html = $this->normalizeHtml($htmlBody);
        $combined = $text . ' ' . $html;
        
        // Step 2: Try extraction strategies in priority order
        $strategies = [
            'description_field_transfer_from',
            'description_field_code_name',
            'description_field_from_to',
            'description_field_simple_from',
            'remarks_field',
            'html_table_description',
            'html_table_remarks',
            'generic_from_pattern',
            'xtrapay_format',
        ];
        
        foreach ($strategies as $strategy) {
            $name = $this->executeStrategy($strategy, $text, $html, $combined);
            if ($name && $this->isValidName($name)) {
                return $this->cleanName($name);
            }
        }
        
        return null;
    }
    
    /**
     * Execute a specific extraction strategy
     */
    protected function executeStrategy(string $strategy, string $text, string $html, string $combined): ?string
    {
        switch ($strategy) {
            case 'description_field_transfer_from':
                return $this->extractFromDescriptionTransferFrom($text, $html, $combined);
            
            case 'description_field_code_name':
                return $this->extractFromDescriptionCodeName($text, $html, $combined);
            
            case 'description_field_from_to':
                return $this->extractFromDescriptionFromTo($text, $html, $combined);
            
            case 'description_field_simple_from':
                return $this->extractFromDescriptionSimpleFrom($text, $html, $combined);
            
            case 'remarks_field':
                return $this->extractFromRemarks($text, $html, $combined);
            
            case 'html_table_description':
                return $this->extractFromHtmlTableDescription($html);
            
            case 'html_table_remarks':
                return $this->extractFromHtmlTableRemarks($html);
            
            case 'generic_from_pattern':
                return $this->extractFromGenericFromPattern($text, $html, $combined);
            
            case 'xtrapay_format':
                return $this->extractFromXtraPayFormat($text, $html, $combined);
            
            default:
                return null;
        }
    }
    
    /**
     * Strategy 1: Description field with "TRANSFER FROM NAME" pattern
     * Format: "Description : CODE-TRANSFER FROM NAME-OPAY-..."
     * Or: "Description : =20 CODE-TRANSFER FROM = NAME-OPAY-..."
     */
    protected function extractFromDescriptionTransferFrom(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            // Pattern 1: TRANSFER FROM = NAME (with equals sign and quoted-printable)
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s*-\s*TRANSFER\s+FROM\s*=\s*([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i',
            // Pattern 2: TRANSFER FROM NAME (without equals sign)
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i',
            // Pattern 3: TRANSFER FROM NAME (with quoted-printable spaces)
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i',
            // Pattern 4: More flexible - any digits/dashes before TRANSFER FROM
            '/description[\s]*:[\s]*(?:=20\s*)?.*?TRANSFER\s+FROM\s*=\s*([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i',
            '/description[\s]*:[\s]*(?:=20\s*)?.*?TRANSFER\s+FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                return $this->cleanQuotedPrintable($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 2: Description field with "CODE-NAME TRF FOR" pattern
     * Format: "Description : CODE-NAME TRF FOR CUSTOMER..."
     * Or: "Description : AMITHY ONE M TRF FOR CUSTOMER..."
     * Or: "Description : CODE-TXN-CODE-CODE, NAME" (TXN format)
     */
    protected function extractFromDescriptionCodeName(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            // Pattern 1: CODE-NAME TRF FOR
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i',
            // Pattern 2: NAME TRF FOR (without code)
            '/description[\s]*:[\s]*(?:=20\s*)?([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i',
            // Pattern 3: TXN format - CODE-TXN-CODE-CODE, NAME (extract name after comma)
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+-TXN-[\d\-]+-[A-Z0-9=]+\s*[,\-]\s*([A-Z][A-Z\s]{2,}?)(?:\s|$|Amount)/i',
            // Pattern 4: TXN format - extract name after last dash/comma before Amount
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+-TXN-.*?[,\-]\s*([A-Z][A-Z\s]{3,}?)(?:\s+Amount|$)/i',
            // Pattern 5: More flexible - extract name before TRF/TRANSFER
            '/description[\s]*:[\s]*(?:=20\s*)?.*?([A-Z][A-Z\s]{3,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                $name = $matches[1];
                // Remove leading digits/dashes/codes
                $name = preg_replace('/^[\d\-\s]+/', '', $name);
                // Remove transaction codes if present
                $name = preg_replace('/^[A-Z0-9\-]+[,\-]\s*/', '', $name);
                $name = $this->cleanQuotedPrintable($name);
                if (strlen(trim($name)) >= 3) {
                    return $name;
                }
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 3: Description field with "FROM NAME TO" pattern
     * Format: "Description : CODE FROM NAME TO DESTINATION"
     */
    protected function extractFromDescriptionFromTo(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            '/description[\s]*:[\s]*(?:=20\s*)?.*?FROM\s+([A-Z][A-Z\s=]+?)\s+TO\s+[A-Z]/i',
            '/description[\s]*:[\s]*(?:=20\s*)?.*?FROM\s+([A-Z][A-Z\s=]+?)\s+TO/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                return $this->cleanQuotedPrintable($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 4: Description field with simple "FROM NAME" pattern
     * Format: "Description : CODE FROM NAME"
     */
    protected function extractFromDescriptionSimpleFrom(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            '/description[\s]*:[\s]*(?:=20\s*)?.*?FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$|\s+Amount)/i',
            '/description[\s]*:[\s]*(?:=20\s*)?[\d\-]+\s+FROM\s+([A-Z][A-Z\s=]+?)(?:-|OPAY|TO|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                return $this->cleanQuotedPrintable($matches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 5: Extract from Remarks field
     * Format: "Remarks : NAME" or "Remarks : NT NAME"
     */
    protected function extractFromRemarks(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            // Pattern 1: Remarks with prefix (NT, MR, etc.)
            '/remarks[\s]*:[\s]*(?:NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)[\s]+([A-Z][A-Z\s]{2,}?)(?:\s|\.|$)/i',
            // Pattern 2: Remarks without prefix
            '/remarks[\s]*:[\s]*([A-Z][A-Z\s]{2,}?)(?:\s|\.|$)/i',
            // Pattern 3: HTML table Remarks
            '/(?s)<td[^>]*>[\s]*remarks[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>[\s]*(?:NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)?[\s]*([A-Z][A-Z\s]{2,}?)[\s]*<\/td>/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                $name = trim($matches[1]);
                $name = rtrim($name, '. ');
                // Remove common prefixes if still present
                $name = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $name);
                if (strlen($name) >= 3) {
                    return strtolower($name);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 6: Extract from HTML table Description cell
     */
    protected function extractFromHtmlTableDescription(string $html): ?string
    {
        $patterns = [
            // Pattern 1: Description cell with TRANSFER FROM
            '/(?s)<td[^>]*>[\s]*(?:description|remarks)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?TRANSFER\s+FROM\s*=\s*([A-Z][A-Z\s=<>]+?)(?:-|OPAY|TO|<\/td>)/i',
            '/(?s)<td[^>]*>[\s]*(?:description|remarks)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?TRANSFER\s+FROM\s+([A-Z][A-Z\s=<>]+?)(?:-|OPAY|TO|<\/td>)/i',
            // Pattern 2: Description cell with FROM TO
            '/(?s)<td[^>]*>[\s]*(?:description|remarks)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?FROM\s+([A-Z][A-Z\s=<>]+?)\s+TO/i',
            // Pattern 3: Description cell with simple FROM
            '/(?s)<td[^>]*>[\s]*(?:description|remarks)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>.*?FROM\s+([A-Z][A-Z\s=<>]+?)(?:-|OPAY|<\/td>)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $html, $matches)) {
                $name = strip_tags($matches[1]);
                $name = $this->cleanQuotedPrintable($name);
                if (strlen(trim($name)) >= 3) {
                    return strtolower(trim($name));
                }
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 7: Extract from HTML table Remarks cell
     */
    protected function extractFromHtmlTableRemarks(string $html): ?string
    {
        if (preg_match('/(?s)<td[^>]*>[\s]*remarks[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>[\s]*([A-Z][A-Z\s]{2,}?)[\s]*<\/td>/i', $html, $matches)) {
            $name = strip_tags($matches[1]);
            $name = rtrim($name, '. ');
            $name = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $name);
            if (strlen(trim($name)) >= 3) {
                return strtolower(trim($name));
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 8: Generic FROM pattern anywhere in text
     */
    protected function extractFromGenericFromPattern(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            '/FROM\s+([A-Z][A-Z\s]{2,}?)\s+TO\s+[A-Z]/i',
            '/FROM\s+([A-Z][A-Z\s]{2,}?)(?:-|OPAY|TO|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                $name = $this->cleanQuotedPrintable($matches[1]);
                if (strlen(trim($name)) >= 3) {
                    return strtolower(trim($name));
                }
            }
        }
        
        return null;
    }
    
    /**
     * Strategy 9: XtraPay format
     * Format: "FROM OPAY/ NAME" or "received from NAME"
     */
    protected function extractFromXtraPayFormat(string $text, string $html, string $combined): ?string
    {
        $patterns = [
            // Pattern 1: FROM OPAY/ DIVINE FAVOUR UMEANO-UGOCHUKWU
            '/FROM\s+OPAY\/\s*([A-Z][A-Z\s\-]+?)(?:-Support|\/|\s|$)/i',
            // Pattern 2: received from DIVINE FAVOUR UMEANO-UGOCHUKWU
            '/received\s+from\s+([A-Z][A-Z\s\-]+?)(?:\||\s|$)/i',
            // Pattern 3: Description: XtraPay | FROM OPAY/ NAME
            '/description[\s:]+.*?FROM\s+OPAY\/\s*([A-Z][A-Z\s\-]+?)(?:-Support|\/|\s|$)/i',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $combined, $matches)) {
                $name = trim($matches[1]);
                // Remove "Support" suffix if present
                $name = preg_replace('/-Support$/i', '', $name);
                if (strlen($name) >= 3) {
                    return strtolower($name);
                }
            }
        }
        
        return null;
    }
    
    /**
     * Normalize text body
     */
    protected function normalizeText(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode quoted-printable
        $text = $this->decodeQuotedPrintable($text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove HTML tags
        $text = strip_tags($text);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        return trim($text);
    }
    
    /**
     * Normalize HTML body
     */
    protected function normalizeHtml(string $html): string
    {
        if (empty($html)) {
            return '';
        }
        
        // Decode quoted-printable
        $html = $this->decodeQuotedPrintable($html);
        
        // Decode HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        return $html;
    }
    
    /**
     * Decode quoted-printable encoding
     */
    protected function decodeQuotedPrintable(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode =XX format (hex)
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Handle soft line breaks
        $text = preg_replace('/=\r?\n/', '', $text);
        $text = preg_replace('/=\s*\n/', "\n", $text);
        
        return $text;
    }
    
    /**
     * Clean quoted-printable artifacts from extracted name
     */
    protected function cleanQuotedPrintable(string $name): string
    {
        // Replace =20 with space
        $name = preg_replace('/=20/', ' ', $name);
        
        // Replace standalone = with space
        $name = preg_replace('/\s*=\s*/', ' ', $name);
        
        // Normalize whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        
        return trim($name);
    }
    
    /**
     * Clean and normalize extracted name
     */
    protected function cleanName(string $name): string
    {
        $name = trim($name);
        
        // Remove "FROM" prefix if present
        $name = preg_replace('/^from\s+/i', '', $name);
        
        // Remove trailing punctuation
        $name = rtrim($name, '.,;:!?-');
        
        // Remove common prefixes
        $name = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|MALLAM|MALAM)\s+/i', '', $name);
        
        // Remove common words that shouldn't be in names
        $name = preg_replace('/\b(thank|you|for|choosing|important|us|if|you|would|prefer|that|we|do|not|display|your|account|balance|in|every|transaction|alert|sent|to|via|email|please|dial|privacy|security|bank|details|is|are|as|follows|current|available|value|date|time|document|number|location|transaction|notification|guaranty|trust|electronic|service|gens|wish|inform|occurred|on|with|details|shown|below)\b/i', '', $name);
        
        // Normalize whitespace
        $name = preg_replace('/\s+/', ' ', $name);
        
        return strtolower(trim($name));
    }
    
    /**
     * Validate if extracted text is a valid name
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
        
        // Cannot be an email address
        if (strpos($name, '@') !== false || filter_var($name, FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        
        // Cannot be all digits
        if (preg_match('/^\d+$/', $name)) {
            return false;
        }
        
        // Cannot be account number (10+ digits)
        if (preg_match('/^\d{10,}$/', $name)) {
            return false;
        }
        
        // Must contain at least one letter
        if (!preg_match('/[A-Za-z]/', $name)) {
            return false;
        }
        
        // Cannot be a URL
        if (preg_match('/https?:\/\//i', $name)) {
            return false;
        }
        
        // Cannot be single letter or initials only
        if (preg_match('/^[A-Z]\s*$|^[A-Z]\s+[A-Z]\s*$/i', $name)) {
            return false;
        }
        
        // Cannot be all uppercase single word (likely a code) unless it's a common abbreviation
        if (preg_match('/^[A-Z]{2,10}$/', $name) && !preg_match('/\s/', $name)) {
            $commonAbbrevs = ['NT', 'MR', 'MRS', 'MS', 'DR', 'PROF', 'ENG', 'CHIEF', 'ALHAJI', 'ALHAJA'];
            if (!in_array(strtoupper($name), $commonAbbrevs)) {
                return false;
            }
        }
        
        // Filter out common email/transaction words
        $invalidWords = ['thank', 'you', 'for', 'choosing', 'important', 'us', 'if', 'you', 'would', 'prefer', 
                        'that', 'we', 'do', 'not', 'display', 'your', 'account', 'balance', 'in', 'every', 
                        'transaction', 'alert', 'sent', 'to', 'via', 'email', 'please', 'dial', 'privacy', 
                        'security', 'bank', 'details', 'is', 'are', 'as', 'follows', 'current', 'available',
                        'value', 'date', 'time', 'document', 'number', 'location', 'notification', 'guaranty',
                        'trust', 'electronic', 'service', 'gens', 'wish', 'inform', 'occurred', 'on', 'with',
                        'details', 'shown', 'below', 'from', 'opay', 'xtrapay', 'received'];
        $nameWords = explode(' ', strtolower($name));
        $validWords = array_filter($nameWords, function($word) use ($invalidWords) {
            return !in_array($word, $invalidWords) && strlen($word) >= 2;
        });
        
        if (empty($validWords)) {
            return false;
        }
        
        // If all words are invalid, reject
        if (count($validWords) === 0) {
            return false;
        }
        
        return true;
    }
}
