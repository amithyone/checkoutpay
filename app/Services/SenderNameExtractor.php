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
            // Pattern 1a1: TRANSFER FROM: NAME = NAME TO SQUAD - e.g., "TRANSFER FROM: JOHN = AGBO TO SQUAD" (MORE SPECIFIC - check first)
            if (preg_match('/TRANSFER\s+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)[\s]+TO/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1a: TRANSFER FROM: NAME (with colon) - e.g., "TRANSFER FROM: JOHN = AGBO"
            elseif (preg_match('/TRANSFER\s+FROM[\s]*:[\s]*([A-Z][A-Z\s=]{2,}?)(?:\s+TO|\s*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
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
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c: KMB pattern - e.g., "digits-TXN-digits-GANYJIBM= Q-KMB-OGUNTUASE, SHOLA" or "PAYMENT -KMB-OGWU, OGELUE DIVINE"
            // Also handles "-BIG-KMB-OSULA, GODSTIME" format
            // Handle both "V-KMB-NAME" and " -KMB-NAME" and "-BIG-KMB-NAME" patterns
            // Allow equals signs in name (quoted-printable encoding)
            // Use greedy match to capture full name including comma-separated names
            elseif (preg_match('/[\s\-](?:BIG[\-])?KMB[\-]([A-Z][A-Z\s,=]{2,})(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                // Remove trailing period and clean up
                $potentialName = rtrim($potentialName, '. ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c1a: OPAY pattern - "TRANSFER FROM NAME-OPAY-" (extract name before OPAY)
            elseif (preg_match('/TRANSFER\s+FROM\s+([A-Z][A-Z\s,=\-]{2,}?)[\s\-]+OPAY/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c1b: OPAY pattern - e.g., "PURCHASE-OPAY-NAME", "GIFT-OPAY-NAME", "MISCELLANEOUS-OPAY-NAME", "PAY-OPAY-NAME", "LOAN-REPAYMENT-OPAY-NAME", "TELEGRAM-OPAY-NAME", "NUMBERS-OPAY-NAME"
            elseif (preg_match('/[\-](?:PURCHASE|GIFT|MISCELLANEOUS|TRANSFER|PAY|LOAN[\s]*=[\s]*REPAYMENT|TELEGRAM|NUMBERS|BABA)[\-]OPAY[\-]([A-Z][A-Z\s,=]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                // Remove trailing period and clean up
                $potentialName = rtrim($potentialName, '. ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c1c: OPAY pattern - "TRANSFER FROM NAME-OPAY-" (extract name before OPAY, handle truncation)
            elseif (preg_match('/TRANSFER\s+FROM\s+([A-Z][A-Z\s,=\-]{2,}?)[\s\-]+OPA/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c1d: OPAY pattern - "NAME-OPAY-NAME" format (extract name after OPAY)
            elseif (preg_match('/[\-]OPAY[\-]([A-Z][A-Z\s,=]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '. ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c1e: PALMPAY pattern - "DIGITS-NAME:PHONE-PALMPAY-..." format
            // Example: "100033260126211505020007915346-OLUWATOBI IFEDAYO = ALADE:7038292657-PALMPAY-OLUWAT"
            // Extract name between first dash after digits and colon before phone number
            elseif (preg_match('/\d+\-([A-Z][A-Z\s=]{2,}?):\d+\-PALMPAY/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable encoding)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c2a: PALMPAY pattern - extract name BEFORE PALMPAY (full name)
            // Format: "NAME = NAMEphone-PALMPAY-TRUNCATED" or "NAME:phone-PALMPAY-TRUNCATED"
            // Extract the full name before PALMPAY (may include equals sign)
            // Pattern: capture name parts before phone number (10+ digits) followed by -PALMPAY
            elseif (preg_match('/([A-Z][A-Z\s=]{2,}?)[\s]*[=:][\s]*([A-Z][A-Z\s]*?)[\d]{10,}[\-]PALMPAY/i', $descriptionLine, $nameMatches)) {
                // Combine both name parts
                $potentialName = trim($nameMatches[1]) . ' ' . trim($nameMatches[2]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c2a1: PALMPAY pattern - simpler format "NAMEphone-PALMPAY"
            elseif (preg_match('/([A-Z][A-Z\s=]{2,}?)[\d]{10,}[\-]PALMPAY/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable)
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c2b: PALMPAY pattern - extract name AFTER PALMPAY (fallback if no name before)
            // Format: "NAME:phone-PALMPAY-NAME" (extract name after PALMPAY)
            elseif (preg_match('/[\-]PALMPAY[\-]([A-Z][A-Z\s,=]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|[\s]*=|[\s]*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names (quoted-printable) - remove =20, =0D, etc.
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/=\d+/', '', $potentialName); // Remove =20, =0D, etc.
                // Remove trailing period and clean up
                $potentialName = rtrim($potentialName, '. ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c2c: PALMPAY/OPAY pattern - "palmpay-NAME" or "opay-NAME" (name follows after hyphen)
            elseif (preg_match('/(?:palmpay|opay)[\-]([A-Z][A-Z\s,=]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|[\s]*=|[\s]*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/=\d+/', '', $potentialName);
                $potentialName = rtrim($potentialName, '. -');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1c2d: Kuda bank patterns - "-KMB-NAME", "-OPAY-NAME", "-PALMPAY-NAME", "-BIG-KMB-NAME" (name follows after these phrases)
            elseif (preg_match('/[\-](?:BIG[\-])?(?:KMB|OPAY|PALMPAY)[\-]([A-Z][A-Z\s,=]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|[\s]*=|[\s]*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = preg_replace('/=\d+/', '', $potentialName);
                $potentialName = rtrim($potentialName, '. -');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1d: UNION TRANSFER = FROM NAME - e.g., "UNION TRANSFER = FROM UTEBOR PAUL C" or "MOBILE/UNION TRANSFER = FROM NAME"
            // Also handle "TRANSFER = FROM NAME" pattern
            // Capture name until dash with NA, TO, or end of line
            elseif (preg_match('/(?:MOBILE\/)?UNION\s+TRANSFER\s*=\s*FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s]*\-[\s]*NA|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                    $senderName = strtolower($potentialName);
                }
            }
            // Pattern 1d1: TRANSFER = FROM NAME (without UNION) - e.g., "TRANSFER = FROM UTEBOR PAUL C"
            elseif (preg_match('/TRANSFER\s*=\s*FROM[\s]+([A-Z][A-Z\s]{2,}?)(?:[\s]*\-[\s]*NA|[\s]+TO|\s*$)/i', $descriptionLine, $nameMatches)) {
                $potentialName = trim($nameMatches[1]);
                // Handle = characters in names
                $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
                $potentialName = rtrim($potentialName, '- ');
                $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
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
        
        // PRIORITY 1.5: Check Remarks field for Access Bank transactions
        // When Access Bank is detected in description, sender name is usually in Remarks
        if (!$senderName && preg_match('/[\-]ACCESS[\-]/i', $text)) {
            // Extract from Remarks field
            if (preg_match('/remark[s]*[\s]*:[\s]*([^\n\r]+)/i', $text, $remarksMatches)) {
                $remarksLine = trim($remarksMatches[1]);
                // Extract name from remarks (usually just the name before "Time" or other fields)
                if (preg_match('/^([A-Z][A-Z\s]{2,}?)(?:\s+Time|\s+Transaction|\s+Document|\s*$)/i', $remarksLine, $nameMatches)) {
                    $potentialName = trim($nameMatches[1]);
                    $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                    if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                        $senderName = strtolower($potentialName);
                    }
                }
            }
        }
        
        // PRIORITY 1.6: Extract TXN ID from description and match with pending payments
        // When description has TXN pattern, look up the transaction_id in pending payments
        // Format: Email has "TXN17685930623YUR0JMAKA", Payment has "TXN-1768593062-3yUR0JMAk"
        // Match by comparing uppercase versions with dashes removed (fuzzy match for 1 char difference)
        if (!$senderName && preg_match('/TXN[\-]?[\d]+[\-]?[A-Z0-9]+/i', $text, $txnMatches)) {
            // Extract TXN ID (convert to uppercase for matching)
            $txnIdFromDesc = strtoupper(trim($txnMatches[0]));
            $txnIdFromDescNoDash = str_replace('-', '', $txnIdFromDesc);
            
            // Try to find matching payment by transaction_id (check pending first, then all)
            // Get all payments with TXN prefix and match in memory (more flexible)
            $payment = \App\Models\Payment::where('transaction_id', 'LIKE', 'TXN%')
                ->where('status', 'pending') // Prioritize pending payments
                ->get()
                ->first(function ($payment) use ($txnIdFromDescNoDash) {
                    $paymentTxnUpper = strtoupper($payment->transaction_id);
                    $paymentTxnNoDash = str_replace('-', '', $paymentTxnUpper);
                    
                    // Exact match
                    if ($txnIdFromDescNoDash === $paymentTxnNoDash) {
                        return true;
                    }
                    
                    // Fuzzy match: if one is prefix of the other (min 15 chars) or differs by 1 char
                    $minLen = min(strlen($txnIdFromDescNoDash), strlen($paymentTxnNoDash));
                    if ($minLen >= 15) {
                        // Check if one is a prefix of the other (bank might add/remove trailing chars)
                        if (strpos($txnIdFromDescNoDash, $paymentTxnNoDash) === 0 || 
                            strpos($paymentTxnNoDash, $txnIdFromDescNoDash) === 0) {
                            return true;
                        }
                        // Check if they differ by only 1 character (similarity >= 95%)
                        $diff = abs(strlen($txnIdFromDescNoDash) - strlen($paymentTxnNoDash));
                        if ($diff <= 1 && substr($txnIdFromDescNoDash, 0, $minLen - 1) === substr($paymentTxnNoDash, 0, $minLen - 1)) {
                            return true;
                        }
                    }
                    
                    return false;
                });
            
            // If no pending payment found, check all payments (approved/rejected might have payer_name)
            if (!$payment) {
                $payment = \App\Models\Payment::where('transaction_id', 'LIKE', 'TXN%')
                    ->get()
                    ->first(function ($payment) use ($txnIdFromDescNoDash) {
                        $paymentTxnUpper = strtoupper($payment->transaction_id);
                        $paymentTxnNoDash = str_replace('-', '', $paymentTxnUpper);
                        
                        // Exact match
                        if ($txnIdFromDescNoDash === $paymentTxnNoDash) {
                            return true;
                        }
                        
                        // Fuzzy match: if one is prefix of the other (min 15 chars) or differs by 1 char
                        $minLen = min(strlen($txnIdFromDescNoDash), strlen($paymentTxnNoDash));
                        if ($minLen >= 15) {
                            // Check if one is a prefix of the other
                            if (strpos($txnIdFromDescNoDash, $paymentTxnNoDash) === 0 || 
                                strpos($paymentTxnNoDash, $txnIdFromDescNoDash) === 0) {
                                return true;
                            }
                            // Check if they differ by only 1 character
                            $diff = abs(strlen($txnIdFromDescNoDash) - strlen($paymentTxnNoDash));
                            if ($diff <= 1 && substr($txnIdFromDescNoDash, 0, $minLen - 1) === substr($paymentTxnNoDash, 0, $minLen - 1)) {
                                return true;
                            }
                        }
                        
                        return false;
                    });
            }
            
            if ($payment && !empty($payment->payer_name)) {
                $senderName = strtolower(trim($payment->payer_name));
            }
        }
        
        // PRIORITY 1.6a: Extract from Remarks field when description has TXN pattern but no payment match found
        // Some banks put sender name in Remarks when description only has transaction codes
        // Format: "Remarks : NAME" or "Remarks : D-NAME" or "Remarks : /NONE/...-FBN-NAME"
        if (!$senderName && preg_match('/TXN[\-]?[\d]+[\-]?[A-Z0-9]+/i', $text)) {
            // Check if remarks contains a name (not just transaction codes)
            if (preg_match('/remark[s]*[\s]*:[\s]*([^\n\r]+)/i', $text, $remarksMatches)) {
                $remarksLine = trim($remarksMatches[1]);
                // Skip if remarks only contains transaction codes (no actual name pattern)
                if (!preg_match('/^[\dA-Z\-\s]{10,}$/i', $remarksLine) || preg_match('/[A-Z]{2,}\s+[A-Z]/i', $remarksLine)) {
                    // Pattern 1: Direct name at start: "D-HENRY CH IBUZOR CHIKWENDU" or "NAME"
                    if (preg_match('/^(?:D[\-])?([A-Z][A-Z\s]{2,}?)(?:\s+Time|\s+Transaction|\s+Document)/i', $remarksLine, $nameMatches)) {
                        $potentialName = trim($nameMatches[1]);
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                            $senderName = strtolower($potentialName);
                        }
                    }
                    // Pattern 2: Name after codes or prefixes: "/NONE/...-FBN-NAME"
                    elseif (preg_match('/[\-]FBN[\-]([A-Z][A-Z\s]{2,}?)(?:\s+Time|\s+Transaction|\s+Document)/i', $remarksLine, $nameMatches)) {
                        $potentialName = trim($nameMatches[1]);
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                            $senderName = strtolower($potentialName);
                        }
                    }
                }
            }
        }
        
        // PRIORITY 1.7: Extract from Remarks field for other cases where name might be in remarks
        // Check remarks for names that look like actual person names (not transaction codes)
        if (!$senderName && preg_match('/remark[s]*[\s]*:[\s]*([^\n\r]+)/i', $text, $remarksMatches)) {
            $remarksLine = trim($remarksMatches[1]);
            // Skip if it looks like transaction codes (all caps, no spaces, or mostly numbers)
            if (!preg_match('/^[\dA-Z\-]{10,}$/i', $remarksLine)) {
                // Pattern 1: Name with D- prefix: "D-HENRY CH IBUZOR CHIKWENDU"
                if (preg_match('/^(?:D[\-])?([A-Z][A-Z\s]{2,}?)(?:\s+Time|\s+Transaction|\s+Document)/i', $remarksLine, $nameMatches)) {
                    $potentialName = trim($nameMatches[1]);
                    // Must have at least 2 words with capital letters
                    if (preg_match('/[A-Z]{2,}\s+[A-Z]/i', $potentialName)) {
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                            $senderName = strtolower($potentialName);
                        }
                    }
                }
                // Pattern 2: Name after FBN: "/NONE/...-FBN-NAME"
                elseif (preg_match('/[\-]FBN[\-]([A-Z][A-Z\s]{2,}?)(?:\s+Time|\s+Transaction|\s+Document)/i', $remarksLine, $nameMatches)) {
                    $potentialName = trim($nameMatches[1]);
                    if (preg_match('/[A-Z]{2,}\s+[A-Z]/i', $potentialName)) {
                        $potentialName = preg_replace('/\s+/', ' ', $potentialName);
                        if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                            $senderName = strtolower($potentialName);
                        }
                    }
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
        
        // PRIORITY FINAL: Fallback - Extract LEMONADE from description/remarks if all else fails
        // LEMONADE TECHNOLOGY LIMITED is a company name, use "lemonade" as fallback
        if (!$senderName && (stripos($text, 'LEMONADE') !== false || stripos($text, 'LEMFI') !== false)) {
            // Check if LEMONADE TECHNOLOGY LIMITED appears
            if (preg_match('/[\-]LEMONADE[\s]+TECHNOLOGY[\s]+LIMITED/i', $text)) {
                $senderName = 'lemonade';
            }
            // Or if LEMFI TRANSFER appears
            elseif (preg_match('/[\-]LEMFI[\s]+TRANSFER/i', $text)) {
                $senderName = 'lemonade';
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
            if (!$this->isValidName($senderName) || $this->isGenericTransactionName($senderName)) {
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
        
        // Pattern 4: PALMPAY/OPAY pattern - "palmpay-NAME" or "opay-NAME" (name follows after hyphen)
        if (preg_match('/(?:palmpay|opay)[\-]([A-Z][A-Z\s,=]{2,}?)(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|[\s]*=|[\s]*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/=\d+/', '', $potentialName);
            $potentialName = rtrim($potentialName, '. -');
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
                return strtolower($potentialName);
            }
        }
        
        // Pattern 5: Kuda bank patterns - "-KMB-NAME", "-OPAY-NAME", "-PALMPAY-NAME", "-BIG-KMB-NAME" (name follows after these phrases)
        // Use greedy match to capture full name including comma-separated names
        if (preg_match('/[\-](?:BIG[\-])?(?:KMB|OPAY|PALMPAY)[\-]([A-Z][A-Z\s,=]{2,})(?:[\s]*\.|[\s]+Amount|[\s]+Value|[\s]+Time|[\s]*=|[\s]*$|[\s\-])/i', $descriptionLine, $nameMatches)) {
            $potentialName = trim($nameMatches[1]);
            $potentialName = preg_replace('/\s*=\s*/', ' ', $potentialName);
            $potentialName = preg_replace('/=\d+/', '', $potentialName);
            $potentialName = rtrim($potentialName, '. -');
            $potentialName = preg_replace('/\s+/', ' ', $potentialName);
            if ($this->isValidName($potentialName) && !$this->isGenericTransactionName($potentialName)) {
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
        
        // Use PHP's built-in function first (handles UTF-8 properly)
        $text = quoted_printable_decode($text);
        
        // Also handle any remaining =XX patterns manually (for edge cases)
        $text = preg_replace_callback('/=([0-9A-F]{2})/i', function($matches) {
            return chr(hexdec($matches[1]));
        }, $text);
        
        // Handle soft line breaks (= at end of line)
        $text = preg_replace('/=\r?\n/', '', $text);
        $text = preg_replace('/=\s*$/', '', $text);
        
        // Normalize whitespace (but preserve structure)
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Sanitize UTF-8 to prevent malformed characters
        $text = $this->sanitizeUtf8($text);
        
        return trim($text);
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
     * Check if name is a generic transaction name (not a real person name)
     */
    protected function isGenericTransactionName(string $name): bool
    {
        $name = strtolower(trim($name));
        
        // List of generic transaction names to filter out
        $genericNames = [
            'payment',
            'vam transfer transaction',
            'transfer transaction',
            'transaction',
            'credit',
            'debit',
            'deposit',
            'withdrawal',
            'transfer',
            'remittance',
            'payment received',
            'payment sent',
            'bank transfer',
            'online transfer',
            'mobile transfer',
            'atm transaction',
            'pos transaction',
            'card transaction',
        ];
        
        // Check exact matches
        if (in_array($name, $genericNames)) {
            return true;
        }
        
        // Check if name contains generic transaction words
        foreach ($genericNames as $generic) {
            if (stripos($name, $generic) !== false && strlen($name) < 30) {
                return true;
            }
        }
        
        return false;
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
        
        // Reject specific invalid names
        $invalidNames = ['-', 'mobile', 'vam transfer transaction', 'vam'];
        if (in_array(strtolower($name), $invalidNames)) {
            return false;
        }
        
        // Must be at least 3 characters
        if (strlen($name) < 3) {
            return false;
        }
        
        // Filter out generic transaction names
        if ($this->isGenericTransactionName($name)) {
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
