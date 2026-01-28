#!/bin/bash
# Laravel Queue Worker Script for cPanel
# This script runs the queue worker and automatically restarts it if it crashes

# Change to the application directory
cd /var/www/checkout || exit 1

# Set the PHP path (adjust if needed)
PHP_BIN="/usr/bin/php"

# Log file location
LOG_FILE="/var/www/checkout/storage/logs/queue-worker.log"

# Maximum memory limit (in MB)
MAX_MEMORY=512

# Timeout for each job (in seconds)
TIMEOUT=60

# Sleep time when no jobs available (in seconds)
SLEEP=3

# Maximum time to run before restarting (in seconds) - 1 hour
MAX_TIME=3600

# Function to log messages
log_message() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1" >> "$LOG_FILE"
}

# Function to check if queue worker is already running
check_running() {
    pgrep -f "queue:work" > /dev/null
    return $?
}

# Check if already running
if check_running; then
    log_message "Queue worker is already running. Exiting."
    exit 0
fi

log_message "Starting queue worker..."

# Run the queue worker with auto-restart
while true; do
    log_message "Queue worker started (PID: $$)"
    
    # Run queue worker with options
    $PHP_BIN artisan queue:work \
        --queue=default \
        --timeout=$TIMEOUT \
        --memory=$MAX_MEMORY \
        --sleep=$SLEEP \
        --max-time=$MAX_TIME \
        --tries=3 \
        --stop-when-empty=false \
        2>&1 | tee -a "$LOG_FILE"
    
    EXIT_CODE=$?
    
    log_message "Queue worker exited with code: $EXIT_CODE"
    
    # If exit code is 0 (normal shutdown) or 12 (SIGUSR2 - graceful shutdown), don't restart
    if [ $EXIT_CODE -eq 0 ] || [ $EXIT_CODE -eq 12 ]; then
        log_message "Queue worker stopped gracefully. Exiting."
        break
    fi
    
    # Wait a bit before restarting
    log_message "Queue worker crashed. Restarting in 5 seconds..."
    sleep 5
done

log_message "Queue worker script ended."
