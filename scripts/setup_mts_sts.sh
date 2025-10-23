#!/usr/bin/env bash

# MTA-STS (Mail Transfer Agent Strict Transport Security) Setup Script
# This script helps configure MTA-STS and TLS-RPT for email security
#
# MTA-STS (RFC 8461): Enables mail servers to declare their ability to receive TLS-secured connections
# TLS-RPT (RFC 8460): Provides a reporting mechanism for TLS connection issues
#
# Usage: ./setup_mta_sts.sh [domain] [mx-servers]
# Example: ./setup_mta_sts.sh example.com "mail.example.com,mail2.example.com"

set -e

# Color codes for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Function to print colored output
print_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Function to display usage
usage() {
    cat << EOF
Usage: $0 [OPTIONS]

Setup MTA-STS and TLS-RPT for your domain.

OPTIONS:
    -d, --domain DOMAIN         Your domain name (e.g., example.com)
    -m, --mx-servers SERVERS    Comma-separated list of MX servers (e.g., mail.example.com,mail2.example.com)
    -e, --email EMAIL           Email address for TLS-RPT reports
    -o, --output-dir DIR        Output directory for generated files (default: ./mta-sts-config)
    -h, --help                  Display this help message

EXAMPLES:
    $0 -d example.com -m mail.example.com -e admin@example.com
    $0 --domain example.com --mx-servers "mail1.example.com,mail2.example.com" --email security@example.com

DESCRIPTION:
    This script generates the necessary configuration files for MTA-STS and TLS-RPT:

    1. MTA-STS Policy File: Declares which MX servers are authorized to receive email
    2. DNS Records: TXT records needed for MTA-STS and TLS-RPT
    3. Web Server Configuration: Example configuration for serving the policy file

    After running this script:
    - Upload the policy file to https://mta-sts.yourdomain.com/.well-known/mta-sts.txt
    - Add the DNS TXT records to your domain
    - Configure your web server to serve the policy file with HTTPS

REFERENCES:
    - MTA-STS RFC: https://tools.ietf.org/html/rfc8461
    - TLS-RPT RFC: https://tools.ietf.org/html/rfc8460
    - Validator: https://aykevl.nl/apps/mta-sts/
    - Guide: https://dmarcly.com/blog/how-to-set-up-mta-sts-and-tls-reporting

EOF
}

# Parse command line arguments
DOMAIN=""
MX_SERVERS=""
REPORT_EMAIL=""
OUTPUT_DIR="./mta-sts-config"

while [[ $# -gt 0 ]]; do
    case $1 in
        -d|--domain)
            DOMAIN="$2"
            shift 2
            ;;
        -m|--mx-servers)
            MX_SERVERS="$2"
            shift 2
            ;;
        -e|--email)
            REPORT_EMAIL="$2"
            shift 2
            ;;
        -o|--output-dir)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        -h|--help)
            usage
            exit 0
            ;;
        *)
            print_error "Unknown option: $1"
            usage
            exit 1
            ;;
    esac
done

# Validate required parameters
if [ -z "$DOMAIN" ]; then
    print_error "Domain is required"
    usage
    exit 1
fi

if [ -z "$MX_SERVERS" ]; then
    print_error "MX servers are required"
    usage
    exit 1
fi

if [ -z "$REPORT_EMAIL" ]; then
    print_warning "No report email specified, using postmaster@${DOMAIN}"
    REPORT_EMAIL="postmaster@${DOMAIN}"
fi

# Create output directory
mkdir -p "$OUTPUT_DIR"

print_info "Generating MTA-STS configuration for domain: ${DOMAIN}"
print_info "Output directory: ${OUTPUT_DIR}"

# Generate MTA-STS policy file
POLICY_FILE="${OUTPUT_DIR}/mta-sts.txt"
cat > "$POLICY_FILE" << EOF
version: STSv1
mode: enforce
mx: $(echo "$MX_SERVERS" | sed 's/,/\nmx: /g')
max_age: 604800
EOF

print_info "Generated MTA-STS policy file: ${POLICY_FILE}"

# Generate policy ID (timestamp-based version)
POLICY_ID=$(date +%Y%m%d%H%M%S)

# Generate DNS records file
DNS_FILE="${OUTPUT_DIR}/dns-records.txt"
cat > "$DNS_FILE" << EOF
# DNS Records for MTA-STS and TLS-RPT
# Add these TXT records to your DNS configuration

# MTA-STS DNS Record
# This record points to your MTA-STS policy
_mta-sts.${DOMAIN}. IN TXT "v=STSv1; id=${POLICY_ID};"

# TLS-RPT DNS Record
# This record specifies where to send TLS failure reports
_smtp._tls.${DOMAIN}. IN TXT "v=TLSRPTv1; rua=mailto:${REPORT_EMAIL};"

# Note: Update the policy ID when you make changes to your MTA-STS policy
# The ID should be incremented or changed to signal policy updates to mail servers
EOF

print_info "Generated DNS records file: ${DNS_FILE}"

# Generate web server configuration examples
WEBSERVER_CONFIG="${OUTPUT_DIR}/webserver-config-examples.txt"
cat > "$WEBSERVER_CONFIG" << EOF
# Web Server Configuration Examples for MTA-STS
# The MTA-STS policy must be served over HTTPS at:
# https://mta-sts.${DOMAIN}/.well-known/mta-sts.txt

# ============================================================================
# Apache Configuration Example
# ============================================================================
# Add this to your Apache virtual host configuration:

<VirtualHost *:443>
    ServerName mta-sts.${DOMAIN}

    SSLEngine on
    SSLCertificateFile /path/to/cert.pem
    SSLCertificateKeyFile /path/to/key.pem

    DocumentRoot /var/www/mta-sts/${DOMAIN}

    <Directory /var/www/mta-sts/${DOMAIN}/.well-known>
        Require all granted
    </Directory>

    # Ensure proper content type
    <FilesMatch "mta-sts\.txt$">
        Header set Content-Type "text/plain"
    </FilesMatch>
</VirtualHost>

# ============================================================================
# Nginx Configuration Example
# ============================================================================
# Add this to your Nginx configuration:

server {
    listen 443 ssl http2;
    server_name mta-sts.${DOMAIN};

    ssl_certificate /path/to/cert.pem;
    ssl_certificate_key /path/to/key.pem;

    root /var/www/mta-sts/${DOMAIN};

    location /.well-known/mta-sts.txt {
        default_type text/plain;
        add_header Content-Type "text/plain; charset=utf-8";
    }
}

# ============================================================================
# Deployment Instructions
# ============================================================================

1. Create the directory structure:
   mkdir -p /var/www/mta-sts/${DOMAIN}/.well-known

2. Copy the policy file:
   cp ${POLICY_FILE} /var/www/mta-sts/${DOMAIN}/.well-known/mta-sts.txt

3. Set appropriate permissions:
   chmod 644 /var/www/mta-sts/${DOMAIN}/.well-known/mta-sts.txt

4. Configure your web server (Apache or Nginx) with the examples above

5. Obtain a valid SSL certificate for mta-sts.${DOMAIN}
   - You can use Let's Encrypt: certbot --nginx -d mta-sts.${DOMAIN}

6. Test the configuration:
   curl https://mta-sts.${DOMAIN}/.well-known/mta-sts.txt

7. Add the DNS TXT records from ${DNS_FILE}

8. Validate your setup:
   - Use online validators like https://aykevl.nl/apps/mta-sts/
   - Test with: dig TXT _mta-sts.${DOMAIN}
EOF

print_info "Generated web server configuration examples: ${WEBSERVER_CONFIG}"

# Generate a summary/README file
README_FILE="${OUTPUT_DIR}/README.md"
cat > "$README_FILE" << EOF
# MTA-STS and TLS-RPT Configuration for ${DOMAIN}

This directory contains the configuration files needed to implement MTA-STS and TLS-RPT for your domain.

## Generated Files

1. **mta-sts.txt** - The MTA-STS policy file that declares your mail server security requirements
2. **dns-records.txt** - DNS TXT records that need to be added to your domain
3. **webserver-config-examples.txt** - Example configurations for Apache and Nginx web servers
4. **README.md** - This file

## Quick Start

### Step 1: Deploy the Policy File

The policy file must be accessible at:
\`\`\`
https://mta-sts.${DOMAIN}/.well-known/mta-sts.txt
\`\`\`

Copy the policy file to your web server:
\`\`\`bash
mkdir -p /var/www/mta-sts/${DOMAIN}/.well-known
cp mta-sts.txt /var/www/mta-sts/${DOMAIN}/.well-known/
chmod 644 /var/www/mta-sts/${DOMAIN}/.well-known/mta-sts.txt
\`\`\`

### Step 2: Configure Web Server

Configure your web server to serve the policy file over HTTPS. See \`webserver-config-examples.txt\` for Apache and Nginx examples.

**Important:** You must have a valid SSL certificate for \`mta-sts.${DOMAIN}\`

### Step 3: Add DNS Records

Add the DNS TXT records from \`dns-records.txt\` to your domain's DNS configuration:

- \`_mta-sts.${DOMAIN}\` - Points to your MTA-STS policy
- \`_smtp._tls.${DOMAIN}\` - Specifies where to send TLS failure reports

### Step 4: Verify Configuration

Test your configuration:
\`\`\`bash
# Test policy file accessibility
curl https://mta-sts.${DOMAIN}/.well-known/mta-sts.txt

# Verify DNS records
dig TXT _mta-sts.${DOMAIN}
dig TXT _smtp._tls.${DOMAIN}
\`\`\`

Use online validators:
- https://aykevl.nl/apps/mta-sts/ (if available)
- Check MX records and policy consistency

## Configuration Details

### MX Servers
Your configured MX servers:
$(echo "$MX_SERVERS" | sed 's/,/\n- /g' | sed 's/^/- /')

### TLS-RPT Email
Reports will be sent to: ${REPORT_EMAIL}

### Policy Mode
Current mode: **enforce** (strict enforcement)

You can change the mode to \`testing\` during initial deployment to avoid delivery issues while testing the configuration.

## Updating the Policy

When you update your MTA-STS policy:

1. Modify the \`mta-sts.txt\` file
2. Update the policy file on your web server
3. **Important:** Change the policy ID in the DNS TXT record (\`_mta-sts.${DOMAIN}\`)
   - The ID can be any string, but it must change to signal an update
   - We recommend using a timestamp: \`id=${POLICY_ID}\`

## References

- [RFC 8461 - MTA-STS](https://tools.ietf.org/html/rfc8461)
- [RFC 8460 - TLS-RPT](https://tools.ietf.org/html/rfc8460)
- [Setup Guide](https://dmarcly.com/blog/how-to-set-up-mta-sts-and-tls-reporting)
- [Video Tutorial](https://www.youtube.com/watch?v=dFiPUrrVFD4)

## Troubleshooting

### Policy file not accessible
- Ensure SSL certificate is valid for \`mta-sts.${DOMAIN}\`
- Check web server configuration
- Verify file permissions

### DNS records not resolving
- Allow time for DNS propagation (up to 48 hours)
- Verify records with \`dig\` or \`nslookup\`
- Check for typos in record names

### Testing mode
During initial setup, you may want to use \`mode: testing\` instead of \`mode: enforce\` in the policy file. This allows mail delivery to continue even if there are configuration issues.

## Support

For issues or questions:
- Check the Cypht documentation
- Visit https://github.com/cypht-org/cypht/issues/337
- Consult the RFCs and reference materials listed above

Generated on: $(date)
EOF

print_info "Generated README file: ${README_FILE}"

# Create a simple validation script
VALIDATE_SCRIPT="${OUTPUT_DIR}/validate.sh"
cat > "$VALIDATE_SCRIPT" << 'VALIDATE_SCRIPT_CONTENT'
#!/usr/bin/env bash

# Simple validation script for MTA-STS configuration

set -e

DOMAIN="${1}"

if [ -z "$DOMAIN" ]; then
    echo "Usage: $0 <domain>"
    exit 1
fi

echo "Validating MTA-STS configuration for: ${DOMAIN}"
echo "================================================"
echo ""

echo "1. Checking MTA-STS DNS record..."
if dig TXT "_mta-sts.${DOMAIN}" +short | grep -q "v=STSv1"; then
    echo "   ✓ MTA-STS DNS record found"
    dig TXT "_mta-sts.${DOMAIN}" +short
else
    echo "   ✗ MTA-STS DNS record not found or invalid"
fi
echo ""

echo "2. Checking TLS-RPT DNS record..."
if dig TXT "_smtp._tls.${DOMAIN}" +short | grep -q "v=TLSRPTv1"; then
    echo "   ✓ TLS-RPT DNS record found"
    dig TXT "_smtp._tls.${DOMAIN}" +short
else
    echo "   ✗ TLS-RPT DNS record not found or invalid"
fi
echo ""

echo "3. Checking policy file accessibility..."
POLICY_URL="https://mta-sts.${DOMAIN}/.well-known/mta-sts.txt"
if curl -s -f -m 10 "$POLICY_URL" > /dev/null 2>&1; then
    echo "   ✓ Policy file is accessible at: ${POLICY_URL}"
    echo ""
    echo "   Policy content:"
    curl -s "$POLICY_URL" | sed 's/^/   /'
else
    echo "   ✗ Policy file is not accessible at: ${POLICY_URL}"
    echo "   Make sure:"
    echo "   - The file is uploaded to the correct location"
    echo "   - Web server is configured correctly"
    echo "   - SSL certificate is valid for mta-sts.${DOMAIN}"
fi
echo ""

echo "4. Checking SSL certificate for mta-sts.${DOMAIN}..."
if echo | openssl s_client -connect "mta-sts.${DOMAIN}:443" -servername "mta-sts.${DOMAIN}" 2>/dev/null | grep -q "Verify return code: 0"; then
    echo "   ✓ SSL certificate is valid"
else
    echo "   ✗ SSL certificate issue detected"
fi
echo ""

echo "================================================"
echo "Validation complete!"
echo ""
echo "For more thorough validation, use online tools:"
echo "- https://aykevl.nl/apps/mta-sts/ (if available)"
echo "- Your domain: ${DOMAIN}"
VALIDATE_SCRIPT_CONTENT

chmod +x "$VALIDATE_SCRIPT"

print_info "Generated validation script: ${VALIDATE_SCRIPT}"

# Print summary
echo ""
echo "============================================================================"
print_info "MTA-STS configuration generation complete!"
echo "============================================================================"
echo ""
echo "Generated files in ${OUTPUT_DIR}:"
echo "  - mta-sts.txt                     (Policy file)"
echo "  - dns-records.txt                 (DNS TXT records)"
echo "  - webserver-config-examples.txt   (Web server setup)"
echo "  - README.md                       (Detailed instructions)"
echo "  - validate.sh                     (Validation script)"
echo ""
echo "Next steps:"
echo "  1. Read ${OUTPUT_DIR}/README.md for detailed instructions"
echo "  2. Deploy the policy file to https://mta-sts.${DOMAIN}/.well-known/mta-sts.txt"
echo "  3. Add the DNS records from dns-records.txt"
echo "  4. Run ${VALIDATE_SCRIPT} ${DOMAIN} to validate your setup"
echo ""
echo "For more information, visit:"
echo "  - https://github.com/cypht-org/cypht/issues/337"
echo "  - https://tools.ietf.org/html/rfc8461"
echo ""
