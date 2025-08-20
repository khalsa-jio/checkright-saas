#!/bin/bash

# Mobile API Key Generator Script
# Generates a secure 64-character hexadecimal API key for mobile app authentication

set -e

echo "🔐 Mobile API Key Generator"
echo "=========================="
echo ""

# Check if OpenSSL is available
if ! command -v openssl &> /dev/null; then
    echo "❌ Error: OpenSSL is not installed or not in PATH"
    echo "Please install OpenSSL to generate secure keys"
    exit 1
fi

# Generate the key
echo "🔄 Generating secure 64-character hexadecimal API key..."
MOBILE_API_KEY=$(openssl rand -hex 32)

echo ""
echo "✅ Generated Mobile API Key:"
echo "MOBILE_API_KEY=$MOBILE_API_KEY"
echo ""

# Provide usage instructions
echo "📋 Next Steps:"
echo "1. Backend (Laravel): Add to your .env file:"
echo "   MOBILE_API_KEY=$MOBILE_API_KEY"
echo ""
echo "2. Mobile App: Add to your environment files:"
echo "   Development: apps/mobile-app/.env.development"
echo "   Staging: apps/mobile-app/.env.staging"
echo "   Production: apps/mobile-app/.env.production"
echo ""
echo "3. For production deployment:"
echo "   - Use your CI/CD secrets management"
echo "   - Never commit production keys to version control"
echo "   - Coordinate deployment between backend and mobile app"
echo ""

# Security reminders
echo "⚠️  Security Reminders:"
echo "• Rotate keys every 90 days minimum"
echo "• Use different keys for development, staging, and production"
echo "• Store production keys in secure secret management systems"
echo "• Monitor API key usage for security anomalies"
echo ""

# Validation
echo "🔍 Key Validation:"
echo "• Length: ${#MOBILE_API_KEY} characters (should be 64)"
echo "• Format: Hexadecimal (0-9, a-f only)"
echo "• Entropy: 256 bits (cryptographically secure)"
echo ""

if [ ${#MOBILE_API_KEY} -eq 64 ]; then
    echo "✅ Key length is correct"
else
    echo "❌ Warning: Key length is incorrect"
fi

# Check if key contains only hex characters
if [[ $MOBILE_API_KEY =~ ^[0-9a-f]{64}$ ]]; then
    echo "✅ Key format is correct (hexadecimal)"
else
    echo "❌ Warning: Key format is incorrect"
fi

echo ""
echo "🔐 Key generation complete!"