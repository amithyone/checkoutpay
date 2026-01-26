<?php

namespace App\Services;

class EmailExtractionService
{
    /**
     * Extract payment info from text_body (plain text)
     * @param string $text The email text body
     * @param string $subject The email subject
     * @param string $from The email sender
     * @param string|null $emailDate The email received date (for Kuda emails that don't carry time)
     */
    public function extractFromTextBody(string $text, string $subject, string $from, ?string $emailDate = null): ?array
    {
        // Decode quoted-printable encoding (common in email text_body)
        // =20 is space, =3D is equals sign, etc.
        $text = preg_replace('/=20/', ' ', $text);
        $text = preg_replace('/=3D/', '=', $text);
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Decode HTML entities
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        $textLower = strtolower($text);
        $fullText = $subject . ' ' . $textLower;
        
        $amount = null;
        $accountNumber = null;
        $senderName = null;
        $payerAccountNumber = null;
        $transactionTime = null;
        $extractedDate = null;
        
        // PRIORITY 0: Kuda Bank format - multiple variations
        // Pattern 1: "Transaction Notification NAME just sent you ₦AMOUNT"
        // Pattern 2: "You just sent ₦AMOUNT to NAME" (outgoing payment)
        // Pattern 3: "NAME just sent you ₦AMOUNT" (incoming payment)
        // For Kuda emails, use email_date as transaction_time since they don't carry time
        
        // Pattern 1: "Transaction Notification NAME just sent you ₦AMOUNT"
        if (preg_match('/Transaction\s+Notification\s+([A-Z][A-Z\s]{2,}?)\s+just\s+sent\s+you\s+₦([\d,]+\.?\d*)/i', $text, $kudaMatches)) {
            $potentialName = trim($kudaMatches[1]);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
            
            // Extract amount
            $potentialAmount = (float) str_replace(',', '', $kudaMatches[2]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
            }
        }
        // Pattern 2: "You just sent ₦AMOUNT to NAME" (outgoing payment - sender is the account owner)
        elseif (preg_match('/You\s+just\s+sent\s+₦([\d,]+\.?\d*)\s+to\s+([A-Z][A-Z\s\-]{2,}?)/i', $text, $kudaMatches)) {
            // Extract amount
            $potentialAmount = (float) str_replace(',', '', $kudaMatches[1]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
            }
            
            // Extract recipient name (not sender, but store it anyway)
            $potentialName = trim($kudaMatches[2]);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            $potentialName = rtrim($potentialName, '- .');
            if (strlen($potentialName) >= 3 && !$senderName) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: "NAME just sent you ₦AMOUNT" (incoming payment)
        elseif (preg_match('/([A-Z][A-Z\s]{2,}?)\s+just\s+sent\s+you\s+₦([\d,]+\.?\d*)/i', $text, $kudaMatches)) {
            $potentialName = trim($kudaMatches[1]);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
            
            // Extract amount
            $potentialAmount = (float) str_replace(',', '', $kudaMatches[2]);
            if ($potentialAmount >= 10) {
                $amount = $potentialAmount;
            }
        }
        
        // Use email_date as transaction_time for Kuda emails (they don't carry time in the email)
        if ($amount && $emailDate) {
            try {
                $emailDateTime = \Carbon\Carbon::parse($emailDate);
                $transactionTime = $emailDateTime->format('H:i:s');
            } catch (\Exception $e) {
                // If parsing fails, leave transactionTime as null
            }
        }
        
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
        
        // PRIORITY: Extract sender name from description field FIRST (STRUCTURED approach like amount)
        // This is the PRIMARY source - same logic as amount extraction for reliability
        // Strategy: Extract description line, then parse name from it (like we parse amount from digits 11-16)
        if (!$senderName && preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $text, $descLineMatches)) {
            $descriptionLine = trim($descLineMatches[1]);
            
            // Pattern 1a: TRANSFER FROM: NAME (with colon) - e.g., "TRANSFER FROM: JOHN = AGBO"
            if (preg_match('/TRANSFER\s+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:\s+TO|\s*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (convert "JOHN = AGBO" to "JOHN AGBO")
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1b: TRANSFER FROM NAME (without colon) - e.g., "TRANSFER FROM JIMMY = ALEX PAM-OPAY"
            elseif (preg_match('/TRANSFER\s+FROM[\s]+([A-Z][A-Z\s=]{2,}?)(?:-OPAY|[\s\-]+OPAY|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1c: KMB pattern - e.g., "digits-TXN-digits-GANYJIBM= Q-KMB-OGUNTUASE, SHOLA" or "-BIG-KMB-OSULA, GODSTIME"
            // Also handles "-BIG-KMB-NAME" format
            elseif (preg_match('/[\-](?:BIG[\-])?KMB[\-]([A-Z][A-Z\s,]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Remove trailing period and clean up
                $potentialName = rtrim($potentialName, '. ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1d: UNION TRANSFER = FROM NAME - e.g., "UNION TRANSFER = FROM UTEBOR PAUL C"
            elseif (preg_match('/UNION\s+TRANSFER\s*=\s*FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1d: digits FROM = name (with equals sign) - e.g., "digits FROM = SOLOMON INNOCENT AMITHY"
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]*=[\s]*([A-Z][A-Z\s]{2,}?)(?:[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1e: digits FROM name (with optional dash, TO, or end) - original pattern
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s=]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1f: digits FROM: name (with colon) - e.g., "digits FROM: DESTINY = IWAJOMO"
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1g: digits FROM name (end of line)
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s=]{2,}?)$/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
        }
        // Pattern 2: Also try "TRANSFER FROM NAME" format in description (without description label)
        if (!$senderName && preg_match('/description[\s]*:[\s]*[^\n\r]*?[\d\-]+\s*-\s*TRANSFER\s+FROM[\s]*:?\s*([A-Z\s=]+?)(?:-|TO|\s*$)/i', $text, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        
        // Now parse the digits if we found them (ONLY for account numbers and date, NOT amount)
        // Amount should ONLY come from the "Amount" line in text_body, not from description field
        // Try 43-digit format first (most common)
        if ($descriptionField && strlen($descriptionField) === 43) {
            // Parse the 43 digits: recipient(10) + payer(10) + amount(6) + date(8) + unknown(9)
            // SKIP amount extraction - amount should come from "Amount" line only
            if (preg_match('/^(\d{10})(\d{10})(\d{6})(\d{8})(\d{9})$/', $descriptionField, $digitMatches)) {
                $accountNumber = trim($digitMatches[1]); // PRIMARY: recipient account (first 10 digits)
                $payerAccountNumber = trim($digitMatches[2]); // Sender account (next 10 digits)
                // SKIP amount extraction from description field - amount should come from "Amount" line
                
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
                // SKIP amount extraction from description field - amount should come from "Amount" line
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
        // SKIP amount extraction - amount should ONLY come from "Amount" line in text_body
        if (!$accountNumber && preg_match('/description[\s]*:[\s]*(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s+TO|$)/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            // SKIP amount extraction from description field - amount should come from "Amount" line only
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $potentialName = trim($matches[6]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3: Direct format without "Description :" prefix (fallback - very flexible)
        // Format: "9008771210 021008599511000020260111080847554 FROM SOLOMON..." (with space)
        // OR: "900877121002100859959000020260111094651392 FROM SOLOMON..." (without space)
        // CRITICAL: This pattern allows ANY characters (including spaces, dashes, etc.) between digits and FROM
        // CRITICAL: Make TO optional - text might be truncated or not have TO
        // Use 'if' instead of 'elseif' so it runs even if previous patterns didn't find a match
        // SKIP amount extraction - amount should ONLY come from "Amount" line in text_body
        if (!$accountNumber && preg_match('/(\d{10})[\s]*(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s+TO|$)/i', $text, $matches)) {
            $accountNumber = trim($matches[1]); // PRIMARY source: recipient account
            $payerAccountNumber = trim($matches[2]);
            // SKIP amount extraction from description field - amount should come from "Amount" line only
            $dateStr = $matches[4];
            if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
            }
            if (!$senderName) {
                $senderName = trim(strtolower($matches[6]));
            }
        }
        
        // Extract amount from text (case insensitive, flexible patterns) - ONLY from "Amount" line
        // Priority: Amount after NGN on the "Amount" line (GTBank format: "Amount : NGN 1000")
        // Amount should ONLY come from text_body, NOT from description field
        // Handle HTML entities like =20 (space in quoted-printable encoding)
        // Handle Unicode spaces (non-breaking space, etc.)
        $amountPatterns = [
            // Pattern 1: "Amount : NGN 1000" - GTBank format with space after colon and NGN
            // Also handles HTML entities: "Amount : NGN=201000" (=20 is space)
            // \s matches all whitespace including non-breaking spaces
            '/amount[\s]*:[\s=]+(?:ngn|naira|₦|NGN)[\s=]+([\d,]+\.?\d*)/iu',
            // Pattern 2: "Amount: NGN 1,000.00" - standard format
            '/amount[\s:]+(?:ngn|naira|₦|NGN)[\s=]+([\d,]+\.?\d*)/iu',
            // Pattern 3: Tab separated "Amount\t:\tNGN 1000"
            '/amount[\s\t:]+(?:ngn|naira|₦|NGN)[\s\t=]+([\d,]+\.?\d*)/iu',
            // Pattern 4: Other amount labels with NGN
            '/(?:sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦|NGN)[\s=]+([\d,]+\.?\d*)/iu',
        ];
        
        foreach ($amountPatterns as $pattern) {
            if (preg_match($pattern, $fullText, $matches)) {
                $potentialAmount = (float) str_replace(',', '', $matches[1]);
                // Accept any positive amount (removed >= 10 check - bank charges can be small)
                if ($potentialAmount > 0) {
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
        // Pattern 1b: "TRANSFER FROM NAME" format in Description (with or without colon)
        if (!$senderName && preg_match('/description[\s]*:[\s]*.*?[\d\-]+\s*-\s*TRANSFER\s+FROM[\s]*:?\s*([A-Z][A-Z\s=]+?)(?:-OPAY|[\s\-]+OPAY|[\s]+TO|-|$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 1c: UNION TRANSFER = FROM NAME
        elseif (!$senderName && preg_match('/UNION\s+TRANSFER\s*=\s*FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $text, $matches)) {
            $potentialName = trim($matches[1]);
            $potentialName = rtrim($potentialName, '- ');
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
        // Pattern 3: Extract from Remarks field - UBA format (PRIORITY - check first)
        // Format: "Remarks : 4-UBA-SOLO MON FEMI GARBA" - name is after "-UBA-"
        if (!$senderName && preg_match('/(?:remark|remarks)[\s:]+[^\n\r]*?[\-]UBA[\-]([A-Z][A-Z\s]+?)(?:\s*$|\s+Time|\s+Transaction|\s+Amount|\s+Value)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3a: Extract from Remarks field - handle dash-separated format
        // Format: "Remarks : D-FAIRMONE Y-JOHN AGBO" or "Remarks : NAME"
        elseif (!$senderName && preg_match('/(?:remark|remarks)[\s:]+[^\n\r]*?[\-]([A-Z][A-Z\s]{2,}?)(?:\s|$|Time|Transaction)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes like "NT", "MR", "MRS", "MS", etc.
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|D-FAIRMONE\s+Y)\s*/i', '', $potentialName);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Pattern 3b: Remarks field without dash (original pattern)
        elseif (!$senderName && preg_match('/(?:remark|remarks)[\s:]+([A-Z][A-Z\s]{2,}?)(?:\s|$)/i', $fullText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes like "NT", "MR", "MRS", "MS", etc.
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA)\s+/i', '', $potentialName);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
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
            // Handle = characters in names (convert "JOHN = AGBO" to "JOHN AGBO")
            $senderName = preg_replace('/\s*=\s*/', ' ', $senderName);
            $senderName = preg_replace('/\s+/', ' ', $senderName);
            $senderName = trim($senderName);
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
        
        // PRIORITY: Extract sender name from description field FIRST (STRUCTURED approach like amount)
        // This is the PRIMARY source - same logic as amount extraction for reliability
        // Strategy: Extract description line, then parse name from it (like we parse amount from digits 11-16)
        if (!$senderName && preg_match('/description[\s]*:[\s]*([^\n\r]+)/i', $plainText, $descLineMatches)) {
            $descriptionLine = trim($descLineMatches[1]);
            
            // Pattern 1a: TRANSFER FROM: NAME (with colon) - e.g., "TRANSFER FROM: JOHN = AGBO"
            if (preg_match('/TRANSFER\s+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:\s+TO|\s*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1b: TRANSFER FROM NAME (without colon, before OPAY) - e.g., "TRANSFER FROM JIMMY = ALEX PAM-OPAY"
            elseif (preg_match('/TRANSFER\s+FROM[\s]+([A-Z][A-Z\s=]{2,}?)(?:-OPAY|[\s\-]+OPAY|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1c: KMB pattern - e.g., "digits-TXN-digits-GANYJIBM= Q-KMB-OGUNTUASE, SHOLA" or "-BIG-KMB-OSULA, GODSTIME"
            // Also handles "-BIG-KMB-NAME" format
            elseif (preg_match('/[\-](?:BIG[\-])?KMB[\-]([A-Z][A-Z\s,]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Remove trailing period and clean up
                $potentialName = rtrim($potentialName, '. ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1d: UNION TRANSFER = FROM NAME - e.g., "UNION TRANSFER = FROM UTEBOR PAUL C"
            elseif (preg_match('/UNION\s+TRANSFER\s*=\s*FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1e: digits FROM = name (with equals sign) - e.g., "digits FROM = SOLOMON INNOCENT AMITHY"
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]*=[\s]*([A-Z][A-Z\s]{2,}?)(?:[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1e: digits FROM name (with optional dash, TO, or end) - original pattern
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s=]{2,}?)(?:[\s\-]+|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1f: digits FROM: name (with colon) - e.g., "digits FROM: DESTINY = IWAJOMO"
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
            // Pattern 1g: digits FROM name (end of line)
            elseif (preg_match('/\d{20,}[\s]+FROM[\s]+([A-Z][A-Z\s=]{2,}?)$/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if (strlen($potentialName) >= 3) {
                    $senderName = trim(strtolower($potentialName));
                }
            }
        }
        // Pattern 2: Also try "TRANSFER FROM NAME" format in description (without description label)
        if (!$senderName && preg_match('/description[\s]*:[\s]*[^\n\r]*?[\d\-]+\s*-\s*TRANSFER\s+FROM[\s]*:?\s*([A-Z\s=]+?)(?:-OPAY|[\s\-]+OPAY|[\s]+TO|-|$)/i', $plainText, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
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
                    // SKIP amount extraction from description field - amount should come from "Amount" line only
                    $dateStr = $descMatches[4];
                    if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                        $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                    }
                    if (!$senderName) {
                        $potentialName = trim($descMatches[6]);
                        // Handle = characters in names
                        $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        $senderName = trim(strtolower($potentialName));
                    }
                }
            }
            // Pattern 2: Description field without colon cell
            elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>.*?<td[^>]*>.*?(\d{10})(\d{10})(\d{6})(\d{8})(\d{9}).*?FROM.*?([A-Z\s]+?)(?:\s*TO|$)/i', $html, $matches)) {
                $accountNumber = trim($matches[1]);
                $payerAccountNumber = trim($matches[2]);
                // SKIP amount extraction from description field - amount should come from "Amount" line only
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                $potentialName = trim($matches[6]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $senderName = trim(strtolower($potentialName));
            }
            // Pattern 3: Description field with space between accounts
            elseif (preg_match('/(?s)<td[^>]*>[\s]*(?:description|remarks|details|narration)[\s:]*<\/td>\s*<td[^>]*>[\s:]*<\/td>\s*<td[^>]*>[\s]*(\d{10})[\s]+(\d{10})(\d{6})(\d{8})(\d{9})\s+FROM\s+([A-Z\s]+?)(?:\s+TO|$)/i', $html, $matches)) {
                $accountNumber = trim($matches[1]);
                $payerAccountNumber = trim($matches[2]);
                // SKIP amount extraction from description field - amount should come from "Amount" line only
                $dateStr = $matches[4];
                if (preg_match('/^(\d{4})(\d{2})(\d{2})$/', $dateStr, $dateMatches)) {
                    $extractedDate = $dateMatches[1] . '-' . $dateMatches[2] . '-' . $dateMatches[3];
                }
                $potentialName = trim($matches[6]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $senderName = trim(strtolower($potentialName));
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
        
        // Additional pattern: Extract from Remarks field in HTML - UBA format (PRIORITY - check first)
        // Format: "Remarks : 4-UBA-SOLO MON FEMI GARBA" - name is after "-UBA-"
        if (!$senderName && preg_match('/(?:remark|remarks)[\s:]+[^\n\r<]*?[\-]UBA[\-]([A-Z][A-Z\s]+?)(?:\s*$|\s+Time|\s+Transaction|\s+Amount|\s+Value|<\/td>)/i', $plainText, $matches)) {
            $potentialName = trim($matches[1]);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
        }
        // Additional pattern: Extract from Remarks field in HTML (if not found yet)
        // Format: "Remarks : D-FAIRMONE Y-JOHN AGBO" or "Remarks : NAME"
        elseif (!$senderName && preg_match('/(?:remark|remarks)[\s:]+[^\n\r<]*?[\-]([A-Z][A-Z\s]{2,}?)(?:\s|$|Time|Transaction|<\/td>)/i', $plainText, $matches)) {
            $potentialName = trim($matches[1]);
            // Remove common prefixes and service names
            $potentialName = preg_replace('/^(NT|MR|MRS|MS|DR|PROF|ENG|CHIEF|ALHAJI|ALHAJA|D-FAIRMONE\s+Y)\s*/i', '', $potentialName);
            // Handle = characters in names
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if (strlen($potentialName) >= 3) {
                $senderName = trim(strtolower($potentialName));
            }
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
        
        // Use PHP's built-in function first (handles UTF-8 properly)
        $decoded = quoted_printable_decode($text);
        
        // Also handle any remaining =XX patterns manually (for edge cases)
        $decoded = preg_replace_callback('/=([0-9A-F]{2})/i', function ($matches) {
            $char = chr(hexdec($matches[1]));
            // Only return if it's a valid single-byte character or part of valid UTF-8 sequence
            return $char;
        }, $decoded);
        
        // Handle soft line breaks
        $decoded = preg_replace('/=\r?\n/', '', $decoded);
        $decoded = preg_replace('/=\s*\n/', "\n", $decoded);
        
        // Sanitize UTF-8 after decoding
        $decoded = $this->sanitizeUtf8($decoded);
        
        return $decoded;
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
        
        // Sanitize UTF-8 to prevent malformed characters
        $textBody = $this->sanitizeUtf8($textBody);
        
        return $textBody;
    }
    
    /**
     * Sanitize UTF-8 string to remove malformed characters
     * 
     * @param string $string
     * @return string
     */
    protected function sanitizeUtf8(string $string): string
    {
        if (empty($string)) {
            return $string;
        }
        
        // First, try to fix encoding using mb_convert_encoding
        if (!mb_check_encoding($string, 'UTF-8')) {
            // Try to convert from various encodings
            $encodings = ['ISO-8859-1', 'Windows-1252', 'UTF-8'];
            foreach ($encodings as $encoding) {
                $converted = @mb_convert_encoding($string, 'UTF-8', $encoding);
                if (mb_check_encoding($converted, 'UTF-8')) {
                    $string = $converted;
                    break;
                }
            }
        }
        
        // Use iconv to remove invalid UTF-8 sequences
        $sanitized = @iconv('UTF-8', 'UTF-8//IGNORE', $string);
        
        // If iconv failed, use mb_convert_encoding with IGNORE flag
        if ($sanitized === false || !mb_check_encoding($sanitized, 'UTF-8')) {
            $sanitized = mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        }
        
        // Remove control characters except newlines, carriage returns, and tabs
        $sanitized = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/u', '', $sanitized);
        
        // Final check: ensure valid UTF-8
        if (!mb_check_encoding($sanitized, 'UTF-8')) {
            // Last resort: remove any remaining invalid bytes
            $sanitized = mb_convert_encoding($sanitized, 'UTF-8', 'UTF-8');
        }
        
        return $sanitized ?: '';
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
