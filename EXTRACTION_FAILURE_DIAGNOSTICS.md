# Email Extraction Failure Diagnostics

## Problem Description

When the system cannot extract payment information from an email, it logs the error: **"Could not extract payment info from email"**. This document explains what's happening and how to diagnose the issue.

## What Happens During Extraction

The extraction process follows these steps in order:

1. **Template Matching** (Highest Priority)
   - Checks if the sender email matches a configured bank email template
   - If matched, uses template-specific extraction patterns
   - **Failure**: No matching template found, or template extraction failed

2. **Text Body Extraction** (Primary)
   - Tries to extract payment info from `text_body` (plain text) first
   - Looks for amount patterns: `NGN 1000`, `Amount: NGN 1000`, etc.
   - **Failure**: No amount found in text body, or amount is invalid (< 10 Naira)

3. **HTML Body Extraction** (Fallback)
   - If text body fails, tries HTML table extraction (most accurate for Nigerian banks)
   - Looks for HTML table patterns: `<td>Amount</td><td>NGN 1000</td>`
   - **Failure**: No amount found in HTML tables

4. **HTML-to-Text Conversion** (Last Resort)
   - Converts HTML to plain text and tries text extraction again
   - **Failure**: HTML conversion produced empty text, or no amount found

## Why Extraction Fails

Common reasons extraction fails:

### 1. **Empty or Missing Email Content**
   - **Issue**: Both `text_body` and `html_body` are empty
   - **Possible Causes**:
     - Email was not parsed correctly during fetching
     - Email format is not supported (e.g., multipart encoding issues)
     - Email contains only images or attachments
   - **Diagnostic**: Check `text_length` and `html_length` in match attempt details

### 2. **Email Format Not Recognized**
   - **Issue**: Email doesn't contain expected patterns
   - **Possible Causes**:
     - Email is not from a Nigerian bank
     - Bank uses a different email format than expected
     - Email is a notification about something else (not a payment)
   - **Diagnostic**: Check if email contains keywords like "amount", "ngn", "naira", "payment", "transfer", "credit", "deposit"
   - **Solution**: Check the email content preview in match attempt details

### 3. **Amount Format Not Recognized**
   - **Issue**: Amount exists but doesn't match any extraction pattern
   - **Possible Causes**:
     - Amount format is different (e.g., "1,000.00" vs "1000")
     - Currency code is different (e.g., "USD" instead of "NGN")
     - Amount is embedded in a complex HTML structure not covered by patterns
   - **Diagnostic**: Check `text_preview` and `html_preview` in match attempt details to see actual amount format

### 4. **HTML Structure Not Table-Based**
   - **Issue**: HTML doesn't contain table structures (`<td>`, `<tr>`, `<table>` tags)
   - **Possible Causes**:
     - Email uses div-based layout instead of tables
     - Email is formatted differently than expected GTBank format
   - **Diagnostic**: Check if HTML preview contains table tags

### 5. **Amount Too Small**
   - **Issue**: Amount was found but is less than 10 Naira (filtered out as invalid)
   - **Possible Causes**:
     - Amount is actually less than 10 Naira (unlikely for real payments)
     - Amount was incorrectly parsed (e.g., "10.50" parsed as "10")
   - **Diagnostic**: Check extraction errors in match attempt details

## Enhanced Diagnostics (Now Available)

When extraction fails, the system now logs detailed diagnostic information in the match attempt record:

### Diagnostic Information Includes:

1. **Extraction Steps Logged**
   - Which steps were attempted (template, text_body, html_body, etc.)
   - Whether each step found content or was empty

2. **Error Messages**
   - Specific reason why each extraction method failed
   - Whether amount was found but invalid, or not found at all

3. **Content Analysis**
   - Text body length (chars)
   - HTML body length (chars)
   - Email subject and sender
   - Text body preview (first 500 chars)
   - HTML body preview (first 500 chars)

4. **Potential Issues Detected**
   - Both text_body and html_body are empty
   - Missing common payment keywords
   - HTML doesn't contain table structures
   - Other format issues

### Where to Find Diagnostics

1. **Admin Panel → Match Logs**
   - Find the failed match attempt
   - Click to view details
   - Check the "Match Reason" section - it now contains detailed diagnostic information

2. **Processed Email Details**
   - Go to the email that failed to extract
   - Check the "Last Match Reason" field
   - View full text_body and html_body content

## How to Use Diagnostics to Fix Extraction

### Step 1: Check Match Attempt Details

1. Go to **Admin Panel → Match Logs**
2. Find a failed extraction (match_result = "unmatched", reason contains "Could not extract")
3. Click to view details
4. Read the "Match Reason" section - it now contains:
   - Extraction steps that were attempted
   - Specific errors encountered
   - Content length analysis
   - Text/HTML previews
   - Potential issues detected

### Step 2: Analyze Email Content

1. Check the **text_preview** and **html_preview** in the diagnostics
2. Look for:
   - Where the amount appears in the email
   - What format the amount is in (e.g., "NGN 1,000.00", "₦1000", "1000 Naira")
   - Whether the email uses tables or divs
   - Whether payment keywords are present

### Step 3: Identify the Issue

Based on the diagnostics, identify which category the failure falls into:

- **Empty Content**: Email wasn't parsed correctly → Check email fetching process
- **Format Not Recognized**: Email uses different format → Need to add new extraction patterns
- **Amount Format Issue**: Amount exists but format is different → Need to update regex patterns
- **HTML Structure Issue**: HTML doesn't use tables → Need to add div-based extraction patterns

### Step 4: Create Solution

Based on the identified issue:

1. **Add New Extraction Patterns**
   - If amount format is different, add regex patterns to `extractFromTextBody()` or `extractFromHtmlBody()`
   - Test with the actual email content from preview

2. **Create Bank Email Template**
   - If emails are from a specific bank with consistent format, create a template
   - Templates have highest priority and are most accurate

3. **Improve Email Parsing**
   - If content is empty, check the email fetching process (`ReadEmailsDirect`, `MonitorEmails`)
   - Ensure emails are being parsed correctly (text_body and html_body populated)

4. **Handle Special Cases**
   - Some emails might need manual processing
   - Consider adding a "Manual Review" flag for emails that can't be auto-extracted

## Example Diagnostic Output

```
Could not extract payment info from email.

Extraction Steps:
- Template lookup: Not found
- Text body check: Present (234 chars)
- HTML body check: Present (15432 chars)
- HTML-to-text conversion: 543 chars

Errors Encountered:
- Text body extraction failed: No amount found in text body
- HTML body extraction failed: No amount found in HTML (tried table and text patterns)
- HTML rendered text extraction failed: No amount found after converting HTML to text

Email Content Analysis:
- Text Body Length: 234 chars
- HTML Body Length: 15432 chars
- Subject: Credit Alert - GTBank
- From: noreply@gtbank.com

Text Body Preview (first 500 chars):
Your account has been credited with the sum of One Thousand Naira Only (NGN 1,000.00)...

HTML Body Preview (first 500 chars):
<table><tr><td>Amount</td><td>NGN 1,000.00</td></tr>...

Potential Issues Detected:
- HTML body doesn't contain common payment keywords (amount, ngn, naira, payment, transfer, credit, deposit)
```

**Note**: This example shows an issue where the amount format might include "One Thousand Naira Only" in text but the HTML preview shows "NGN 1,000.00" - the system might not be matching this exact format.

## Next Steps

1. **Review Failed Extractions**: Check several failed extractions to identify common patterns
2. **Gather Sample Emails**: Collect actual email content (text_body and html_body) from failed extractions
3. **Test New Patterns**: Use the email previews to test new extraction patterns
4. **Create Templates**: For banks with consistent formats, create bank email templates
5. **Update Extraction Logic**: Add new patterns to handle identified formats

## Contact for Support

When seeking help with extraction issues, provide:

1. Match attempt ID or ProcessedEmail ID
2. The full "Match Reason" diagnostic output
3. Screenshot of the email content (if possible)
4. Description of what format the amount is in
5. Bank name and email sender address

This information will help identify the exact issue and create appropriate extraction patterns.
