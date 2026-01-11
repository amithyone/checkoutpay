<?php

namespace App\Services;

class DescriptionFieldExtractor
{
    /**
     * Extract description field from text (flexible - 20+ digits)
     */
    public function extractFromText(string $text): ?string
    {
        // Try flexible pattern: description : (20+ digits) followed by space, FROM, dash, or end
        if (preg_match('/description[\s]*:[\s]*(\d{20,})(?:\s|FROM|-|$)/i', $text, $descMatches)) {
            return trim($descMatches[1]);
        } 
        // Fallback: Match description : ... then find longest digit sequence (20+)
        elseif (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $text, $descLineMatches)) {
            $descLine = $descLineMatches[1];
            if (preg_match('/(\d{20,})/', $descLine, $digitMatches)) {
                return trim($digitMatches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Extract description field from HTML (after converting to plain text)
     */
    public function extractFromHtml(string $html): ?string
    {
        // Convert HTML to plain text first
        $plainText = strip_tags($html);
        $plainText = preg_replace('/\s+/', ' ', $plainText);
        
        // Try simple pattern first: description : (43 digits) followed by space or FROM or end
        if (preg_match('/description[\s]*:[\s]*(\d{43})(?:\s|FROM|$)/i', $plainText, $descMatches)) {
            return trim($descMatches[1]);
        } 
        // Fallback: Match description : ... then find 43 consecutive digits anywhere in that line
        elseif (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $plainText, $descLineMatches)) {
            $descLine = $descLineMatches[1];
            if (preg_match('/(\d{43})/', $descLine, $digitMatches)) {
                return trim($digitMatches[1]);
            }
        }
        
        return null;
    }
    
    /**
     * Parse description field into components
     */
    public function parseDescriptionField(?string $descriptionField): array
    {
        $result = [
            'account_number' => null,
            'payer_account_number' => null,
            'amount' => null,
            'extracted_date' => null,
        ];
        
        if (!$descriptionField) {
            return $result;
        }
        
        $length = strlen($descriptionField);
        
        // Handle 43-digit format
        if ($length === 43) {
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                // SKIP amount extraction - not reliable, use amount field instead
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Handle 42-digit format (pad with 0)
        elseif ($length === 42) {
            $padded = $descriptionField . '0';
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $padded, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
                // SKIP amount extraction - not reliable
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $result['extracted_date'] = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Handle 30-41 digit format
        elseif ($length >= 30 && $length <= 41) {
            if (preg_match('/^(\d{10})(\d{10})/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);
                $result['payer_account_number'] = trim($matches[2]);
            }
        }
        // For any other length (20+), try to extract first 10 digits
        elseif ($length >= 20) {
            if (preg_match('/^(\d{10})/', $descriptionField, $matches)) {
                $result['account_number'] = trim($matches[1]);
            }
        }
        
        return $result;
    }
}
