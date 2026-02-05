# Shell Gate Gateway Dockerfile
#
# Build:
#   docker build -t shell-gate-gateway .
#
# Run:
#   docker run -d \
#     -p 7681:7681 \
#     -e JWT_SECRET=your-secret \
#     -e ALLOWED_ORIGINS=https://admin.example.com \
#     --name shell-gate-gateway \
#     shell-gate-gateway

FROM node:20-alpine

# Install build dependencies for node-pty
RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    bash

# Create app directory
WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy source
COPY . .

# Create non-root user for running the gateway
RUN addgroup -S shellgate && adduser -S shellgate -G shellgate

# Set ownership
RUN chown -R shellgate:shellgate /app

# Switch to non-root user
USER shellgate

# Expose port
EXPOSE 7681

# Health check
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD wget --no-verbose --tries=1 --spider http://localhost:7681/health || exit 1

# Start gateway
CMD ["node", "index.js"]
