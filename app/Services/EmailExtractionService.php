<?php

namespace App\Services;

class EmailExtractionService
{
    /**
     * Extract payment info from text_body (plain text)
     */
    public function extractFromTextBody(string $text, string $subject, string $from): ?array
    {
        $textLower = strtolower($text);
        $fullText = $subject . ' ' . $textLower;
        
        $amount = null;
        $accountNumber = null;
        $senderName = null;
        $payerAccountNumber = null;
        $transactionTime = null;
        $extractedDate = null;
        
        // PRIORITY 1: Extract description field FIRST - This is the MOST RELIABLE source
        // FLEXIBLE PATTERN: Match "Description : " followed by digits (20+ digits, not just 43)
        // Text format: "Description : 900877121002100859959000020260111094651392 FROM SOLOMON"
        // OR: "Description : 100004260111113119149684166825-TRANSFER FROM INNOCENT AMITHY SOLOMON"
        // This is CLEANER than HTML - we should prioritize this!
        // CRITICAL: Pattern is flexible - accepts 20+ consecutive digits (not just 43)
        $descriptionField = null;
        
        // Try flexible pattern: description : (20+ digits) followed by space, FROM, dash, or end
        // This handles both 43-digit format and other formats like CODE-TRANSFER FROM
        if (preg_match('/description[\s]*:[\s]*(\d{20,})(?:\s|FROM|-|$)/i', $text, $descMatches)) {
            $descriptionField = trim($descMatches[1]);
        } 
        // Fallback: Match description : ... then find longest digit sequence (20+) in that line
        elseif (preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $text, $descLineMatches)) {
            $descLine = $descLineMatches[1];
            // Find longest digit sequence (at least 20 digits) in the description line
            if (preg_match('/(\d{20,})/', $descLine, $digitMatches)) {
                $descriptionField = trim($digitMatches[1]);
            }
        }
        
        // PRIORITY: Extract sender name from description field FIRST (regardless of digit count)
        // This is the PRIMARY source for sender name - must come from description field
        // Format: "Description : 900877121002100859959000020260111094651392 FROM SOLOMON"
        // OR: "Description : 100004260111113119149684166825-TRANSFER FROM INNOCENT AMITHY SOLOMON"
        if (!$senderName && preg_match('/description[\s]*:[\s]*[^\n\r]*?FROM\s+([A-Z\s]+?)(?:\s+TO|$)/i', $text, $nameMatches)) {
            $senderName = trim(strtolower($nameMatches[1]));
        }
        // Also try "TRANSFER FROM NAME" format in description
        if (!$senderName && preg_match('/description[\s]*:[\s]*[^\n\r]*?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z\s]+?)(?:-|$)/i', $text, $nameMatches)) {
            $senderName = trim(strtolower($nameMatches[1]));
        }
        
        // Now parse the digits if we found them
        // Try 43-digit format first (most common)
        if ($descriptionField && strlen($descriptionField) === 43) {
            // Parse the 43 digits: recipient(10) + payer(10) + amount(6) + date(8) + unknown(9)
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $descriptionField, $digitMatches)) {
                $accountNumber = trim($digitMatches[1]); // PRIMARY: recipient account (first 10 digits)
                $payerAccountNumber = trim($digitMatches[2]); // Sender account (next 10 digits)
                $amountFromDesc = (float) ($digitMatches[3] / 100); // Amount (6 digits, divide by 100)
                
                if ($amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                
                $dateStr = $digitMatches[4]; // Date YYYYMMDD (8 digits)
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Try 42-digit format (pad with 0)
        elseif ($descriptionField && strlen($descriptionField) === 42) {
            $padded = $descriptionField . '0';
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $padded, $digitMatches)) {
                $accountNumber = trim($digitMatches[1]);
                $payerAccountNumber = trim($digitMatches[2]);
                $amountFromDesc = (float) ($digitMatches[3] / 100);
                if ($amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                }
                $dateStr = $digitMatches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
            }
        }
        // Try 30-41 digit format (extract first 20 digits as account numbers)
        elseif ($descriptionField && strlen($descriptionField) >= 30 && strlen($descriptionField) <= 41) {
            // Extract first 10 digits as recipient account, next 10 as payer account
            if (preg_match('/^(\d{10})(\d{10})/', $descriptionField, $digitMatches)) {
                $accountNumber = trim($digitMatches[1]);
                $payerAccountNumber = trim($digitMatches[2]);
            }
        }
        // For any other length (20+), try to extract first 10 digits as account number
        elseif ($descriptionField && strlen($descriptionField) >= 20) {
            if (preg_match('/^(\d{10})/', $descriptionField, $digitMatches)) {
                $accountNumber = trim($digitMatches[1]);
            }
        }
        // Pattern 2: Without space between accounts (all 43 digits together) - also flexible
        // Format: "Description : 900877121002100859959000020260111094651392 FROM..."
        // CRITICAL: Make TO optional - text might be truncated or not have TO
        // Use 'if' instead of 'elseif' so it runs even if description field extraction didn't find a match
        if (!$accountNumber && preg_match('/description[\s]*:[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s+TO|$)/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if ($amountFromDesc >= 10) {
                $amount = $amountFromDesc;
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($matches[6]));
            }
        }
        // Pattern 3: Direct format without "Description :" prefix (fallback - very flexible)
        // Format: "9008771210 021008599511000020260111080847554 FROM SOLOMON..." (with space)
        // OR: "900877121002100859959000020260111094651392 FROM SOLOMON..." (without space)
        // CRITICAL: This pattern allows ANY characters (including spaces, dashes, etc.) between digits and FROM
        // CRITICAL: Make TO optional - text might be truncated or not have TO
        // Use 'if' instead of 'elseif' so it runs even if previous patterns didn't find a match
        if (!$accountNumber && preg_match('/(\d{10})[\s]*(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s+TO|$)/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            $amountFromDesc = (float) ($matches[3] / 100);
            if ($amountFromDesc >= 10) {
                $amount = $amountFromDesc;
            }
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($matches[6]));
            }
        }
        
        // Extract amount from text (case insensitive, flexible patterns) - only if not already extracted
        $amountPatterns = [
            '/(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦|NGN)[\s]*([\d,]+\.?\d*)/i',
            '/(?:ngn|naira|₦|NGN)[\s]*([\d,]+\.?\d*)/i',
            '/([\d,]+\.?\d*)[\s]*(?:naira|ngn|usd|dollar|NGN)/i',
            // Pattern for format: "Amount\t:\tNGN 1000" (tab separated)
            '/amount[\s\t:]+(?:ngn|naira|₦|NGN)[\s\t]*([\d,]+\.?\d*)/i',
            // Pattern for format: "Amount: NGN  1000" (multiple spaces)
            '/amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)/i',
        ];
        
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    break;
                }
            }
        }
        
        // Extract sender name from text (if not already extracted from description field)
        // Only use fallback patterns if we didn't get the name from description field
        if (!$senderName) {
            $senderName = $this->extractSenderNameFromText($text, $fullText);
        }
        
        // Extract transaction time from text
        if (preg_match('/(?:time|transaction\s*time)[\s]*:[\s]*(\d{1,2}):(\d{2}):(\d{2})\s*(AM|PM)/i', $text, $timeMatches)) {
            $hour = (int) $timeMatches[1];
            $minute = (int) $timeMatches[2];
            $second = (int) $timeMatches[3];
            $ampm = strtoupper($timeMatches[4]);
            
            if ($ampm === 'PM' && $hour < 12) {
                $hour += 12;
            } elseif ($ampm === 'AM' && $hour === 12) {
                $hour = 0;
            }
            
            $transactionTime = sprintf('%02d:%02d:%02d', $hour, $minute, $second);
        }
        
        // Try extracting from email sender if no name found
        if (!$senderName && $from) {
            if (preg_match('/([^<]+)/', $from, $matches)) {
                $senderName = trim(strtolower($matches[1]));
            }
        }
        
        // Validate sender name before returning (filter out email addresses)
        if ($senderName) {
            $senderName = $this->validateSenderName($senderName);
        }
        
        // ALWAYS return results if we found ANY data (amount, account number, sender name, or description field)
        // This ensures we extract as much as possible from text_body even if description field extraction failed
        // Description field extraction is valuable even if amount extraction from other fields failed
        if ($amount || $accountNumber || $senderName || $descriptionField) {
            $result = [
                'amount' => $amount,
                'sender_name' => $senderName, // Already validated
                'account_number' => $accountNumber, // CRITICAL: Recipient account number (where payment was sent TO)
                'payer_account_number' => $payerAccountNumber,
                'transaction_time' => $transactionTime,
                'extracted_date' => $extractedDate,
                'method' => 'text_body',
            ];
            
            // Add description field to result for debugging
            if ($descriptionField) {
                $result['description_field'] = $descriptionField;
            }
            
            return $result;
        }
        
        return null;
    }
    
    /**
     * Extract sender name from text using multiple patterns
     */
    protected function extractSenderNameFromText(string $text, string $fullText): ?string
    {
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
        // Pattern 2b: Direct format in text "CODE-NAME TRF FOR" (after decode)
        elseif (preg_match('/[\d\-]+\s*-\s*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: Extract from Remarks field (just the name, no FROM)
        elseif (preg_match('/(?:remark|remarks)[\s:]+([A-Z][A-Z\s]{2,}?)(?:\s|$)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes like "NT", "MR", "MRS", "MS", etc.
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA)\s+/i', '', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 4: Other standard patterns in text
        elseif (preg_match('/(?:from|sender|payer|depositor|account\s*name|name)[\s:]+([A-Z][A-Z\s]+?)(?:\s+to|\s+account|\s+:|$)/i', $fullText, $matches)) {
            $senderName = trim(strtolower($matches[1]));
        }
        
        // Clean up sender name
        if ($senderName) {
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            if (strlen($senderName) < 3) {
                $senderName = null;
            }
        }
        
        return $senderName;
    }
    
    /**
     * Extract payment info from html_body
     */
    public function extractFromHtmlBody(string $html, string $subject, string $from, DescriptionFieldExtractor $descExtractor, SenderNameExtractor $nameExtractor): ?array
    {
        // Decode quoted-printable and HTML entities
        $html = $this->decodeQuotedPrintable($html);
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $amount = null;
        $accountNumber = null;
        $senderName = null;
        $payerAccountNumber = null;
        $transactionTime = null;
        $extractedDate = null;
        $method = null;
        $descriptionField = null;
        
        // PRIORITY 1: Convert HTML to plain text FIRST - this is the most reliable method
        $plainText = strip_tags($html);
        $plainText = preg_replace('/\s+/', ' ', $plainText);
        
        // Extract description field from plain text
        $descriptionField = $descExtractor->extractFromHtml($html);
        
        // PRIORITY: Extract sender name from description field FIRST (regardless of digit count)
        // This is the PRIMARY source for sender name - must come from description field
        // Format: "Description : 900877121002100859959000020260111094651392 FROM SOLOMON"
        // OR: "Description : 100004260111113119149684166825-TRANSFER FROM INNOCENT AMITHY SOLOMON"
        if (!$senderName && preg_match('/description[\s]*:[\s]*[^\n\r]*?FROM\s+([A-Z\s]+?)(?:\s+TO|$)/i', $plainText, $nameMatches)) {
            $senderName = trim(strtolower($nameMatches[1]));
        }
        // Also try "TRANSFER FROM NAME" format in description
        if (!$senderName && preg_match('/description[\s]*:[\s]*[^\n\r]*?[\d\-]+\s*-\s*TRANSFER\s+FROM\s+([A-Z\s]+?)(?:-|$)/i', $plainText, $nameMatches)) {
            $senderName = trim(strtolower($nameMatches[1]));
        }
        
        // Parse description field if found
        if ($descriptionField) {
            $parsed = $descExtractor->parseDescriptionField($descriptionField);
            if ($parsed['account_number']) {
                $accountNumber = $parsed['account_number'];
                $payerAccountNumber = $parsed['payer_account_number'];
                if ($parsed['extracted_date']) {
                    $extractedDate = $parsed['extracted_date'];
                }
                // Extract amount from description (if 43 digits)
                if (strlen($descriptionField) === 43) {
                    if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $descriptionField, $digitMatches)) {
                        $amountFromDesc = (float) ($digitMatches[3] / 100);
                        if ($amountFromDesc >= 10) {
                            $amount = $amountFromDesc;
                            $method = 'html_description_43digits';
                        }
                    }
                }
            }
        }
        
        // Extract from HTML table patterns (if description field extraction didn't work)
        if (!$accountNumber) {
            // Pattern 1: Description field with colon cell
            if (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>[\s:]*<\/td>.*?<td[^>]*>([^<]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})[^<]*FROM[^<]*)/i', $html, $matches)) {
                $descriptionField = trim(strip_tags($matches[1]));
                if (preg_match('/(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $descriptionField, $descMatches)) {
                    $accountNumber = trim($descMatches[1]);
                    $payerAccountNumber = trim($descMatches[2]);
                    $amountFromDesc = (float) ($descMatches[3] / 100);
                    if (!$amount && $amountFromDesc >= 10) {
                        $amount = $amountFromDesc;
                        $method = 'html_table_description';
                    }
                    $dateStr = $descMatches[4];
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                        $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                    }
                    if (!$senderName) {
                        $senderName = trim(strtolower($descMatches[6]));
                    }
                }
            }
            // Pattern 2: Description field without colon cell
            elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>.*?(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $html, $matches)) {
                $accountNumber = trim($matches[1]);
                $payerAccountNumber = trim($matches[2]);
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                    $method = 'html_table_description';
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                $senderName = trim(strtolower($matches[6]));
            }
            // Pattern 3: Description field with space between accounts
            elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})[\s]+(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)(?:\s+TO|$)/i', $html, $matches)) {
                $accountNumber = trim($matches[1]);
                $payerAccountNumber = trim($matches[2]);
                $amountFromDesc = (float) ($matches[3] / 100);
                if (!$amount && $amountFromDesc >= 10) {
                    $amount = $amountFromDesc;
                    $method = 'html_table_description';
                }
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                $senderName = trim(strtolower($matches[6]));
            }
        }
        
        // Extract amount from HTML table (if not already extracted)
        if (!$amount) {
            // Pattern 1: Amount in separate cell after label and colon
            if (preg_match('/(?s)<td[^>]*>[\s]*amount[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>.*?(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 2: Amount in same cell with label
            elseif (preg_match('/<td[^>]*>[\s]*amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 3: Any table row containing "Amount" label
            elseif (preg_match('/(?s)<tr[^>]*>.*?<td[^>]*>[\s]*amount[\s:]*<\/td>.*?<td[^>]*>.*?(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)[\s]*<\/td>/i', $html, $matches)) {
                $amount = (float) str_replace(',', '', $matches[1]);
                $method = 'html_table';
            }
            // Pattern 4: From plain text
            elseif (preg_match('/amount[\s:]+(?:ngn|naira|₦|NGN)[\s]+([\d,]+\.?\d*)/i', $plainText, $textMatches)) {
                $potentialAmount = (float) str_replace(',', '', $textMatches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    $method = 'html_rendered_text';
                }
            }
            // Pattern 5: Standalone NGN in HTML
            elseif (preg_match('/(?:ngn|naira|₦|NGN)\s*([\d,]+\.?\d*)/i', $html, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                if ($potentialAmount >= 10) {
                    $amount = $potentialAmount;
                    $method = 'html_text';
                }
            }
        }
        
        // Extract sender name using SenderNameExtractor (only if not already extracted from description field)
        // Only use fallback patterns if we didn't get the name from description field
        if (!$senderName) {
            $senderName = $nameExtractor->extractFromHtml($html);
        }
        
        // Try extracting from email sender if no name found (but validate it's not an email)
        if (!$senderName && $from) {
            if (preg_match('/([^<]+)/', $from, $matches)) {
                $potentialName = trim(strtolower($matches[1]));
                // Validate it's not an email address
                $senderName = $this->validateSenderName($potentialName);
            }
        }
        
        // Validate sender name before returning (filter out email addresses)
        if ($senderName) {
            $senderName = $this->validateSenderName($senderName);
        }
        
        if (!$amount) {
            return null;
        }
        
        $result = [
            'amount' => $amount,
            'account_number' => $accountNumber,
            'sender_name' => $senderName, // Already validated
            'payer_account_number' => $payerAccountNumber,
            'transaction_time' => $transactionTime,
            'extracted_date' => $extractedDate,
            'method' => $method ?? 'html_body',
        ];
        
        // Add description field if extracted
        if ($descriptionField) {
            $result['description_field'] = $descriptionField;
        }
        
        return $result;
    }
    
    /**
     * Convert HTML to plain text
     */
    public function htmlToText(string $html): string
    {
        // Remove script and style tags
        $html = preg_replace('/<script[^>]*>.*?<\/script>/is', '', $html);
        $html = preg_replace('/<style[^>]*>.*?<\/style>/is', '', $html);
        
        // Convert HTML entities
        $html = html_entity_decode($html, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove HTML tags
        $text = strip_tags($html);
        
        // Normalize whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        $text = trim($text);
        
        return $text;
    }
    
    /**
     * Decode quoted-printable encoding
     */
    public function decodeQuotedPrintable(string $text): string
    {
        if (empty($text)) {
            return '';
        }
        
        // Decode quoted-printable format: =XX where XX is hex
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Handle soft line breaks
        $text = preg_replace('/=\r?\n/', '', $text);
        $text = preg_replace('/=\s*\n/', "\n", $text);
        
        return $text;
    }
    
    /**
     * Normalize text_body: ensure it's always stripped from HTML and never empty
     * If text_body is empty, extract from html_body
     * 
     * @param string|null $textBody
     * @param string|null $htmlBody
     * @return string
     */
    public function normalizeTextBody(?string $textBody, ?string $htmlBody = null): string
    {
        // If text_body is empty but html_body exists, extract text from HTML
        if (empty(trim($textBody ?? '')) && !empty(trim($htmlBody ?? ''))) {
            $textBody = $this->htmlToText($htmlBody);
        }
        
        // If still empty, return empty string (but at least we tried)
        if (empty(trim($textBody ?? ''))) {
            return '';
        }
        
        // Ensure text_body is clean (no HTML tags, normalized whitespace)
        // Decode any HTML entities first
        $textBody = html_entity_decode($textBody, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Remove any remaining HTML tags (in case text_body had some HTML)
        $textBody = strip_tags($textBody);
        
        // Normalize whitespace
        $textBody = preg_replace('/\s+/', ' ', $textBody);
        $textBody = trim($textBody);
        
        return $textBody;
    }
    
    /**
     * Validate and filter sender name - cannot be an email address
     * 
     * @param string|null $senderName
     * @return string|null
     */
    public function validateSenderName(?string $senderName): ?string
    {
        if (empty($senderName)) {
            return null;
        }
        
        $senderName = trim($senderName);
        
        // FILTER OUT EMAIL ADDRESSES - sender name cannot be an email
        if (preg_match('/@/', $senderName) || filter_var($senderName, FILTER_VALIDATE_EMAIL)) {
            return null;
        }
        
        // Filter out common email patterns
        if (preg_match('/^[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}$/i', $senderName)) {
            return null;
        }
        
        // Filter out email-like patterns (e.g., "gens@gtbank.com")
        if (preg_match('/@[a-z0-9.-]+/i', $senderName)) {
            return null;
        }
        
        // Must be at least 3 characters
        if (strlen($senderName) < 3) {
            return null;
        }
        
        return $senderName;
    }
}
