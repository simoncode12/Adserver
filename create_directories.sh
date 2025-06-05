#!/bin/bash
# Create directory structure for AdServer platform

# Create main directories
mkdir -p logs
mkdir -p uploads/creatives
mkdir -p uploads/banners
mkdir -p uploads/videos
mkdir -p uploads/temp

# Set permissions (adjust as needed for your server)
chmod 755 logs uploads
chmod 755 uploads/creatives uploads/banners uploads/videos uploads/temp

# Create empty log files
touch logs/error.log
touch logs/access.log
touch logs/rtb.log
touch logs/fraud.log

chmod 644 logs/*.log

echo "Directory structure created successfully!"
