#!/usr/bin/env python3
"""
Simple Python extraction script for shared hosting.
No external dependencies - uses only standard library.
Called from PHP via shell_exec().
"""

import sys
import json
import re
from html.parser import HTMLParser

def strip_html(html):
    """Simple HTML tag removal (no external dependencies)."""
    if not html:
        return ""
    # Remove script and style tags
    html = re.sub(r'<script[^>]*>.*?</script>', '', html, flags=re.DOTALL | re.IGNORECASE)
    html = re.sub(r'<style[^>]*>.*?</style>', '', html, flags=re.DOTALL | re.IGNORECASE)
    # Remove all HTML tags
    text = re.sub(r'<[^>]+>', ' ', html)
    # Clean whitespace
    text = re.sub(r'\s+', ' ', text)
    return text.strip()

def extract_amount_from_html_table(html):
    """Extract amount from HTML table structures."""
    if not html:
        return None
    
    # Pattern 1: <td>Amount</td><td>NGN 1000</td>
    pattern1 = r'<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]*</td>\s*<td[^>]*>[\s]*(?:ngn|naira|₦)[\s&nbsp;]*([\d,]+\.?\d*)[\s]*</td>'
    match = re.search(pattern1, html, re.IGNORECASE)
    if match:
        try:
            amount = float(match.group(1).replace(',', ''))
            if amount >= 10:
                return amount, 0.95  # High confidence
        except (ValueError, IndexError):
            pass
    
    # Pattern 2: <td>Amount: NGN 1000</td>
    pattern2 = r'<td[^>]*>[\s]*(?:amount|sum|value|total|paid|payment)[\s:]+(?:ngn|naira|₦)[\s&nbsp;]*([\d,]+\.?\d*)[\s]*</td>'
    match = re.search(pattern2, html, re.IGNORECASE)
    if match:
        try:
            amount = float(match.group(1).replace(',', ''))
            if amount >= 10:
                return amount, 0.95
        except (ValueError, IndexError):
            pass
    
    # Pattern 3: Any <td> with NGN amount
    pattern3 = r'<td[^>]*>[\s]*(?:ngn|naira|₦)\s*([\d,]+\.?\d*)[\s]*</td>'
    match = re.search(pattern3, html, re.IGNORECASE)
    if match:
        try:
            amount = float(match.group(1).replace(',', ''))
            if amount >= 10:
                return amount, 0.90
        except (ValueError, IndexError):
            pass
    
    return None

def extract_amount_from_html_text(html):
    """Extract amount from HTML text (not table-based)."""
    if not html:
        return None
    
    patterns = [
        r'(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
    ]
    
    for pattern in patterns:
        matches = re.finditer(pattern, html, re.IGNORECASE)
        for match in matches:
            try:
                amount = float(match.group(1).replace(',', ''))
                if amount >= 10:
                    return amount, 0.85
            except (ValueError, IndexError):
                continue
    
    return None

def extract_amount_from_text(text):
    """Extract amount from plain text."""
    if not text:
        return None
    
    patterns = [
        r'(?:amount|sum|value|total|paid|payment|deposit|transfer|credit)[\s:]+(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'(?:ngn|naira|₦)\s*([\d,]+\.?\d*)',
        r'([\d,]+\.?\d*)\s*(?:naira|ngn)',
    ]
    
    for pattern in patterns:
        matches = re.finditer(pattern, text, re.IGNORECASE)
        for match in matches:
            try:
                amount = float(match.group(1).replace(',', ''))
                if amount >= 10:
                    return amount, 0.80
            except (ValueError, IndexError):
                continue
    
    return None

def extract_sender_name(html, text):
    """Extract sender name from email content."""
    content = (html or "") + " " + (text or "")
    
    # Pattern 1: FROM NAME TO
    match = re.search(r'from\s+([A-Z][A-Z\s]+?)\s+to', content, re.IGNORECASE)
    if match:
        name = match.group(1).strip().lower()
        if len(name) >= 3:
            return name
    
    # Pattern 2: NAME TRF FOR
    match = re.search(r'description[\s:]+.*?[\s\-]*([A-Z][A-Z\s]{2,}?)\s+(?:TRF|TRANSFER|FOR|TO)', content, re.IGNORECASE)
    if match:
        name = match.group(1).strip().lower()
        name = re.sub(r'^[\d\-\s]+', '', name)
        if len(name) >= 3:
            return name
    
    return None

def main():
    """Main extraction function."""
    try:
        # Read input from stdin (called from PHP)
        input_data = json.loads(sys.stdin.read())
        
        text_body = input_data.get('text_body', '')
        html_body = input_data.get('html_body', '')
        from_email = input_data.get('from_email', '').lower()
        
        diagnostics = {
            "steps": [],
            "errors": [],
            "text_length": len(text_body or ""),
            "html_length": len(html_body or ""),
        }
        
        # Strategy 1: Try HTML table extraction (highest confidence)
        if html_body:
            diagnostics["steps"].append("Attempting HTML table extraction")
            result = extract_amount_from_html_table(html_body)
            if result:
                amount, confidence = result
                diagnostics["steps"].append(f"Extraction successful: html_table (confidence: {confidence})")
                output = {
                    "success": True,
                    "data": {
                        "amount": amount,
                        "currency": "NGN",
                        "direction": "credit",
                        "confidence": confidence,
                        "source": "html_table",
                        "sender_name": extract_sender_name(html_body, text_body),
                    },
                    "diagnostics": diagnostics,
                }
                print(json.dumps(output))
                return
        
        # Strategy 2: Try HTML text extraction
        if html_body:
            diagnostics["steps"].append("Attempting HTML text extraction")
            result = extract_amount_from_html_text(html_body)
            if result:
                amount, confidence = result
                diagnostics["steps"].append(f"Extraction successful: html_text (confidence: {confidence})")
                output = {
                    "success": True,
                    "data": {
                        "amount": amount,
                        "currency": "NGN",
                        "direction": "credit",
                        "confidence": confidence,
                        "source": "html_text",
                        "sender_name": extract_sender_name(html_body, text_body),
                    },
                    "diagnostics": diagnostics,
                }
                print(json.dumps(output))
                return
        
        # Strategy 3: Try text body extraction
        if text_body:
            diagnostics["steps"].append("Attempting text body extraction")
            result = extract_amount_from_text(text_body)
            if result:
                amount, confidence = result
                diagnostics["steps"].append(f"Extraction successful: text_body (confidence: {confidence})")
                output = {
                    "success": True,
                    "data": {
                        "amount": amount,
                        "currency": "NGN",
                        "direction": "credit",
                        "confidence": confidence,
                        "source": "text_body",
                        "sender_name": extract_sender_name(html_body, text_body),
                    },
                    "diagnostics": diagnostics,
                }
                print(json.dumps(output))
                return
        
        # Strategy 4: Convert HTML to text and try again
        if html_body:
            rendered_text = strip_html(html_body)
            if rendered_text:
                diagnostics["steps"].append("Attempting HTML-to-text conversion extraction")
                result = extract_amount_from_text(rendered_text)
                if result:
                    amount, confidence = result
                    diagnostics["steps"].append(f"Extraction successful: html_rendered_text (confidence: {confidence})")
                    output = {
                        "success": True,
                        "data": {
                            "amount": amount,
                            "currency": "NGN",
                            "direction": "credit",
                            "confidence": confidence * 0.9,  # Lower confidence for converted text
                            "source": "html_rendered_text",
                            "sender_name": extract_sender_name(html_body, text_body),
                        },
                        "diagnostics": diagnostics,
                    }
                    print(json.dumps(output))
                    return
        
        # All strategies failed
        diagnostics["errors"].append("All extraction strategies failed")
        output = {
            "success": False,
            "errors": diagnostics["errors"],
            "diagnostics": diagnostics,
        }
        print(json.dumps(output))
        
    except Exception as e:
        error_output = {
            "success": False,
            "errors": [f"Extraction exception: {str(e)}"],
            "diagnostics": {
                "steps": [],
                "errors": [str(e)],
            },
        }
        print(json.dumps(error_output))
        sys.exit(1)

if __name__ == "__main__":
    main()
