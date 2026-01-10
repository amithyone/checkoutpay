#!/bin/bash
# Script to upload files to server and run setup
# Run this from your LOCAL machine (where you have the checkoutpay repo)

echo "========================================="
echo "Upload and Setup Script"
echo "========================================="
echo ""
echo "This script will help you:"
echo "  1. Upload extract_simple.py to server"
echo "  2. Upload test_python_extraction.php to server"
echo "  3. Upload setup script to server"
echo "  4. Run setup on server"
echo ""

# Configuration
read -p "Enter server username (default: checzspw): " SERVER_USER
SERVER_USER=${SERVER_USER:-checzspw}

read -p "Enter server hostname (e.g., premium340.web-hosting.com): " SERVER_HOST
if [ -z "$SERVER_HOST" ]; then
    echo "✗ Server hostname is required"
    exit 1
fi

read -p "Enter server path (default: ~/public_html): " SERVER_PATH
SERVER_PATH=${SERVER_PATH:-~/public_html}

echo ""
echo "Server configuration:"
echo "  User: $SERVER_USER"
echo "  Host: $SERVER_HOST"
echo "  Path: $SERVER_PATH"
echo ""

read -p "Continue? (y/n): " CONFIRM
if [ "$CONFIRM" != "y" ] && [ "$CONFIRM" != "Y" ]; then
    echo "Cancelled."
    exit 0
fi

# Check if SSH key exists
if [ ! -f ~/.ssh/id_rsa ] && [ ! -f ~/.ssh/id_rsa.pub ]; then
    echo ""
    echo "Generating SSH key..."
    ssh-keygen -t rsa -b 2048 -f ~/.ssh/id_rsa -N ""
fi

# Display public key (user needs to add to authorized_keys)
echo ""
echo "========================================="
echo "SSH Key Setup"
echo "========================================="
echo ""
echo "Your public key:"
cat ~/.ssh/id_rsa.pub
echo ""
echo "You need to add this key to your server's authorized_keys:"
echo "  1. SSH to your server: ssh $SERVER_USER@$SERVER_HOST"
echo "  2. Run: mkdir -p ~/.ssh && chmod 700 ~/.ssh"
echo "  3. Run: echo '$(cat ~/.ssh/id_rsa.pub)' >> ~/.ssh/authorized_keys"
echo "  4. Run: chmod 600 ~/.ssh/authorized_keys"
echo ""
read -p "Press Enter after adding the key to server, or Ctrl+C to cancel..."

# Upload files
echo ""
echo "Uploading files..."
scp python-extractor/extract_simple.py $SERVER_USER@$SERVER_HOST:$SERVER_PATH/python-extractor/extract_simple.py
scp test_python_extraction.php $SERVER_USER@$SERVER_HOST:$SERVER_PATH/test_python_extraction.php
scp setup_python_on_server.sh $SERVER_USER@$SERVER_HOST:$SERVER_PATH/setup_python_on_server.sh

echo "✓ Files uploaded"

# Run setup on server
echo ""
echo "Running setup on server..."
ssh $SERVER_USER@$SERVER_HOST "cd $SERVER_PATH && bash setup_python_on_server.sh"

echo ""
echo "========================================="
echo "Complete!"
echo "========================================="
echo ""
echo "Check the output above for any errors."
echo "Next: Test with 'php test_python_extraction.php' on server"
