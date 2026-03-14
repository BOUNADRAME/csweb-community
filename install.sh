#!/bin/bash

# ============================================================================
# CSWeb Community Platform - Installation Script
# ============================================================================
# Author: Bouna DRAME
# Date: 14 Mars 2026
# Version: 1.0.0
#
# Description:
#   Automated installation script for CSWeb Community Platform
#   Handles .env configuration, Docker setup, and initial verification
#
# Usage:
#   chmod +x install.sh
#   ./install.sh
# ============================================================================

set -e  # Exit on error

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Functions
print_header() {
    echo -e "${BLUE}"
    echo "============================================================================"
    echo "  CSWeb Community Platform - Installation"
    echo "============================================================================"
    echo -e "${NC}"
}

print_success() {
    echo -e "${GREEN}✓ $1${NC}"
}

print_error() {
    echo -e "${RED}✗ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# Check prerequisites
check_prerequisites() {
    print_info "Checking prerequisites..."

    # Check Docker
    if ! command -v docker &> /dev/null; then
        print_error "Docker is not installed. Please install Docker first."
        echo "Visit: https://docs.docker.com/get-docker/"
        exit 1
    fi
    print_success "Docker is installed"

    # Check Docker Compose
    if ! command -v docker-compose &> /dev/null; then
        print_error "Docker Compose is not installed. Please install Docker Compose first."
        echo "Visit: https://docs.docker.com/compose/install/"
        exit 1
    fi
    print_success "Docker Compose is installed"

    # Check if Docker is running
    if ! docker info &> /dev/null; then
        print_error "Docker daemon is not running. Please start Docker."
        exit 1
    fi
    print_success "Docker daemon is running"
}

# Generate random password
generate_password() {
    openssl rand -base64 24 | tr -d "=+/" | cut -c1-24
}

# Create .env file
create_env_file() {
    print_info "Creating .env configuration file..."

    if [ -f .env ]; then
        print_warning ".env file already exists. Creating backup..."
        cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    fi

    # Generate secure passwords
    MYSQL_ROOT_PASS=$(generate_password)
    MYSQL_PASS=$(generate_password)
    POSTGRES_PASS=$(generate_password)
    APP_SECRET=$(openssl rand -hex 32)
    JWT_SECRET=$(openssl rand -base64 32)

    # Create .env from template
    cat > .env << EOF
# ============================================================================
# CSWeb Community Platform - Environment Configuration
# ============================================================================
# Generated on: $(date)
#
# IMPORTANT: Keep this file secure and never commit it to version control
# ============================================================================

# Application
APP_ENV=prod
APP_DEBUG=false
APP_SECRET=${APP_SECRET}
APP_TIMEZONE=UTC

# Ports
CSWEB_PORT=8080
MYSQL_PORT=3306
POSTGRES_PORT=5432
PHPMYADMIN_PORT=8081
PGADMIN_PORT=8082

# MySQL (CSWeb Metadata - FIXE)
MYSQL_HOST=mysql
MYSQL_PORT=3306
MYSQL_DATABASE=csweb_metadata
MYSQL_USER=csweb_user
MYSQL_PASSWORD=${MYSQL_PASS}
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASS}

# PostgreSQL (Breakout Analytics - Default)
POSTGRES_HOST=postgres
POSTGRES_PORT=5432
POSTGRES_DATABASE=csweb_analytics
POSTGRES_USER=csweb_analytics
POSTGRES_PASSWORD=${POSTGRES_PASS}

# Breakout Configuration
DEFAULT_BREAKOUT_DB_TYPE=postgresql

# Database Timezone
DB_TIMEZONE=UTC

# phpMyAdmin (Development)
PHPMYADMIN_PORT=8081

# pgAdmin (Development)
PGADMIN_PORT=8082
PGADMIN_EMAIL=admin@csweb.local
PGADMIN_PASSWORD=admin123

# JWT Authentication
JWT_SECRET=${JWT_SECRET}
JWT_EXPIRATION=86400000

# Files Directory
FILES_FOLDER=/var/www/html/files

# API URL (update with your domain in production)
API_URL=http://localhost:8080/api/

# Logging
CSWEB_LOG_LEVEL=error
CSWEB_PROCESS_CASES_LOG_LEVEL=error

# Maximum Execution Time
MAX_EXECUTION_TIME=300
EOF

    print_success ".env file created with secure random passwords"
    print_warning "Passwords generated:"
    echo "  MySQL Root: ${MYSQL_ROOT_PASS}"
    echo "  MySQL User: ${MYSQL_PASS}"
    echo "  PostgreSQL: ${POSTGRES_PASS}"
    echo ""
    print_warning "Save these passwords in a secure location!"
}

# Pull Docker images
pull_images() {
    print_info "Pulling Docker images..."
    docker-compose pull
    print_success "Docker images pulled successfully"
}

# Start services
start_services() {
    print_info "Starting Docker services..."
    docker-compose up -d
    print_success "Docker services started"
}

# Wait for services
wait_for_services() {
    print_info "Waiting for services to be ready..."

    echo -n "  Waiting for MySQL"
    for i in {1..30}; do
        if docker-compose exec -T mysql mysqladmin ping -h localhost --silent &> /dev/null; then
            echo " ✓"
            break
        fi
        echo -n "."
        sleep 2
    done

    echo -n "  Waiting for PostgreSQL"
    for i in {1..30}; do
        if docker-compose exec -T postgres pg_isready -U csweb_analytics &> /dev/null; then
            echo " ✓"
            break
        fi
        echo -n "."
        sleep 2
    done

    print_success "All services are ready"
}

# Display information
display_info() {
    echo ""
    print_header
    print_success "Installation completed successfully!"
    echo ""
    print_info "Access CSWeb:"
    echo "  URL: http://localhost:8080"
    echo "  Setup: http://localhost:8080/setup/"
    echo ""
    print_info "Development Tools (optional):"
    echo "  phpMyAdmin: http://localhost:8081"
    echo "  pgAdmin: http://localhost:8082"
    echo "    - Email: admin@csweb.local"
    echo "    - Password: admin123"
    echo ""
    print_info "Next Steps:"
    echo "  1. Open http://localhost:8080/setup/ in your browser"
    echo "  2. Complete the CSWeb setup wizard"
    echo "  3. Follow docs/INSTALLATION-CSWEB-VANILLA.md for detailed instructions"
    echo ""
    print_info "Useful Commands:"
    echo "  View logs:        docker-compose logs -f csweb"
    echo "  Stop services:    docker-compose down"
    echo "  Restart services: docker-compose restart"
    echo "  Check status:     docker-compose ps"
    echo ""
    print_warning "Database Credentials (save these!):"
    echo "  MySQL Root Password: (check .env file)"
    echo "  PostgreSQL Password: (check .env file)"
    echo ""
    echo "For documentation, visit: docs/"
    echo "============================================================================"
}

# Main installation flow
main() {
    print_header

    # Check prerequisites
    check_prerequisites

    # Create .env file
    create_env_file

    # Pull images
    pull_images

    # Start services
    start_services

    # Wait for services
    wait_for_services

    # Display information
    display_info
}

# Run main function
main
